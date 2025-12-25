<?php

declare(strict_types=1);

namespace Foodsharing\Modules\OAuth\Entity;

use League\OAuth2\Server\Entities\UserEntityInterface;
use OpenIDConnectServer\Entities\ClaimSetInterface;

class UserEntity implements UserEntityInterface, ClaimSetInterface
{
    private string $identifier;
    private array $claims = [];

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setClaims(array $claims): void
    {
        $this->claims = $claims;
    }

    public function getClaims(): array
    {
        return $this->claims;
    }
}
