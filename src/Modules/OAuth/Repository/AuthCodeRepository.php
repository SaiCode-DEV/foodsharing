<?php

declare(strict_types=1);

namespace Foodsharing\Modules\OAuth\Repository;

use Foodsharing\Modules\OAuth\Entity\AuthCodeEntity;
use Foodsharing\Modules\OAuth\OAuthGateway;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;

class AuthCodeRepository implements AuthCodeRepositoryInterface
{
    private OAuthGateway $gateway;

    public function __construct(OAuthGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        $scopes = array_map(fn ($scope) => $scope->getIdentifier(), $authCodeEntity->getScopes());

        $nonce = null;
        if (method_exists($authCodeEntity, 'getNonce')) {
            $nonce = $authCodeEntity->getNonce();
        }

        $this->gateway->persistAuthCode(
            $authCodeEntity->getIdentifier(),
            $authCodeEntity->getClient()->getIdentifier(),
            $authCodeEntity->getUserIdentifier(),
            $scopes,
            $authCodeEntity->getExpiryDateTime(),
            $authCodeEntity->getRedirectUri(),
            $nonce
        );
    }

    public function revokeAuthCode($codeId): void
    {
        $this->gateway->revokeAuthCode($codeId);
    }

    public function isAuthCodeRevoked($codeId): bool
    {
        return $this->gateway->isAuthCodeRevoked($codeId);
    }

    public function getNewAuthCode(): AuthCodeEntityInterface
    {
        return new AuthCodeEntity();
    }

    public function getAuthCodeEntity(string $codeId): ?AuthCodeEntityInterface
    {
        $authCodeData = $this->gateway->getAuthCode($codeId);

        if (!$authCodeData) {
            return null;
        }

        $authCode = new AuthCodeEntity();
        $authCode->setIdentifier($authCodeData['identifier']);

        if (isset($authCodeData['nonce'])) {
            $authCode->setNonce($authCodeData['nonce']);
        }

        return $authCode;
    }
}
