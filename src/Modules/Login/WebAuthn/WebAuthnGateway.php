<?php

namespace Foodsharing\Modules\Login\WebAuthn;

use Foodsharing\Modules\Core\BaseGateway;
use Symfony\Component\Uid\Uuid;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\EmptyTrustPath;

/**
 * Gateway for managing WebAuthn credentials.
 *
 * Note: In webauthn-lib 5.0+, PublicKeyCredentialSourceRepository interface was removed.
 * The methods are still implemented but the interface is no longer required.
 */
class WebAuthnGateway extends BaseGateway
{
    use WebAuthnDomainTrait;

    /**
     * Find a credential by its credential ID.
     */
    public function findOneByCredentialId(string $credentialId): ?PublicKeyCredentialSource
    {
        $data = $this->db->fetchByCriteria(
            'fs_webauthn_credentials',
            [
                'id',
                'public_key_credential_id',
                'type',
                'transports',
                'attestation_type',
                'trust_path',
                'aaguid',
                'credential_public_key',
                'user_handle',
                'counter',
                'other_ui',
            ],
            ['public_key_credential_id' => base64_encode($credentialId)]
        );

        if (!$data) {
            return null;
        }

        return $this->rowToPublicKeyCredentialSource($data);
    }

    /**
     * Find all credentials for a user.
     *
     * @return PublicKeyCredentialSource[]
     */
    public function findAllForUserEntity(PublicKeyCredentialUserEntity $userEntity): array
    {
        $userId = (int)$userEntity->id;

        $rows = $this->db->fetchAllByCriteria(
            'fs_webauthn_credentials',
            [
                'id',
                'public_key_credential_id',
                'type',
                'transports',
                'attestation_type',
                'trust_path',
                'aaguid',
                'credential_public_key',
                'user_handle',
                'counter',
                'other_ui',
            ],
            ['user_handle' => $userId]
        );

        return array_map([$this, 'rowToPublicKeyCredentialSource'], $rows);
    }

    /**
     * Save a new credential source.
     */
    public function saveCredentialSource(PublicKeyCredentialSource $credentialSource, ?string $name = null, ?string $rpId = null): void
    {
        // Generate a unique ID (ULID or similar) for the database row
        $id = $this->generateUniqueId();

        // Serialize TrustPath - get the class name for reconstruction
        // For EmptyTrustPath (none attestation), we just need the type
        $trustPathData = [
            'type' => get_class($credentialSource->trustPath),
        ];

        // If the trust path has certificates (not empty), we'd need to handle them
        // For now, we only support EmptyTrustPath (none attestation)

        // Determine RP ID from environment if not provided
        if ($rpId === null) {
            $rpId = $this->getWebAuthnDomain();
        }

        $this->db->insert('fs_webauthn_credentials', [
            'id' => $id,
            'public_key_credential_id' => base64_encode($credentialSource->publicKeyCredentialId),
            'type' => $credentialSource->type,
            'transports' => json_encode($credentialSource->transports),
            'attestation_type' => $credentialSource->attestationType,
            'trust_path' => json_encode($trustPathData),
            'aaguid' => $credentialSource->aaguid->toString(),
            'credential_public_key' => base64_encode($credentialSource->credentialPublicKey),
            'user_handle' => (int)$credentialSource->userHandle,
            'rp_id' => $rpId,
            'counter' => $credentialSource->counter,
            'other_ui' => $credentialSource->otherUI ? json_encode($credentialSource->otherUI) : null,
            'name' => $name,
            'created_at' => $this->db->now(),
        ]);
    }

    /**
     * Update an existing credential source (e.g., counter update).
     */
    public function updateCredentialSource(PublicKeyCredentialSource $credentialSource): void
    {
        $this->db->update(
            'fs_webauthn_credentials',
            [
                'counter' => $credentialSource->counter,
                'last_used_at' => $this->db->now(),
            ],
            ['public_key_credential_id' => base64_encode($credentialSource->publicKeyCredentialId)]
        );
    }

    /**
     * Get all credentials for a user in a simplified format.
     * Includes information about which domain each credential belongs to.
     */
    public function getCredentialsForUser(int $userId): array
    {
        $currentRpId = $this->getWebAuthnDomain();

        $credentials = $this->db->fetchAll('
            SELECT 
                id,
                name,
                created_at,
                last_used_at,
                aaguid,
                rp_id
            FROM fs_webauthn_credentials
            WHERE user_handle = :user_id
            ORDER BY created_at DESC
        ', [':user_id' => $userId]);

        // Add flag to indicate if credential is from current domain
        return array_map(function ($cred) use ($currentRpId) {
            $cred['is_current_domain'] = ($cred['rp_id'] === $currentRpId);

            return $cred;
        }, $credentials);
    }

    /**
     * Delete a credential by ID.
     */
    public function deleteCredential(string $id, int $userId): bool
    {
        return $this->db->delete('fs_webauthn_credentials', [
            'id' => $id,
            'user_handle' => $userId,
        ]) > 0;
    }

    /**
     * Update credential name.
     */
    public function updateCredentialName(string $id, int $userId, string $name): bool
    {
        return $this->db->update(
            'fs_webauthn_credentials',
            ['name' => $name],
            ['id' => $id, 'user_handle' => $userId]
        ) > 0;
    }

    /**
     * Convert database row to PublicKeyCredentialSource.
     */
    private function rowToPublicKeyCredentialSource(array $data): PublicKeyCredentialSource
    {
        $trustPathData = json_decode($data['trust_path'], true);

        // Reconstruct TrustPath based on type
        // For now, we primarily support EmptyTrustPath (none attestation)
        $trustPath = match ($trustPathData['type'] ?? '') {
            'Webauthn\\TrustPath\\EmptyTrustPath' => EmptyTrustPath::create(),
            default => EmptyTrustPath::create(), // Fallback to empty
        };

        return PublicKeyCredentialSource::create(
            base64_decode($data['public_key_credential_id']),
            $data['type'],
            json_decode($data['transports'], true),
            $data['attestation_type'],
            $trustPath,
            Uuid::fromString($data['aaguid']),
            base64_decode($data['credential_public_key']),
            (string)$data['user_handle'],
            (int)$data['counter'],
            $data['other_ui'] ? json_decode($data['other_ui'], true) : null
        );
    }

    /**
     * Generate a unique ID for the credential record.
     * Uses ULID or a similar format.
     */
    private function generateUniqueId(): string
    {
        // Simple ULID-like generation (timestamp + random)
        // In production, use a proper ULID library
        $timestamp = sprintf('%010d', time());
        $random = bin2hex(random_bytes(8));

        return strtoupper($timestamp . $random);
    }
}
