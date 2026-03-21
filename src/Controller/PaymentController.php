<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Razorpay\Api\Api;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class PaymentController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private ?string $razorpayKeyId;
    private ?string $razorpayKeySecret;
    private ?string $razorpayWebhookSecret;
    private ?string $razorpayPlanId;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->razorpayKeyId = $_ENV['RAZORPAY_KEY_ID'] ?? null;
        $this->razorpayKeySecret = $_ENV['RAZORPAY_KEY_SECRET'] ?? null;
        $this->razorpayWebhookSecret = $_ENV['RAZORPAY_WEBHOOK_SECRET'] ?? null;
        $this->razorpayPlanId = $_ENV['RAZORPAY_PLAN_ID'] ?? null;
    }

    #[Route('/api/subscription/create', name: 'api_subscription_create', methods: ['POST'])]
    public function createSubscription(UserInterface $user): JsonResponse
    {
        
        if (!$this->razorpayKeyId || !$this->razorpayKeySecret || !$this->razorpayPlanId) {
            return new JsonResponse(['error' => 'Razorpay credentials not configured'], 500);
        }

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Invalid user'], 400);
        }

        try {
            $api = new Api($this->razorpayKeyId, $this->razorpayKeySecret);

            $customerId = $user->getRazorpayCustomerId();

            // Create a Razorpay Customer if one doesn't exist
            if (!$customerId) {
                $customerData = [
                    'name' => $user->getFirstName() . ' ' . $user->getLastName(),
                    'email' => $user->getEmail(),
                    'contact' => $user->getPhoneNumber() ?? '0000000000'
                ];
                $customer = $api->customer->create($customerData);
                $customerId = $customer->id;
                $user->setRazorpayCustomerId($customerId);
                $this->entityManager->flush();
            }

            // Create a subscription
            $subscriptionData = [
                'plan_id' => $this->razorpayPlanId,
                'customer_id' => $customerId,
                'total_count' => 120, // Example: 10 years of monthly billing
                'customer_notify' => 1
            ];

            $subscription = $api->subscription->create($subscriptionData);

            return new JsonResponse([
                'subscription_id' => $subscription->id,
                'key_id' => $this->razorpayKeyId,
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/api/webhooks/razorpay', name: 'api_webhooks_razorpay', methods: ['POST'])]
    public function razorpayWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->headers->get('X-Razorpay-Signature');

        if (!$signature || !$this->razorpayWebhookSecret) {
            return new JsonResponse(['status' => 'ignore', 'message' => 'missing signature or secret'], 400);
        }

        try {
            $api = new Api($this->razorpayKeyId, $this->razorpayKeySecret);
            $api->utility->verifyWebhookSignature($payload, $signature, $this->razorpayWebhookSecret);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Invalid signature'], 400);
        }

        $data = json_decode($payload, true);
        if (!$data || !isset($data['event'])) {
            return new JsonResponse(['error' => 'Invalid payload'], 400);
        }

        $event = $data['event'];
        $entityId = $data['payload']['subscription']['entity']['id'] ?? null;
        $customerId = $data['payload']['subscription']['entity']['customer_id'] ?? null;

        if (!$entityId || !$customerId) {
            return new JsonResponse(['status' => 'ignored'], 200);
        }

        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['razorpayCustomerId' => $customerId]);

        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        switch ($event) {
            case 'subscription.activated':
            case 'subscription.charged':
                $user->setPlan('pro');
                $user->setRazorpaySubscriptionId($entityId);
                break;
            case 'subscription.halted':
            case 'subscription.cancelled':
            case 'subscription.completed':
                if ($user->getRazorpaySubscriptionId() === $entityId) {
                    $user->setPlan('free');
                    $user->setRazorpaySubscriptionId(null);
                }
                break;
        }

        $this->entityManager->flush();

        return new JsonResponse(['status' => 'ok']);
    }
}
