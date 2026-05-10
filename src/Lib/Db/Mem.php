<?php

namespace Foodsharing\Lib\Db;

use Redis;

class Mem
{
    /**
     * @var Redis
     */
    public $cache;
    public $connected;

    public function connect()
    {
        if (MEM_ENABLED) {
            $this->connected = true;
            $this->cache = new Redis();
            $this->cache->connect(REDIS_HOST, REDIS_PORT);
        }
    }

    // Set a key to a value, ttl in seconds
    public function set($key, $data, $ttl = 0)
    {
        if (MEM_ENABLED) {
            $this->ensureConnected();
            $options = [];
            if ($ttl > 0) {
                $options['ex'] = $ttl;
            }
            if ($options) {
                return $this->cache->set($key, $data, $options);
            }

            return $this->cache->set($key, $data);
        }

        return false;
    }

    /**
     * Enqueue an email to be processed by the mailqueuerunner.
     * Counterpart of the asynchronous queue runner in OutgoingMailsService.
     */
    public function enqueueEmail($data, bool $highPriority = false)
    {
        if (MEM_ENABLED) {
            $e = serialize(['data' => $data]);
            $this->ensureConnected();

            if ($highPriority) {
                return $this->cache->rPush('workqueue', $e);
            } else {
                return $this->cache->lPush('workqueue', $e);
            }
        }
    }

    public function get($key)
    {
        if (MEM_ENABLED) {
            $this->ensureConnected();

            return $this->cache->get($key);
        }

        return false;
    }

    public function del($key)
    {
        if (MEM_ENABLED) {
            $this->ensureConnected();

            return $this->cache->del($key);
        }

        return false;
    }

    public function user($id, $key)
    {
        return $this->get('user-' . $key . '-' . $id);
    }

    public function userSet($id, $key, $value)
    {
        return $this->set('user-' . $key . '-' . $id, $value);
    }

    public function userAppend($id, $key, $value)
    {
        $out = [];
        if ($val = $this->user($id, $key)) {
            if (is_array($val)) {
                $out = $val;
            }
        }
        $out[] = $value;

        return $this->set('user-' . $key . '-' . $id, $out);
    }

    public function userDel($id, $key)
    {
        return $this->del('user-' . $key . '-' . $id);
    }

    /*
     * Add entry to the redis set that stores user -> session mappings.
     * e.g. for user=20 and sessionid=mysessionid it would run the redis command:
     *   > SADD php:user:20:sessions mysessionid
     *
     * This then provides a way to get all the active sessions for a user and expire old ones.
     * See `websocket/session-ids.lua` for a redis lua script that does this.
     */
    public function userAddSession($fs_id, $session_id)
    {
        $this->ensureConnected();

        return $this->cache->sAdd(join(':', ['php', 'user', $fs_id, 'sessions']), $session_id);
    }

    public function userRemoveSession($fs_id, $session_id)
    {
        $this->ensureConnected();

        return $this->cache->sRem(join(':', ['php', 'user', $fs_id, 'sessions']), $session_id);
    }

    /**
     * Invalidate all Redis-backed sessions for a given user.
     *
     * Deletes `fs_sess:<id>` keys and removes them from the
     * `php:user:<id>:sessions` set.
     *
     * @param int $userId foodsaver id
     * @param bool $exceptCurrentSession if true, the current session will not
     * be invalidated (if it belongs to the user)
     */
    public function invalidateSessionsForUser(int $userId, bool $exceptCurrentSession): void
    {
        try {
            $this->ensureConnected();
            $userKey = join(':', ['php', 'user', $userId, 'sessions']);
            $sessionIds = $this->cache->sMembers($userKey);
            if (!empty($sessionIds)) {
                foreach ($sessionIds as $sid) {
                    if ($exceptCurrentSession && session_id() === $sid) {
                        continue;
                    }
                    $this->cache->del('fs_sess:' . $sid);
                    $this->cache->sRem($userKey, $sid);
                }
            }
        } catch (\Throwable $e) {
            // Don't throw on Redis errors
        }
    }

    /**
     * Invalidate a specific field in all Redis-backed sessions for a given user.
     *
     * Deletes the field from `fs_sess:<id>` keys for all sessions of the user.
     *
     * @param int $userId foodsaver id
     * @param string $field the session field to delete
     */
    public function clearSessionFieldForUser(int $userId, string $field): void
    {
        try {
            $this->ensureConnected();
            $userKey = join(':', ['php', 'user', $userId, 'sessions']);
            $sessionIds = $this->cache->sMembers($userKey);
            if (!empty($sessionIds)) {
                foreach ($sessionIds as $sid) {
                    $this->cache->hDel('fs_sess:' . $sid, $field);
                }
            }
        } catch (\Throwable $e) {
            // Don't throw on Redis errors
        }
    }

    public function getPageCache($fsId)
    {
        return $this->get('pc-' . $_SERVER['REQUEST_URI'] . ':' . $fsId);
    }

    public function setPageCache($page, $ttl, $fsId)
    {
        return $this->set('pc-' . $_SERVER['REQUEST_URI'] . ':' . $fsId, $page, $ttl);
    }

    public function delPageCache($page, $fsId)
    {
        return $this->del('pc-' . $page . ':' . $fsId);
    }

    public function ensureConnected()
    {
        if (!$this->connected) {
            $this->connect();
        }
    }
}
