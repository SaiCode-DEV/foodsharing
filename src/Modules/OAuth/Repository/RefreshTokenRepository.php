<?php

declare(strict_types=1);

namespace Foodsharing\Modules\OAuth\Repository;

use Foodsharing\Modules\OAuth\Entity\RefreshTokenEntity;
use Foodsharing\Modules\OAuth\OAuthGateway;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;

class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    private OAuthGateway $gateway;

    public function __construct(OAuthGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        $this->gateway->persistRefreshToken(
            $refreshTokenEntity->getIdentifier(),
            $refreshTokenEntity->getAccessToken()->getIdentifier(),
            $refreshTokenEntity->getExpiryDateTime()
        );
    }

    public function revokeRefreshToken($tokenId): void
    {
        $this->gateway->revokeRefreshToken($tokenId);
    }

    public function isRefreshTokenRevoked($tokenId): bool
    {
        return $this->gateway->isRefreshTokenRevoked($tokenId);
    }

    public function getNewRefreshToken(): ?RefreshTokenEntityInterface
    {
        return new RefreshTokenEntity();
    }
}
