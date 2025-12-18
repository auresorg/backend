<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;

#[Route('/auth')]
final class AuthController extends AbstractController
{
    private EntityManagerInterface $em;
    private UserRepository $userRepository;
    private JWTTokenManagerInterface $jwtManager;
    private RefreshTokenManagerInterface $refreshTokenManager;

    private LoggerInterface $logger;
    private string $githubClientId;
    private string $githubClientSecret;

    public function __construct(EntityManagerInterface $em, UserRepository $userRepository, LoggerInterface $logger, JWTTokenManagerInterface $jwtManager, RefreshTokenManagerInterface $refreshTokenManager)
    {
        $this->em = $em;
        $this->userRepository = $userRepository;
        $this->githubClientId = $_ENV['GITHUB_CLIENT_ID'];
        $this->githubClientSecret = $_ENV['GITHUB_CLIENT_SECRET'];
        $this->jwtManager = $jwtManager;
        $this->logger = $logger;
        $this->refreshTokenManager = $refreshTokenManager;
    }

    /**
     * Handle GitHub OAuth authentication
     *
     * @param Request $request
     * @return Response
     *
     * 400 - Bad Request (missing code)
     * 450 - Error requesting access token
     * 451 - Error parsing access token response
     * 460 - Error requesting user info (github)
     * 461 - Error parsing user info response (github)
     *
     * 200 - Success (returns user ID)
     *
     */
    #[Route('/github', name: 'app_auth_github', methods: ['POST'])]
    public function github(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $code = $data['code'] ?? null;

        if (!$code) {
            return new Response(null, 400);
        }

        $client = HttpClient::create();

        try {
            $response = $client->request('POST', 'https://github.com/login/oauth/access_token', [
                'headers' => [
                    'Accept' => 'application/json'
                ],
                'body' => [
                    'client_id' => $this->githubClientId,
                    'client_secret' => $this->githubClientSecret,
                    'code' => $code
                ]
            ]);
        } catch (TransportExceptionInterface $e) {
            return new Response(null, 450);
        }

        $accessToken = null;
        try {
            $tokenData = $response->toArray();
            $accessToken = $tokenData['access_token'] ?? throw new Exception('No access token in response');
        } catch (Exception $e) {
            return new Response(null, 451);
        }

        $githubId = null;
        $username = null;
        $email = null;
        $avatar = null;

        try {
            $userResponse = $client->request('GET', 'https://api.github.com/user', [
                'headers' => [
                    'Authorization' => "token $accessToken",
                    'Accept' => 'application/json'
                ]
            ]);

            $githubUser = $userResponse->toArray();

            $githubId = $githubUser['id'];
            $username = $githubUser['login'];
            $avatar = $githubUser['avatar_url'];
            $email = $githubUser['email'] ?? throw new Exception();

        } catch (TransportExceptionInterface $e) {
            return new Response(null, 460);
        } catch (Exception $e) {
            return new Response(null, 461);
        }

        $user = $this->userRepository->findByGithubId($githubId);

        if (!$user) {
            $user = new User();
            $user->setGithubId($githubId);
            $user->setUsername($username);
            $user->setEmail($email);
            $user->setAvatarUrl($avatar);
            $user->setAccessToken($accessToken);
            $this->em->persist($user);
        } else {
            $user->setUsername($username);
            $user->setEmail($email);
            $user->setAvatarUrl($avatar);
        }

        $this->em->flush();

        $jwt = null;

        //jwt with only user id
        try {
            $jwt =  $this->jwtManager->createFromPayload($user, ['plan' => $user->getPlan()]);
        } catch (ContainerExceptionInterface $e) {
            $this->logger->error('JWTTokenManagerInterface container error: ' . $e->getMessage());
            return new Response(null, 500);
        }

        $refreshToken = $this->refreshTokenManager->create();
        $refreshToken->setUsername($user->getUserIdentifier());
        $refreshToken->setRefreshToken(); 
        $refreshToken->setValid((new \DateTime())->modify('+1 month')); 
        $this->refreshTokenManager->save($refreshToken);

        return $this->json(['token' => $jwt, 'refresh_token' => $refreshToken->getRefreshToken(), 'user' => [
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'avatarUrl' => $user->getAvatarUrl(),
            'plan' => $user->getPlan(),
            'linkedin' => $user->getLinkedin(),
            'leetcode' => $user->getLeetcode(),
            'skills' => $user->getSkills(),
            'projectsCount' => $user->getProjectsCount(),
            'certCount' => $user->getCertCount(),
            'awardsCount' => $user->getAwardsCount(),
            'experienceCount' => $user->getExperienceCount()
        ]], 200);
    }
}
