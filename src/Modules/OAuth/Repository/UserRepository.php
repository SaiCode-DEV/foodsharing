<?php

declare(strict_types=1);

namespace Foodsharing\Modules\OAuth\Repository;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{
    public function getUserEntityByUserCredentials(
        $username,
        $password,
        $grantType,
        ClientEntityInterface $clientEntity
    ): ?UserEntityInterface {
        // For OAuth2, we don't use password grant - users log in via the web interface
        // This method is only needed for password grant type which we don't support
        return null;
    }
}
