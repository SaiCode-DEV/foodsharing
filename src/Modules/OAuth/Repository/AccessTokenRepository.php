<?php

declare(strict_types=1);

namespace Foodsharing\Modules\OAuth\Repository;

use Foodsharing\Modules\OAuth\Entity\AccessTokenEntity;
use Foodsharing\Modules\OAuth\OAuthGateway;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;

class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    private OAuthGateway $gateway;

    public function __construct(OAuthGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        $scopes = array_map(fn ($scope) => $scope->getIdentifier(), $accessTokenEntity->getScopes());

        $this->gateway->persistAccessToken(
            $accessTokenEntity->getIdentifier(),
            $accessTokenEntity->getClient()->getIdentifier(),
            $accessTokenEntity->getUserIdentifier(),
            $scopes,
            $accessTokenEntity->getExpiryDateTime()
        );
    }

    public function revokeAccessToken($tokenId): void
    {
        $this->gateway->revokeAccessToken($tokenId);
    }

    public function isAccessTokenRevoked($tokenId): bool
    {
        return $this->gateway->isAccessTokenRevoked($tokenId);
    }

    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, $userIdentifier = null): AccessTokenEntityInterface
    {
        $accessToken = new AccessTokenEntity();
        $accessToken->setClient($clientEntity);

        foreach ($scopes as $scope) {
            $accessToken->addScope($scope);
        }

        if ($userIdentifier !== null) {
            $accessToken->setUserIdentifier((string)$userIdentifier);
        }

        return $accessToken;
    }
}
