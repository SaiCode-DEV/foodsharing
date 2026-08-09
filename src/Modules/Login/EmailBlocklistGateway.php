<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Login;

use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Login\DTO\EmailBlocklistEntry;

class EmailBlocklistGateway extends BaseGateway
{
    /**
     * Get all blocklist entries.
     * @return array<EmailBlocklistEntry>
     */
    public function getAllEntries(): array
    {
        $entries = $this->db->fetchAll('
            SELECT
                id,
                email,
                reason,
                active,
                created_at,
                created_by,
                updated_at,
                updated_by
            FROM fs_email_blacklist
            ORDER BY created_at DESC
        ');

        return array_map(fn ($entry) => $this->normalizeEntry($entry), $entries);
    }

    /**
     * Get a single blocklist entry by ID.
     */
    public function getEntry(int $id): ?EmailBlocklistEntry
    {
        $entry = $this->db->fetchByCriteria(
            'fs_email_blacklist',
            [
                'id',
                'email',
                'reason',
                'active',
                'created_at',
                'created_by',
                'updated_at',
                'updated_by',
            ],
            ['id' => $id]
        );

        return $entry ? $this->normalizeEntry($entry) : null;
    }

    /**
     * Create a new blocklist entry.
     */
    public function createEntry(string $emailPattern, ?string $description, bool $active, int $createdBy): int
    {
        return $this->db->insert('fs_email_blacklist', [
            'email' => $emailPattern,
            'reason' => $description,
            'active' => $active ? 1 : 0,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Update an existing blocklist entry.
     */
    public function updateEntry(int $id, array $data): void
    {
        if (isset($data['active'])) {
            $data['active'] = $data['active'] ? 1 : 0;
        }
        $data['updated_at'] = $this->db->now();

        $this->db->update('fs_email_blacklist', $data, ['id' => $id]);
    }

    /**
     * Delete a blocklist entry by ID.
     */
    public function deleteEntry(int $id): void
    {
        $this->db->delete('fs_email_blacklist', ['id' => $id]);
    }

    /**
     * Get all active blocklist patterns.
     * This method is used by EmailBlocklistTransactions for caching.
     *
     * @return string[] Array of email patterns
     */
    public function getActivePatterns(): array
    {
        return $this->db->fetchAllValuesByCriteria('fs_email_blacklist', 'email', ['active' => 1]);
    }

    /**
     * Check if email pattern already exists.
     */
    public function patternExists(string $emailPattern): bool
    {
        return $this->db->exists('fs_email_blacklist', ['email' => $emailPattern]);
    }

    /**
     * Create a blocklist entry for a deleted user account.
     * Constructs a descriptive reason from the deletion reason.
     *
     * @return int|null The ID of the created entry, or null if the email already exists in the blocklist
     */
    public function createEntryFromAccountDeletion(string $email, ?string $deletionReason, int $adminId): ?int
    {
        if ($this->patternExists($email)) {
            return null;
        }

        $description = !empty($deletionReason)
            ? 'Account deletion: ' . $deletionReason
            : 'Account deletion by admin';

        return $this->createEntry($email, $description, true, $adminId);
    }

    /**
     * Normalize entry from database format (snake_case) to API format (camelCase).
     */
    private function normalizeEntry(array $entry): EmailBlocklistEntry
    {
        return new EmailBlocklistEntry(
            id: (int)$entry['id'],
            email: $entry['email'],
            reason: $entry['reason'] ?? null,
            isActive: (bool)$entry['active'],
            createdAt: $entry['created_at'],
            createdBy: isset($entry['created_by']) ? (int)$entry['created_by'] : null,
            updatedAt: $entry['updated_at'] ?? null,
            updatedBy: isset($entry['updated_by']) ? (int)$entry['updated_by'] : null,
        );
    }
}
