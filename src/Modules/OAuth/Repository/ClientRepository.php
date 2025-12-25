<?php

declare(strict_types=1);

namespace Foodsharing\Modules\OAuth\Repository;

use Foodsharing\Modules\OAuth\Entity\ClientEntity;
use Foodsharing\Modules\OAuth\OAuthGateway;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;

class ClientRepository implements ClientRepositoryInterface
{
    private OAuthGateway $gateway;

    public function __construct(OAuthGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    public function getClientEntity(string $clientIdentifier): ?ClientEntityInterface
    {
        $clientData = $this->gateway->getClient($clientIdentifier);

        if (!$clientData || !$clientData['active']) {
            return null;
        }

        $client = new ClientEntity();
        $client->setIdentifier($clientIdentifier);
        $client->setName($clientData['name']);
        $client->setRedirectUri(json_decode($clientData['redirect_uris'], true));

        if ($clientData['confidential']) {
            $client->setConfidential();
        }

        return $client;
    }

    public function validateClient($clientIdentifier, $clientSecret, $grantType): bool
    {
        return $this->gateway->validateClient($clientIdentifier, $clientSecret);
    }

    /**
     * Get the allowed scopes for a client.
     *
     * @return array Array of allowed scope identifiers
     */
    public function getClientScopes(string $clientIdentifier): array
    {
        $clientData = $this->gateway->getClient($clientIdentifier);

        if (!$clientData) {
            return [];
        }

        $scopes = json_decode($clientData['scopes'] ?? '[]', true);

        return is_array($scopes) ? $scopes : [];
    }
}
