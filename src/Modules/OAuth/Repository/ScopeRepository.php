<?php

declare(strict_types=1);

namespace Foodsharing\Modules\OAuth\Repository;

use Foodsharing\Modules\OAuth\Entity\ScopeEntity;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;

class ScopeRepository implements ScopeRepositoryInterface
{
    private ClientRepository $clientRepository;

    public function __construct(ClientRepository $clientRepository)
    {
        $this->clientRepository = $clientRepository;
    }
    private const SCOPES = [
        'openid' => 'OpenID Connect support',
        'profile' => 'User profile information',
        'email' => 'User email address',
        'regions' => 'List of regions the user is associated with',
    ];

    public function getScopeEntityByIdentifier($scopeIdentifier): ?ScopeEntityInterface
    {
        if (!array_key_exists($scopeIdentifier, self::SCOPES)) {
            return null;
        }

        $scope = new ScopeEntity();
        $scope->setIdentifier($scopeIdentifier);

        return $scope;
    }

    public function finalizeScopes(
        array $scopes,
        $grantType,
        ClientEntityInterface $clientEntity,
        $userIdentifier = null,
        $authCodeId = null
    ): array {
        // Get the client's allowed scopes from storage
        $allowedScopes = $this->clientRepository->getClientScopes($clientEntity->getIdentifier());

        // If no scopes configured for client, deny all scopes
        if (empty($allowedScopes)) {
            return [];
        }

        // Intersect requested scopes with client's allowed scopes
        $finalizedScopes = [];
        foreach ($scopes as $scope) {
            if ($scope instanceof ScopeEntityInterface) {
                $scopeIdentifier = $scope->getIdentifier();
                // Only include scopes that the client is permitted to access
                if (in_array($scopeIdentifier, $allowedScopes, true)) {
                    $finalizedScopes[] = $scope;
                }
            }
        }

        return $finalizedScopes;
    }

    public static function getScopeDescription(string $identifier): ?string
    {
        return self::SCOPES[$identifier] ?? null;
    }

    public static function getScopeClaims(string $identifier): array
    {
        return match ($identifier) {
            'profile' => ['name', 'given_name', 'family_name', 'picture', 'locale'],
            'email' => ['email', 'email_verified'],
            'regions' => ['regions'],
            default => []
        };
    }
}
