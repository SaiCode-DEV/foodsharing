<?php

namespace Foodsharing\Lib\Session;

use Foodsharing\Lib\Db\Mem;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\AbstractSessionHandler;

/**
 * Custom Redis session handler that reuses the existing Redis connection from Mem class.
 */
class FoodsharingRedisSessionHandler extends AbstractSessionHandler
{
    private Mem $mem;
    private string $prefix;
    private int $ttl;

    public function __construct(Mem $mem, int $ttl = 86400, string $prefix = 'fs_sess:')
    {
        $this->mem = $mem;
        $this->mem->ensureConnected(); // Make sure Redis is connected
        $this->prefix = $prefix;
        $this->ttl = $ttl;
    }

    /**
     * Updates the TTL for this session handler.
     * Used when migrating session to persistent mode.
     */
    public function setTtl(int $ttl): void
    {
        $this->ttl = $ttl;
    }

    /**
     * Check if a session is marked as persistent by reading its data from Redis.
     *
     * @param string $sessionId The session ID to check
     * @return bool True if the session is persistent, false otherwise
     */
    public function isPersistentSession(string $sessionId): bool
    {
        $data = $this->mem->cache->get($this->prefix . $sessionId);

        if ($data === false) {
            return false;
        }

        return str_contains($data, '"session_type";s:10:"persistent"');
    }

    protected function doRead(string $sessionId): string
    {
        $data = $this->mem->cache->get($this->prefix . $sessionId);

        return $data === false ? '' : $data;
    }

    protected function doWrite(string $sessionId, string $data): bool
    {
        // Store session data with TTL
        $result = $this->mem->cache->set(
            $this->prefix . $sessionId,
            $data,
            ['ex' => $this->ttl]
        );

        // Store the session ID in the user's session list if user is logged in
        if (isset($_SESSION['client']['id']) && !empty($_SESSION['client']['id'])) {
            $this->mem->userAddSession($_SESSION['client']['id'], $sessionId);
        }

        return $result;
    }

    protected function doDestroy(string $sessionId): bool
    {
        // Remove the session from the user's session list if user is logged in
        if (isset($_SESSION['client']['id']) && !empty($_SESSION['client']['id'])) {
            $this->mem->userRemoveSession($_SESSION['client']['id'], $sessionId);
        }

        $result = $this->mem->cache->del($this->prefix . $sessionId);

        return $result !== false && (is_int($result) ? $result > 0 : true);
    }

    public function updateTimestamp(string $sessionId, string $data): bool
    {
        // Just update TTL (touch) without rewriting data
        return $this->mem->cache->expire($this->prefix . $sessionId, $this->ttl);
    }

    public function close(): bool
    {
        // Nothing to close - the Redis connection is managed by Mem class
        return true;
    }

    public function gc(int $maxlifetime): int|false
    {
        // Redis automatically handles expiration
        return 0;
    }
}
