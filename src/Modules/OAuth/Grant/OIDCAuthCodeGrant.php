<?php

declare(strict_types=1);

namespace Foodsharing\Modules\OAuth\Grant;

use DateInterval;
use Foodsharing\Modules\OAuth\OAuthGateway;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class OIDCAuthCodeGrant extends AuthCodeGrant
{
    private ?string $nonce = null;

    public function __construct(
        private readonly OAuthGateway $gateway,
        AuthCodeRepositoryInterface $authCodeRepository,
        RefreshTokenRepositoryInterface $refreshTokenRepository,
        DateInterval $authCodeTTL
    ) {
        parent::__construct($authCodeRepository, $refreshTokenRepository, $authCodeTTL);
    }

    public function validateAuthorizationRequest(ServerRequestInterface $request): AuthorizationRequestInterface
    {
        $this->nonce = $this->getQueryStringParameter('nonce', $request);

        if ($this->nonce === null) {
            $this->nonce = $this->getRequestParameter('nonce', $request);
        }

        return parent::validateAuthorizationRequest($request);
    }

    protected function issueAuthCode(
        DateInterval $authCodeTTL,
        ClientEntityInterface $client,
        string $userIdentifier,
        ?string $redirectUri,
        array $scopes = []
    ): AuthCodeEntityInterface {
        $authCode = parent::issueAuthCode($authCodeTTL, $client, $userIdentifier, $redirectUri, $scopes);

        if ($this->nonce !== null) {
            $this->gateway->setAuthCodeNonce($authCode->getIdentifier(), $this->nonce);
        }

        $this->nonce = null;

        return $authCode;
    }

    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        DateInterval $accessTokenTTL
    ): ResponseTypeInterface {
        $response = parent::respondToAccessTokenRequest($request, $responseType, $accessTokenTTL);

        if (!method_exists($response, 'setNonce')) {
            return $response;
        }

        $nonce = $this->resolveNonceFromRequest($request);

        if ($nonce !== null) {
            $response->setNonce($nonce);
        }

        return $response;
    }

    private function resolveNonceFromRequest(ServerRequestInterface $request): ?string
    {
        $encryptedAuthCode = $this->getRequestParameter('code', $request);

        if ($encryptedAuthCode === null) {
            return null;
        }

        try {
            $payload = json_decode($this->decrypt($encryptedAuthCode));
        } catch (Throwable) {
            return null;
        }

        if (!is_object($payload) || !isset($payload->auth_code_id)) {
            return null;
        }

        return $this->gateway->getAuthCodeNonce((string)$payload->auth_code_id);
    }
}
