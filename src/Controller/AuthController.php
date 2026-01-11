<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Cookie;
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
     * Handle Logout
     * Deletes the refresh token from DB and clears the browser cookie.
     */
    #[Route('/logout', name: 'auth_logout', methods: ['POST'])]
    public function logout(Request $request): Response
    {
        $refreshTokenString = $request->cookies->get('refresh_token');

        if ($refreshTokenString) {
            $refreshToken = $this->refreshTokenManager->get($refreshTokenString);
            if ($refreshToken) {
                $this->refreshTokenManager->delete($refreshToken);
            }
        }

        $response = new Response(null, 200);
        $response->headers->clearCookie(
            'refresh_token',
            '/',        // Path
            null,       // Domain
            true,       // Secure
            true,       // HttpOnly
            'none'      // SameSite
        );

        return $response;
    }

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
                'headers' => ['Accept' => 'application/json'],
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
        $isNewUser = false;

        if (!$user) {
            $conn = $this->em->getConnection();
            $sql = "SELECT COUNT(*) AS cnt FROM invites WHERE username = :username";
            $stmt = $conn->prepare($sql);
            $result = $stmt->executeQuery(['username' => $username]);
            $count = $result->fetchAssociative()['cnt'] ?? 0;
            if ($count == 0) {

                try {
                    $client = HttpClient::create();
                    $client->request('POST', 'https://premise.vishok.me/tg/auresadm/inv', [
                        'json' => ['username' => $username]
                    ]);
                } catch (Exception $e) {
                    // Log error if bot is down, but proceed with 403
                }

                return new Response(null, 403);
            }

            $isNewUser = true;
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

        if ($isNewUser) {
            $roles = ['frontend', 'backend', 'fullstack', 'devops', 'mobile', 'aiml', 'product', 'qa', 'designer', 'blockchain'];
            $conn = $this->em->getConnection();

            $sql = "INSERT INTO resumes (user_id, username, role, data_updated_at) VALUES ";
            $params = [
                'uid' => $user->getId(),
                'uname' => $user->getUsername(),
                'now' => (new \DateTime())->format('Y-m-d H:i:s')
            ];
            $values = [];

            foreach ($roles as $i => $role) {
                $values[] = "(:uid, :uname, :r$i, :now)";
                $params["r$i"] = $role;
            }

            $sql .= implode(', ', $values);
            $conn->executeStatement($sql, $params);
        }

        $jwt = null;
        try {
            $jwt = $this->jwtManager->create($user);
        } catch (\Psr\Container\ContainerExceptionInterface $e) {
            $this->logger->error('JWTTokenManagerInterface container error: ' . $e->getMessage());
            return new Response(null, 500);
        }

        $refreshToken = $this->refreshTokenManager->create();
        $refreshToken->setUsername($user->getUserIdentifier());
        $refreshToken->setRefreshToken();
        $validity = (new \DateTime())->modify('+1 month');
        $refreshToken->setValid($validity);
        $this->refreshTokenManager->save($refreshToken);

        $response = $this->json([
            'token' => $jwt,
            'user' => [
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'avatarUrl' => $user->getAvatarUrl(),
                'plan' => $user->getPlan(),
                'linkedin' => $user->getLinkedin(),
                'leetcode' => $user->getLeetcode(),
                'portfolio' => $user->getPortfolio(),
                'skills' => $user->getSkills(),
                'projectsCount' => $user->getProjectsCount(),
                'certCount' => $user->getCertCount(),
                'awardsCount' => $user->getAwardsCount(),
                'experienceCount' => $user->getExperienceCount()
            ]
        ], 200);

        $response->headers->setCookie(new Cookie(
            'refresh_token',                 // Name
            $refreshToken->getRefreshToken(),// Value
            $validity,                       // Expiration
            '/',                             // Path
            null,                            // Domain (null = current domain)
            true,                            // Secure (HTTPS only)
            true,                            // HttpOnly (No JS access)
            false,                           // Raw
            'none'                           // SameSite
        ));

        return $response;
    }

    #[Route('/ping', name: 'app_auth_ping', methods: ['GET'])]
    public function ping(): Response
    {
        return new Response(null, 200);
    }
}