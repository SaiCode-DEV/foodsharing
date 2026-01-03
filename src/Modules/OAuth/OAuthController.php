<?php

declare(strict_types=1);

namespace Foodsharing\Modules\OAuth;

use DateInterval;
use Exception;
use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\OAuth\Entity\UserEntity;
use Foodsharing\Modules\OAuth\Grant\OIDCAuthCodeGrant;
use Foodsharing\Modules\OAuth\Repository\AccessTokenRepository;
use Foodsharing\Modules\OAuth\Repository\AuthCodeRepository;
use Foodsharing\Modules\OAuth\Repository\ClientRepository;
use Foodsharing\Modules\OAuth\Repository\IdentityRepository;
use Foodsharing\Modules\OAuth\Repository\RefreshTokenRepository;
use Foodsharing\Modules\OAuth\Repository\ScopeRepository;
use Foodsharing\Modules\OAuth\Repository\UserRepository;
use Foodsharing\Permissions\OAuthPermissions;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\ResourceServer;
use OpenIDConnectServer\ClaimExtractor;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OAuthController extends FoodsharingController
{
    private AuthorizationServer $server;
    private ResourceServer $resourceServer;
    private OAuthGateway $gateway;
    private ScopeRepository $scopeRepository;
    private Database $db;
    private HttpMessageFactoryInterface $httpMessageFactory;
    private HttpFoundationFactoryInterface $httpFoundationFactory;
    private UrlGeneratorInterface $urlGenerator;
    private OAuthPermissions $permissions;

    public function __construct(
        Database $db,
        HttpMessageFactoryInterface $httpMessageFactory,
        HttpFoundationFactoryInterface $httpFoundationFactory,
        UrlGeneratorInterface $urlGenerator,
        OAuthPermissions $permissions,
    ) {
        parent::__construct();

        $this->db = $db;
        $this->httpMessageFactory = $httpMessageFactory;
        $this->httpFoundationFactory = $httpFoundationFactory;
        $this->urlGenerator = $urlGenerator;
        $this->permissions = $permissions;
        $this->gateway = new OAuthGateway($db);

        // Initialize repositories
        $clientRepository = new ClientRepository($this->gateway);
        $this->scopeRepository = new ScopeRepository($clientRepository);
        $accessTokenRepository = new AccessTokenRepository($this->gateway);
        $authCodeRepository = new AuthCodeRepository($this->gateway);
        $refreshTokenRepository = new RefreshTokenRepository($this->gateway);
        $userRepository = new UserRepository();
        $identityRepository = new IdentityRepository($db);

        // Setup encryption key
        $encryptionKey = $this->getEncryptionKey();

        // Setup OpenID Connect response with ClaimExtractor
        $claimExtractor = new ClaimExtractor();
        $responseType = new OIDCBearerTokenResponse($identityRepository, $claimExtractor);

        // Setup private key with optional passphrase
        if (!file_exists(OAUTH_PRIVATE_KEY_PATH)) {
            throw new Exception('OAuth private key file not found');
        }

        $privateKey = defined('OAUTH_PRIVATE_KEY_PASS') && OAUTH_PRIVATE_KEY_PASS
            ? new CryptKey('file://' . OAUTH_PRIVATE_KEY_PATH, OAUTH_PRIVATE_KEY_PASS)
            : 'file://' . OAUTH_PRIVATE_KEY_PATH;

        // Setup the authorization server
        $this->server = new AuthorizationServer(
            $clientRepository,
            $accessTokenRepository,
            $this->scopeRepository,
            $privateKey,
            $encryptionKey,
            $responseType
        );

        // Enable the authorization code grant with 10-minute auth code TTL and 1-hour access token TTL
        $grant = new OIDCAuthCodeGrant(
            $this->gateway,
            $authCodeRepository,
            $refreshTokenRepository,
            new DateInterval('PT10M')
        );
        $grant->setRefreshTokenTTL(new DateInterval('P1M'));

        $this->server->enableGrantType(
            $grant,
            new DateInterval('PT1H')
        );

        // Enable the refresh token grant
        $refreshTokenGrant = new RefreshTokenGrant($refreshTokenRepository);
        $refreshTokenGrant->setRefreshTokenTTL(new DateInterval('P1M'));
        $this->server->enableGrantType(
            $refreshTokenGrant,
            new DateInterval('PT1H')
        );

        // Setup resource server for validating access tokens
        $this->resourceServer = new ResourceServer(
            $accessTokenRepository,
            'file://' . OAUTH_PUBLIC_KEY_PATH
        );
    }

    #[Route('/oauth/authorize', name: 'oauth_authorize', methods: ['GET', 'POST'])]
    public function authorize(Request $request): Response
    {
        // User must be logged in
        if (!$this->session->mayRole()) {
            $this->session->set('login_redirect', $request->getUri());

            return $this->redirectToRoute('login');
        }

        try {
            // Convert Symfony request to PSR-7
            $psrRequest = $this->httpMessageFactory->createRequest($request);

            // Validate the HTTP request and return an AuthorizationRequest object
            $authRequest = $this->server->validateAuthorizationRequest($psrRequest);
            $userId = $this->session->id();
            $clientId = $authRequest->getClient()->getIdentifier();
            $requestedScopes = array_map(fn ($s) => $s->getIdentifier(), $authRequest->getScopes());

            // Validate that client is only requesting scopes it's authorized for
            $client = $this->gateway->getClient($clientId);
            if ($client) {
                $allowedScopes = !empty($client['scopes']) ? json_decode($client['scopes'], true) : [];
                $unauthorizedScopes = array_diff($requestedScopes, $allowedScopes);

                if (!empty($unauthorizedScopes)) {
                    throw OAuthServerException::invalidScope(implode(', ', $unauthorizedScopes), $authRequest->getRedirectUri());
                }
            }

            // Check region requirements
            $client = $this->gateway->getClient($clientId);
            if ($client && !$this->gateway->userHasRequiredRegions($userId, $client['required_region_ids'] ?? null)) {
                // User doesn't have required region membership - show error
                throw new Exception('oauth.error.region_requirement_not_met');
            }

            // Check for POST submission (user decision)
            if ($request->isMethod('POST')) {
                $decision = $request->request->get('decision');
                $remember = $request->request->get('remember') === '1';

                if ($decision === 'approve') {
                    // User approved
                    $userEntity = new UserEntity();
                    $userEntity->setIdentifier((string)$userId);
                    $authRequest->setUser($userEntity);
                    $authRequest->setAuthorizationApproved(true);

                    // Save consent if user wants to remember
                    if ($remember) {
                        $this->gateway->saveUserConsent($userId, $clientId, $requestedScopes);
                    }

                    // Convert PSR-7 response back to Symfony
                    $psrResponse = $this->server->completeAuthorizationRequest($authRequest, new \Laminas\Diactoros\Response());

                    return $this->httpFoundationFactory->createResponse($psrResponse);
                } else {
                    // User denied
                    $authRequest->setAuthorizationApproved(false);
                    $psrResponse = $this->server->completeAuthorizationRequest($authRequest, new \Laminas\Diactoros\Response());

                    return $this->httpFoundationFactory->createResponse($psrResponse);
                }
            }

            // Check if user has previously consented
            $consent = $this->gateway->getUserConsent($userId, $clientId);
            if ($consent) {
                $consentedScopes = json_decode($consent['scopes'], true);
                // Auto-approve if all requested scopes were previously consented
                if (empty(array_diff($requestedScopes, $consentedScopes))) {
                    $userEntity = new UserEntity();
                    $userEntity->setIdentifier((string)$userId);
                    $authRequest->setUser($userEntity);
                    $authRequest->setAuthorizationApproved(true);
                    $psrResponse = $this->server->completeAuthorizationRequest($authRequest, new \Laminas\Diactoros\Response());

                    return $this->httpFoundationFactory->createResponse($psrResponse);
                }
            }

            // Show authorization page
            $client = $authRequest->getClient();
            $scopes = $authRequest->getScopes();

            $redirectUri = $authRequest->getRedirectUri();

            $props = [
                'clientName' => $client->getName(),
                'clientIdentifier' => $client->getIdentifier(),
                'scopes' => array_map(fn ($s) => $s->getIdentifier(), $scopes),
                'actionUrl' => $this->urlGenerator->generate('oauth_authorize', [], UrlGeneratorInterface::ABSOLUTE_PATH) . '?' . http_build_query($request->query->all()),
                'redirectUri' => $redirectUri,
                'csrfToken' => $this->session->get('csrf_token') ?? ''
            ];
        } catch (OAuthServerException $exception) {
            $props = ['error' => $exception->getMessage(), 'hint' => $exception->getHint()];
        } catch (Exception $exception) {
            $props = ['error' => $exception->getMessage()];
        }

        if (isset($authRequest) && $authRequest->getClient()->getName()) {
            $props['clientName'] = $authRequest->getClient()->getName();
        }

        if (isset($props['error']) && $props['error'] === 'Client authentication failed') {
            $props['error'] = 'oauth.error.invalid_client';
        }
        if (isset($props['error']) && $props['error'] === 'The requested scope is invalid, unknown, or malformed') {
            $props['error'] = 'oauth.error.invalid_scope';
        }

        $this->pageHelper->addTitle($this->translator->trans('oauth.title'));
        $vue = $this->prepareVueComponent('oauth-authorize', 'OAuthAuthorize', $props);
        $this->pageHelper->addContent($vue);

        return $this->renderGlobal();
    }

    #[Route('/oauth/token', name: 'oauth_token', methods: ['POST'])]
    public function accessToken(Request $request): Response
    {
        try {
            // Convert Symfony request to PSR-7
            $psrRequest = $this->httpMessageFactory->createRequest($request);
            $psrResponse = $this->server->respondToAccessTokenRequest($psrRequest, new \Laminas\Diactoros\Response());

            return $this->httpFoundationFactory->createResponse($psrResponse);
        } catch (OAuthServerException $exception) {
            $psrResponse = $exception->generateHttpResponse(new \Laminas\Diactoros\Response());

            return $this->httpFoundationFactory->createResponse($psrResponse);
        } catch (Exception $exception) {
            return new Response($exception->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/oauth/userinfo', name: 'oauth_userinfo', methods: ['GET', 'POST'])]
    public function userinfo(Request $request): Response
    {
        try {
            // Convert Symfony request to PSR-7
            $psrRequest = $this->httpMessageFactory->createRequest($request);

            // Validate the access token
            $psrRequest = $this->resourceServer->validateAuthenticatedRequest($psrRequest);

            $userId = $psrRequest->getAttribute('oauth_user_id');
            $scopes = $psrRequest->getAttribute('oauth_scopes', []);

            if (!$userId) {
                return new JsonResponse(['error' => 'invalid_token'], Response::HTTP_UNAUTHORIZED);
            }

            // Get user data
            $user = $this->db->fetchByCriteria('fs_foodsaver', [
                'id', 'name', 'nachname', 'email', 'photo', 'geschlecht'
            ], ['id' => $userId]);

            if (!$user) {
                return new JsonResponse(['error' => 'user_not_found'], Response::HTTP_NOT_FOUND);
            }

            $userinfo = [
                'sub' => (string)$userId,
                'preferred_username' => $user['name'] . (string)$userId,
            ];

            // Add profile claims if profile scope is present
            if (in_array('profile', $scopes)) {
                $userinfo['name'] = trim(($user['name'] ?? '') . ' ' . ($user['nachname'] ?? ''));
                $userinfo['given_name'] = $user['name'] ?? '';
                $userinfo['family_name'] = $user['nachname'] ?? '';
                $userinfo['locale'] = 'de-DE';

                if (!empty($user['photo'])) {
                    $userinfo['picture'] = BASE_URL . '/images/profile/' . $user['photo'];
                }
            }

            // Add email claims if email scope is present
            if (in_array('email', $scopes)) {
                $userinfo['email'] = $user['email'] ?? '';
                $userinfo['email_verified'] = true; // foodsharing requires verified emails
            }

            // Add regions if regions scope is present
            if (in_array('regions', $scopes)) {
                $regions = $this->db->fetchAll(
                    'SELECT b.id FROM fs_foodsaver_has_bezirk fb 
                     JOIN fs_bezirk b ON fb.bezirk_id = b.id 
                     WHERE fb.foodsaver_id = :userId AND fb.active = 1',
                    [':userId' => $userId]
                );
                $userinfo['region_ids'] = array_map(fn ($r) => (string)$r['id'], $regions);

                // Add regions where user is ambassador
                $ambassadorRegions = $this->db->fetchAll(
                    'SELECT bezirk_id FROM fs_botschafter 
                     WHERE foodsaver_id = :userId',
                    [':userId' => $userId]
                );
                $userinfo['region_ambassador'] = array_map(fn ($r) => (string)$r['bezirk_id'], $ambassadorRegions);
            }

            return new JsonResponse($userinfo);
        } catch (OAuthServerException $exception) {
            $psrResponse = $exception->generateHttpResponse(new \Laminas\Diactoros\Response());

            return $this->httpFoundationFactory->createResponse($psrResponse);
        } catch (Exception $exception) {
            return new JsonResponse(['error' => 'server_error', 'message' => $exception->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/.well-known/openid-configuration', name: 'oauth_openid_configuration', methods: ['GET'])]
    public function openidConfiguration(Request $request): JsonResponse
    {
        $baseUrl = $request->getSchemeAndHttpHost();

        $config = [
            'issuer' => $baseUrl,
            'authorization_endpoint' => $baseUrl . $this->urlGenerator->generate('oauth_authorize', [], UrlGeneratorInterface::ABSOLUTE_PATH),
            'token_endpoint' => $baseUrl . $this->urlGenerator->generate('oauth_token', [], UrlGeneratorInterface::ABSOLUTE_PATH),
            'userinfo_endpoint' => $baseUrl . $this->urlGenerator->generate('oauth_userinfo', [], UrlGeneratorInterface::ABSOLUTE_PATH),
            'jwks_uri' => $baseUrl . $this->urlGenerator->generate('oauth_jwks', [], UrlGeneratorInterface::ABSOLUTE_PATH),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'scopes_supported' => ['openid', 'profile', 'email', 'regions'],
            'token_endpoint_auth_methods_supported' => ['client_secret_post', 'client_secret_basic'],
            'claims_supported' => [
                'sub',
                'name',
                'given_name',
                'family_name',
                'email',
                'email_verified',
                'picture',
                'locale',
                'regions'
            ],
            'code_challenge_methods_supported' => ['plain', 'S256']
        ];

        return new JsonResponse($config);
    }

    #[Route('/.well-known/jwks.json', name: 'oauth_jwks', methods: ['GET'])]
    public function jwks(): JsonResponse
    {
        $publicKey = file_get_contents(OAUTH_PUBLIC_KEY_PATH);

        // Parse the public key to extract modulus and exponent
        $keyResource = openssl_pkey_get_public($publicKey);
        $keyDetails = openssl_pkey_get_details($keyResource);

        $jwks = [
            'keys' => [
                [
                    'kty' => 'RSA',
                    'use' => 'sig',
                    'alg' => 'RS256',
                    'n' => rtrim(str_replace(['+', '/'], ['-', '_'], base64_encode($keyDetails['rsa']['n'])), '='),
                    'e' => rtrim(str_replace(['+', '/'], ['-', '_'], base64_encode($keyDetails['rsa']['e'])), '='),
                ]
            ]
        ];

        return new JsonResponse($jwks);
    }

    #[Route('/admin/oauthclients', name: 'oauth_clients_admin')]
    public function index(): Response
    {
        if (!$this->permissions->mayAdministrateOAuthClients()) {
            return $this->redirect('/dashboard');
        }

        $this->pageHelper->addContent(
            $this->prepareVueComponent('oauth-clients-admin', 'OAuthClientsAdmin')
        );

        return $this->renderGlobal();
    }

    private function getEncryptionKey(): string
    {
        $keyPath = OAUTH_ENCRYPTION_KEY_PATH;

        // Generate encryption key if it doesn't exist
        if (!file_exists($keyPath)) {
            $key = base64_encode(random_bytes(32));
            // Set umask to ensure file is created with secure permissions (0600) atomically
            $oldUmask = umask(0077);
            file_put_contents($keyPath, $key);
            umask($oldUmask);

            return $key;
        }

        return file_get_contents($keyPath);
    }
}
