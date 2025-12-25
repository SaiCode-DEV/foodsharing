<?php

declare(strict_types=1);

namespace Foodsharing\Modules\OAuth;

use DateTimeInterface;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;

class OAuthGateway extends BaseGateway
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function getDatabase(): Database
    {
        return $this->db;
    }

    // Client methods
    public function getClient(string $identifier): ?array
    {
        return $this->db->fetchByCriteria('oauth_clients', [
            'identifier', 'name', 'confidential', 'active', 'redirect_uris', 'scopes', 'grant_types', 'secret_hash', 'required_region_ids', 'changed_by', 'created_at', 'updated_at'
        ], ['identifier' => $identifier]);
    }

    public function getAllClients(): array
    {
        return $this->db->fetchAll('
            SELECT identifier, name, confidential, active, redirect_uris, scopes, grant_types, required_region_ids, changed_by, created_at, updated_at
            FROM oauth_clients
            ORDER BY created_at DESC
        ');
    }

    public function createClient(string $identifier, string $name, bool $confidential, array $redirectUris, array $scopes, array $grantTypes, ?string $secretHash = null, ?array $requiredRegionIds = null, int $changedBy = 0): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->insert('oauth_clients', [
            'identifier' => $identifier,
            'name' => $name,
            'confidential' => $confidential,
            'active' => true,
            'redirect_uris' => json_encode($redirectUris),
            'scopes' => json_encode($scopes),
            'grant_types' => json_encode($grantTypes),
            'secret_hash' => $secretHash,
            'required_region_ids' => $requiredRegionIds ? json_encode($requiredRegionIds) : null,
            'changed_by' => $changedBy,
            'created_at' => $now,
            'updated_at' => $now
        ]);
    }

    public function updateClient(string $identifier, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');

        // Encode arrays
        if (isset($data['redirect_uris']) && is_array($data['redirect_uris'])) {
            $data['redirect_uris'] = json_encode($data['redirect_uris']);
        }
        if (isset($data['scopes']) && is_array($data['scopes'])) {
            $data['scopes'] = json_encode($data['scopes']);
        }
        if (isset($data['grant_types']) && is_array($data['grant_types'])) {
            $data['grant_types'] = json_encode($data['grant_types']);
        }
        if (isset($data['required_region_ids'])) {
            $data['required_region_ids'] = is_array($data['required_region_ids']) && !empty($data['required_region_ids'])
                ? json_encode($data['required_region_ids'])
                : null;
        }

        $this->db->update('oauth_clients', $data, ['identifier' => $identifier]);
    }

    public function deleteClient(string $identifier): void
    {
        // Delete related data
        $this->db->delete('oauth_user_consents', ['client_identifier' => $identifier]);
        $this->db->delete('oauth_auth_codes', ['client_identifier' => $identifier]);
        $this->db->delete('oauth_access_tokens', ['client_identifier' => $identifier]);

        // Delete client
        $this->db->delete('oauth_clients', ['identifier' => $identifier]);
    }

    public function userHasRequiredRegions(int $userId, ?string $requiredRegionIdsJson): bool
    {
        if (!$requiredRegionIdsJson) {
            return true; // No restrictions
        }

        $requiredRegionIds = json_decode($requiredRegionIdsJson, true);
        if (empty($requiredRegionIds)) {
            return true;
        }

        // Get user's region IDs
        $userRegions = $this->db->fetchAllValues('
            SELECT bezirk_id 
            FROM fs_foodsaver_has_bezirk 
            WHERE foodsaver_id = :userId
        ', [':userId' => $userId]);

        // Check if user is in at least one of the required regions
        return !empty(array_intersect($requiredRegionIds, $userRegions));
    }

    public function validateClient(string $identifier, ?string $secret): bool
    {
        $client = $this->getClient($identifier);
        if (!$client || !$client['active']) {
            return false;
        }

        if ($client['confidential'] && $secret !== null) {
            return password_verify($secret, $client['secret_hash']);
        }

        return !$client['confidential'];
    }

    // Access Token methods
    public function persistAccessToken(string $identifier, string $clientIdentifier, ?string $userIdentifier, array $scopes, DateTimeInterface $expiresAt): void
    {
        $this->db->insert('oauth_access_tokens', [
            'identifier' => $identifier,
            'client_identifier' => $clientIdentifier,
            'user_identifier' => $userIdentifier,
            'scopes' => json_encode($scopes),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'revoked' => false,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function revokeAccessToken(string $tokenId): void
    {
        $this->db->update('oauth_access_tokens', ['revoked' => true], ['identifier' => $tokenId]);
    }

    public function isAccessTokenRevoked(string $tokenId): bool
    {
        $token = $this->db->fetchByCriteria('oauth_access_tokens', ['revoked'], ['identifier' => $tokenId]);

        return $token ? (bool)$token['revoked'] : true;
    }

    // Refresh Token methods
    public function persistRefreshToken(string $identifier, string $accessTokenIdentifier, DateTimeInterface $expiresAt): void
    {
        $this->db->insert('oauth_refresh_tokens', [
            'identifier' => $identifier,
            'access_token_identifier' => $accessTokenIdentifier,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'revoked' => false,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function revokeRefreshToken(string $tokenId): void
    {
        $this->db->update('oauth_refresh_tokens', ['revoked' => true], ['identifier' => $tokenId]);
    }

    public function isRefreshTokenRevoked(string $tokenId): bool
    {
        $token = $this->db->fetchByCriteria('oauth_refresh_tokens', ['revoked'], ['identifier' => $tokenId]);

        return $token ? (bool)$token['revoked'] : true;
    }

    // Auth Code methods
    public function persistAuthCode(string $identifier, string $clientIdentifier, ?string $userIdentifier, array $scopes, DateTimeInterface $expiresAt, string $redirectUri, ?string $nonce = null): void
    {
        $this->db->insert('oauth_auth_codes', [
            'identifier' => $identifier,
            'client_identifier' => $clientIdentifier,
            'user_identifier' => $userIdentifier,
            'scopes' => json_encode($scopes),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'redirect_uri' => $redirectUri,
            'nonce' => $nonce,
            'revoked' => false,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function revokeAuthCode(string $codeId): void
    {
        $this->db->update('oauth_auth_codes', ['revoked' => true], ['identifier' => $codeId]);
    }

    public function isAuthCodeRevoked(string $codeId): bool
    {
        $code = $this->db->fetchByCriteria('oauth_auth_codes', ['revoked'], ['identifier' => $codeId]);

        return $code ? (bool)$code['revoked'] : true;
    }

    public function setAuthCodeNonce(string $codeId, ?string $nonce): void
    {
        $this->db->update('oauth_auth_codes', ['nonce' => $nonce], ['identifier' => $codeId]);
    }

    public function getAuthCodeNonce(string $codeId): ?string
    {
        $code = $this->db->fetchByCriteria('oauth_auth_codes', ['nonce'], ['identifier' => $codeId]);

        return $code['nonce'] ?? null;
    }

    public function getAuthCode(string $codeId): ?array
    {
        return $this->db->fetchByCriteria('oauth_auth_codes', [
            'identifier', 'client_identifier', 'user_identifier', 'scopes', 'expires_at', 'redirect_uri', 'revoked', 'nonce'
        ], ['identifier' => $codeId]);
    }

    // User consent methods
    public function getUserConsent(int $userId, string $clientIdentifier): ?array
    {
        return $this->db->fetchByCriteria('oauth_user_consents', [
            'user_id', 'client_identifier', 'scopes', 'created_at', 'updated_at'
        ], ['user_id' => $userId, 'client_identifier' => $clientIdentifier]);
    }

    public function saveUserConsent(int $userId, string $clientIdentifier, array $scopes): void
    {
        $existing = $this->getUserConsent($userId, $clientIdentifier);
        $now = date('Y-m-d H:i:s');

        if ($existing) {
            $this->db->update('oauth_user_consents', [
                'scopes' => json_encode($scopes),
                'updated_at' => $now
            ], ['user_id' => $userId, 'client_identifier' => $clientIdentifier]);
        } else {
            $this->db->insert('oauth_user_consents', [
                'user_id' => $userId,
                'client_identifier' => $clientIdentifier,
                'scopes' => json_encode($scopes),
                'created_at' => $now,
                'updated_at' => $now
            ]);
        }
    }

    public function revokeUserConsent(int $userId, string $clientIdentifier): void
    {
        $this->db->delete('oauth_user_consents', ['user_id' => $userId, 'client_identifier' => $clientIdentifier]);
    }
}
