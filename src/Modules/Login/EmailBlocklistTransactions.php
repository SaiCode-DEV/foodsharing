<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Login;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class EmailBlocklistTransactions
{
    private const string CACHE_KEY = 'email_blocklist_patterns';
    private const int CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private readonly EmailBlocklistGateway $emailBlocklistGateway,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Check if an email matches any active blocklist pattern.
     * Uses Symfony cache with 1-hour TTL.
     */
    public function isEmailBlocked(string $email): bool
    {
        $email = strtolower(trim($email));

        // Get patterns from cache (or fetch from DB if not cached)
        $patterns = $this->cache->get(self::CACHE_KEY, function (ItemInterface $cacheItem) {
            $cacheItem->expiresAfter(self::CACHE_TTL);

            return $this->emailBlocklistGateway->getActivePatterns();
        });

        foreach ($patterns as $pattern) {
            if ($this->emailMatchesPattern($email, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a new blocklist entry and invalidate cache.
     */
    public function createEntry(string $emailPattern, ?string $reason, bool $active, int $createdBy): int
    {
        $id = $this->emailBlocklistGateway->createEntry($emailPattern, $reason, $active, $createdBy);
        $this->invalidateCache();

        return $id;
    }

    /**
     * Update an existing blocklist entry and invalidate cache.
     */
    public function updateEntry(int $id, array $data): void
    {
        $this->emailBlocklistGateway->updateEntry($id, $data);
        $this->invalidateCache();
    }

    /**
     * Delete a blocklist entry and invalidate cache.
     */
    public function deleteEntry(int $id): void
    {
        $this->emailBlocklistGateway->deleteEntry($id);
        $this->invalidateCache();
    }

    /**
     * Create a blocklist entry for a deleted user account and invalidate cache if created.
     */
    public function createEntryFromAccountDeletion(string $email, ?string $deletionReason, int $adminId): ?int
    {
        $id = $this->emailBlocklistGateway->createEntryFromAccountDeletion($email, $deletionReason, $adminId);
        if ($id !== null) {
            $this->invalidateCache();
        }

        return $id;
    }

    /**
     * Invalidate the cached blocklist patterns.
     */
    private function invalidateCache(): void
    {
        $this->cache->delete(self::CACHE_KEY);
    }

    /**
     * Match email against a pattern.
     * Supports wildcards: *@example.com, user@*, *@*.example.com, etc.
     * Also supports legacy domain-only patterns (e.g., "bad.com" blocks "user@bad.com").
     * Normalizes emails by removing + aliases (e.g., "user+tag@example.com" becomes "user@example.com").
     */
    private function emailMatchesPattern(string $email, string $pattern): bool
    {
        $pattern = strtolower(trim($pattern));
        $email = $this->normalizeEmail($email);

        // Exact match
        if ($email === $pattern) {
            return true;
        }

        // Legacy support: if pattern doesn't contain @, treat it as a domain-only pattern
        if (strpos($pattern, '@') === false) {
            // Extract domain from email
            $emailParts = explode('@', $email);
            if (count($emailParts) === 2) {
                $emailDomain = $emailParts[1];

                return $emailDomain === $pattern;
            }

            return false;
        }

        // Normalize pattern too in case it contains + aliases
        $pattern = $this->normalizeEmail($pattern);

        // Convert wildcard pattern to regex
        // Escape special regex characters except *
        $regexPattern = preg_quote($pattern, '/');
        // Replace escaped \* with .* for regex wildcard
        $regexPattern = str_replace('\\*', '.*', $regexPattern);
        // Anchor the pattern
        $regexPattern = '/^' . $regexPattern . '$/';

        return (bool)preg_match($regexPattern, $email);
    }

    /**
     * Normalize email address by removing + aliases.
     * Example: "user+tag@example.com" becomes "user@example.com".
     */
    private function normalizeEmail(string $email): string
    {
        $email = strtolower(trim($email));

        // Split email into local part and domain
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }

        [$localPart, $domain] = $parts;

        // Remove everything after + in the local part
        if (str_contains($localPart, '+')) {
            $localPart = substr($localPart, 0, strpos($localPart, '+'));
        }

        return $localPart . '@' . $domain;
    }
}
