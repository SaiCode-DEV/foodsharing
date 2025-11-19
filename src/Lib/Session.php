<?php

namespace Foodsharing\Lib;

use Exception;
use Foodsharing\Lib\Db\Mem;
use Foodsharing\Lib\Session\FoodsharingRedisSessionHandler;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Login\LoginGateway;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Session as SymfonySession;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

class Session
{
    // update this whenever adding new fields to the session!!!
    // this should be a unix timestamp, together with a human readable date in a comment.
    private const int LAST_SESSION_SCHEMA_CHANGE = 1_741_984_322; // 2025-03-14 20:32 UTC

    private const string SESSION_TIMESTAMP_FIELD_NAME = 'last_updated_ts';

    private const string DEFAULT_NORMAL_SESSION_TIMESPAN = '24 hours';
    private const string DEFAULT_PERSISTENT_SESSION_TIMESPAN = '30 days';
    private const string USER_DATA_REFRESH_INTERVAL = '6 hours';

    public const string LAST_ACTIVITY = 'LAST_USER_ACTIVITY';

    public const string SESSION_COOKIE_NAME = 'FS_SESSID';
    public const string CSRF_COOKIE_NAME = 'FS_CSRF_TOKEN';

    protected ?SymfonySession $symfonySession = null;

    public function __construct(
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly LoginGateway $loginGateway,
        private readonly Mem $mem,
        private bool $initialized = false
    ) {
    }

    public function initIfCookieExists()
    {
        if (isset($_COOKIE[self::SESSION_COOKIE_NAME]) && !$this->initialized) {
            $this->init();

            // to handle cases where (mainly, but this could help with other cases too)
            // new fields get added to the session, this will force an update from the database
            // if the session is older than the last time something was changed about the session fields
            // an example for this: https://gitlab.com/foodsharing-dev/foodsharing/-/issues/1031
            $last_update = $this->get(self::SESSION_TIMESTAMP_FIELD_NAME);
            // $last_update can be 'false' if the session is older than when this mechanism was introduce
            // - there will not be any timestamp to check
            if ($last_update === false || $last_update < self::LAST_SESSION_SCHEMA_CHANGE) {
                // anonymous users can? also have an open session, but it does not actually store an ID.
                // This will cause problems in refreshFromDatabase, so only proceed if there is an ID.
                if ($this->id() !== null) {
                    $this->refreshFromDatabase();
                }
            }
        }

        $this->updateUserActivity($this->id());
    }

    private function checkInitialized()
    {
        if (!$this->initialized) {
            throw new Exception('Session not initialized');
        }
    }

    public function init($rememberMe = false)
    {
        if ($this->initialized) {
            throw new Exception('Session is already initialized');
        }

        $this->initialized = true;

        // Create our custom Redis session handler that reuses the existing Redis connection
        $ttl = $rememberMe
            ? strtotime(self::DEFAULT_PERSISTENT_SESSION_TIMESPAN) - time()
            : strtotime(self::DEFAULT_NORMAL_SESSION_TIMESPAN) - time();
        $redisSessionHandler = new FoodsharingRedisSessionHandler($this->mem, $ttl);
        // Determine the common parent domain for sharing sessions
        $currentHost = $_SERVER['HTTP_HOST'] ?? 'foodsharing.de';
        $domain = $this->getSessionDomain($currentHost);

        // Set session cookie parameters
        $sessionOptions = [
            'name' => self::SESSION_COOKIE_NAME,
            'cookie_lifetime' => $ttl,
            'cookie_path' => '/',
            'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on',
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'gc_maxlifetime' => $ttl,
        ];

        // Only set domain if we have a specific one to use
        if ($domain !== null) {
            $sessionOptions['cookie_domain'] = $domain;
        }

        // Create session storage and session
        $sessionStorage = new NativeSessionStorage($sessionOptions, $redisSessionHandler);
        $this->symfonySession = new SymfonySession($sessionStorage, new AttributeBag());
        $this->symfonySession->start();

        // Set session type (persistent or normal)
        if ($rememberMe) {
            $this->set('session_type', 'persistent');
            $this->set('session_expires', time() + $ttl);
            $this->symfonySession->migrate(true, $ttl);
        } else {
            $this->set('session_type', 'normal');
            $this->set('session_expires', time() + $ttl);
        }

        // Handle CSRF token
        // The CSRF cookie must be readable by JS so the client can send it in X-CSRF-TOKEN header
        $cookieOptions = [
            'expires' => time() + $ttl,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on',
            'httponly' => false,
            'samesite' => 'Lax'
        ];

        // Only set domain if we have a specific one to use
        if ($domain !== null) {
            $cookieOptions['domain'] = $domain;
        }

        // generate a new token if missing/invalid, otherwise reuse existing
        if (!isset($_COOKIE[self::CSRF_COOKIE_NAME]) || !$_COOKIE[self::CSRF_COOKIE_NAME] || !$this->isValidCsrfToken($_COOKIE[self::CSRF_COOKIE_NAME])) {
            setcookie(self::CSRF_COOKIE_NAME, $this->generateCrsfToken(), $cookieOptions);
        } else {
            // refresh cookie to ensure new attributes (like httponly=false) are applied
            setcookie(self::CSRF_COOKIE_NAME, $_COOKIE[self::CSRF_COOKIE_NAME], $cookieOptions);
        }

        if ($this->id()) {
            $loc = $this->user('location');
            if (!$loc) {
                $loc = $this->foodsaverGateway->getFoodsaverAddress($this->id());
                $loc = GeoLocation::createFromArray($loc, false);
                $user = $this->get('user');
                $user['location'] = $loc;
                $this->set('user', $user);
            }
        }

        // Refresh user data from database if it's older than the refresh interval
        if ($this->id() !== null && $this->has(self::SESSION_TIMESTAMP_FIELD_NAME)) {
            $last_update = $this->get(self::SESSION_TIMESTAMP_FIELD_NAME);
            if ($last_update < time() - strtotime(self::USER_DATA_REFRESH_INTERVAL)) {
                $this->refreshFromDatabase();
            }
        }
    }

    /**
     * Determines the common parent domain to use for sharing sessions.
     *
     * @param string $host The current hostname
     * @return string|null The domain to use for cookies, or null to use default behavior
     */
    private function getSessionDomain(string $host): ?string
    {
        // Strip port from host if it exists
        $hostWithoutPort = (string)preg_replace('/:\d+$/', '', $host);

        // Check if it's an IP address - return null to use default behavior
        if (filter_var($hostWithoutPort, FILTER_VALIDATE_IP)) {
            return null;
        }

        // Format: ['foodsharing.de', 'foodsharing.at', 'foodsharing.co.uk']
        // If SESSION_COOKIE_DOMAINS is defined we cast it to an array and only
        // proceed when it contains elements. This avoids using empty() directly
        // on the constant (which triggers static analysis warnings) while
        // preserving runtime behavior when SESSION_COOKIE_DOMAINS is not defined
        // in other environments (like production)
        if (defined('SESSION_COOKIE_DOMAINS')) {
            $domains = (array)SESSION_COOKIE_DOMAINS;

            if (count($domains) > 0) {
                // Check if current host matches or is a subdomain of any allowed domain
                foreach ($domains as $domain) {
                    $domain = trim($domain);
                    if ($hostWithoutPort === $domain ||
                        str_ends_with($hostWithoutPort, '.' . $domain)) {
                        return $domain;
                    }
                }
            }
        }

        // Fallback: Extract the base domain from the hostname without port
        // only works for 2 level domains (e.g. example.com)
        $parts = explode('.', $hostWithoutPort);

        if (count($parts) > 2) {
            // Return the parent domain (e.g. "example.com" for "sub.example.com")
            return implode('.', array_slice($parts, -2));
        }

        // For top-level domains without subdomains, return nothing. Otherwise,
        // Safari/webkit tests will fail for TLD host "nginx". See
        // https://gitlab.com/foodsharing-dev/foodsharing/-/merge_requests/4360#note_2862380078
        // for further details.
        return null;
    }

    public function logout()
    {
        // Store user ID before clearing session for cleanup purposes
        $userId = $this->id();
        $sessionId = session_id();

        if ($this->initialized) {
            // Clear session data
            $this->set('user', false);

            if ($userId && $sessionId) {
                $this->mem->userRemoveSession($userId, $sessionId);
            }

            $this->destroy();
        }
    }

    public function user($index)
    {
        $user = $this->get('user');

        return $user[$index] ?? null;
    }

    protected function setId(int $id): void
    {
        $this->set('userId', $id);
    }

    public function id(): ?int
    {
        if (!$this->initialized) {
            return null;
        }

        if ($this->has('userId')) {
            return $this->get('userId');
        }

        return null;
    }

    protected function setAuthLevel(Role $role): void
    {
        $this->set('role', $role);
    }

    public function role(): ?Role
    {
        if (!$this->initialized) {
            return null;
        }

        if ($this->has('role')) {
            return $this->get('role');
        }

        return null;
    }

    /**
     * Checks if the current user has at least the specified role.
     */
    public function mayRole(Role $minimalExpectedRoleLevel = Role::FOODSHARER): bool
    {
        if (!$this->id() || !$this->role()) {
            return false;
        }

        return $this->role()->isAtLeast($minimalExpectedRoleLevel);
    }

    private function destroy()
    {
        $this->checkInitialized();
        $this->symfonySession->clear();
        $this->symfonySession->invalidate();
    }

    public function has($key): bool
    {
        if (!$this->initialized) {
            return false;
        }

        return $this->symfonySession->has($key);
    }

    public function set($key, $value): void
    {
        /* fail silently when session does not exist. This allows us at some point to also support sessions for not logged in users.
        It doesn't do any harm in other cases as we previously generated 500 responses */
        if ($this->initialized) {
            $this->symfonySession->set($key, $value);
        }
    }

    public function get($key, $default = false)
    {
        if (!$this->initialized) {
            return false;
        }

        return $this->symfonySession->get($key, $default);
    }

    public function login($fs_id = null, $rememberMe = false)
    {
        if (!$this->initialized) {
            $this->init($rememberMe);
        }

        $this->refreshFromDatabase($fs_id);
    }

    /*
     * NOTE: if you change (or add) something in here, update LAST_SESSION_SCHEMA_CHANGE at the top of this class!
     */
    public function refreshFromDatabase($fs_id = null): void
    {
        $this->checkInitialized();

        if ($fs_id === null) {
            $fs_id = $this->id();
        }

        $fs = $this->foodsaverGateway->getFoodsaverDetails($fs_id);
        if (!$fs) {
            throw new Exception('Foodsaver details not found in database.');
        }

        // Clean up session so that all content from other models are removed
        // Store session type and expiration info before clearing
        $sessionType = $this->get('session_type');
        $sessionExpires = $this->get('session_expires');
        $csrfTokens = $this->get('csrf');

        $this->symfonySession->clear();

        // Restore session type and expiration info
        $this->set('session_type', $sessionType);
        $this->set('session_expires', $sessionExpires);
        $this->set('csrf', $csrfTokens);

        // used by Session::initIfCookieExists to determine if it should call this method to update session data
        $this->set(self::SESSION_TIMESTAMP_FIELD_NAME, time());

        $this->setId($fs['id']);
        $this->setAuthLevel(Role::tryFrom($fs['rolle']));

        // Register this session ID with the user in Redis for session tracking
        $sessionId = session_id();
        if ($sessionId) {
            $this->mem->userAddSession($fs['id'], $sessionId);
        }

        $this->set('user', [
            'name' => $fs['name'],
            'nachname' => $fs['nachname'],
            'role' => $fs['rolle'],
            'location' => GeoLocation::createFromArray($fs, false),
            'photo' => $fs['photo'],
            'gender' => $fs['geschlecht'],
            'privacy_policy_accepted_date' => $fs['privacy_policy_accepted_date'],
            'privacy_notice_accepted_date' => $fs['privacy_notice_accepted_date'],
        ]);

        $this->set('login', true);
        $this->set('client', [
            'id' => $fs['id'],
            'bezirk_id' => $fs['bezirk_id'],
            'rolle' => (int)$fs['rolle'],
            'verified' => (int)$fs['verified'],
            'last_activity' => $fs['last_activity'],
        ]);
    }

    public function isVerified(): bool
    {
        if ($this->mayRole(Role::ORGA)) {
            return true;
        }

        $client = $this->get('client');
        if (isset($client['verified']) && $client['verified'] == 1) {
            return true;
        }

        return false;
    }

    public function generateCrsfToken(): string
    {
        $token = bin2hex(random_bytes(16));

        // Store token in the flat structure
        $csrf = $this->get('csrf');
        if (!$csrf) {
            $csrf = [];
        }
        $csrf[$token] = true;

        $this->set('csrf', $csrf);

        return $token;
    }

    public function isValidCsrfToken(string $token): bool
    {
        if (defined('CSRF_TEST_TOKEN') && $token === CSRF_TEST_TOKEN) {
            return true;
        }

        $csrf = $this->get('csrf');
        if ($csrf !== false) {
            return isset($csrf[$token]) && $csrf[$token] === true;
        }

        return false; // no csrf token map stored, should not normally happen, but we treat this as "invalid"
    }

    public function isValidCsrfHeader(Request $request): bool
    {
        // enable CSRF Protection only for loggedin users
        if (!$this->id()) {
            return true;
        }

        $csrfToken = $request->server->get('HTTP_X_CSRF_TOKEN');
        if (!isset($csrfToken)) {
            return false;
        }

        return $this->isValidCsrfToken($csrfToken);
    }

    /**
     * Updates the last activity state of the user in database and session.
     *
     * The update in database and session is only executed on date change, the
     * time information is unused.
     */
    public function updateUserActivity(?int $userId)
    {
        if ($userId === null) {
            // no userId, probably API testing
            return;
        }
        $refreshSession = false;
        // load existing data
        if (!$this->has(self::LAST_ACTIVITY)) {
            $last_activity = $this->loginGateway->getLastLogin($userId);
            $refreshSession = true;
        } else {
            $last_activity = $this->get(self::LAST_ACTIVITY);
        }

        // sanitize data
        $lastActivityDataTime = strtotime((string)$last_activity);
        $isInvalidDateInformation = $lastActivityDataTime === false || $last_activity == '0000-00-00 00:00:00';
        $lastActivityDate = date('Y-m-d', $lastActivityDataTime);

        $today = date('Y-m-d');

        // Refresh data
        if ($isInvalidDateInformation || $today != $lastActivityDate) {
            $this->loginGateway->updateLastActivityInDatabase($userId);
            $refreshSession = true;
        }
        if ($refreshSession) {
            $this->set(self::LAST_ACTIVITY, $today);
        }
    }
}
