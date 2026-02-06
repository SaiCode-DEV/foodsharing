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
    private const int LAST_SESSION_SCHEMA_CHANGE = 1_768_464_660; // 2026-01-15 08:11 UTC

    private const string SESSION_TIMESTAMP_FIELD_NAME = 'last_updated_ts';

    private const string DEFAULT_NORMAL_SESSION_TIMESPAN = '+24 hours';
    private const string DEFAULT_PERSISTENT_SESSION_TIMESPAN = '+30 days';
    private const string USER_DATA_REFRESH_INTERVAL = '+6 hours';

    private const string CSRF_TOKEN_LIFETIME = '+6 hours';
    private const string CSRF_GRACE_PERIOD = '+30 minutes';

    public const string LAST_ACTIVITY = 'LAST_USER_ACTIVITY';

    public const string SESSION_COOKIE_NAME = 'FS_SESSID';
    public const string CSRF_COOKIE_NAME = 'FS_CSRF_TOKEN';

    protected ?SymfonySession $symfonySession = null;
    protected ?FoodsharingRedisSessionHandler $sessionHandler = null;

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
            ? strtotime(self::DEFAULT_PERSISTENT_SESSION_TIMESPAN, 0)
            : strtotime(self::DEFAULT_NORMAL_SESSION_TIMESPAN, 0);

        $this->sessionHandler = new FoodsharingRedisSessionHandler($this->mem, $ttl);

        // Check if we have an existing session cookie that might be persistent
        if (!$rememberMe && isset($_COOKIE[self::SESSION_COOKIE_NAME])) {
            if ($this->sessionHandler->isPersistentSession($_COOKIE[self::SESSION_COOKIE_NAME])) {
                $rememberMe = true;
                $ttl = strtotime(self::DEFAULT_PERSISTENT_SESSION_TIMESPAN, 0);
                $this->sessionHandler->setTtl($ttl);
            }
        }

        // Set session cookie parameters
        $sessionOptions = [
            'name' => self::SESSION_COOKIE_NAME,
            'cookie_lifetime' => $ttl,
            'cookie_path' => '/',
            'cookie_secure' => $this->isCookieSecure(),
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'gc_maxlifetime' => $ttl,
        ];

        $domain = $this->getSessionDomain();
        // Only set domain if we have a specific one to use
        if ($domain !== null) {
            $sessionOptions['cookie_domain'] = $domain;
        }

        // Create session storage and session
        $sessionStorage = new NativeSessionStorage($sessionOptions, $this->sessionHandler);
        $this->symfonySession = new SymfonySession($sessionStorage, new AttributeBag());
        $this->symfonySession->start();

        // Only set session metadata if this is a brand new session
        // (no existing session data found in Redis)
        if (!isset($_COOKIE[self::SESSION_COOKIE_NAME]) || !$this->has('session_type')) {
            if ($rememberMe) {
                $this->set('session_type', 'persistent');
                $this->set('session_expires', time() + $ttl);
            } else {
                $this->set('session_type', 'normal');
                $this->set('session_expires', time() + $ttl);
            }
        }

        // Handle CSRF token cookie
        $this->setCSRFToken();

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
            if (time() - $last_update > strtotime(self::USER_DATA_REFRESH_INTERVAL, 0)) {
                $this->refreshFromDatabase();
            }
        }
    }

    /**
     * Determines the common parent domain to use for sharing sessions.
     *
     * @return string|null The domain to use for cookies (with leading dot), or null to use default behavior
     */
    private function getSessionDomain(): ?string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'foodsharing.de';
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
                    if ($hostWithoutPort === $domain || str_ends_with($hostWithoutPort, '.' . $domain)) {
                        // Always return with leading dot for subdomain sharing
                        return '.' . $domain;
                    }
                }
            }
        }

        // Fallback: Extract the base domain from the hostname without port
        // only works for 2 level domains (e.g. example.com)
        $parts = explode('.', $hostWithoutPort);

        if (count($parts) >= 2) {
            // Return the parent domain with leading dot (e.g. ".example.com" for "sub.example.com")
            return '.' . implode('.', array_slice($parts, -2));
        }

        // For top-level domains without subdomains, return nothing. Otherwise,
        // Safari/webkit tests will fail for TLD host "nginx". See
        // https://gitlab.com/foodsharing-dev/foodsharing/-/merge_requests/4360#note_2862380078
        // for further details.
        return null;
    }

    /**
     * Sets CSRF token cookie if not already set or invalid.
     */
    private function setCSRFToken(): void
    {
        if (isset($_COOKIE[self::CSRF_COOKIE_NAME]) && $this->isValidCsrfToken($_COOKIE[self::CSRF_COOKIE_NAME])) {
            return;
        }

        $csrfToken = $this->generateCSRFToken();

        $csrfCookieOptions = [
            'expires' => strtotime(self::CSRF_TOKEN_LIFETIME),
            'path' => '/',
            'secure' => $this->isCookieSecure(),
            'httponly' => false,
            'samesite' => 'Lax'
        ];

        $domain = $this->getSessionDomain();
        if ($domain !== null) {
            $csrfCookieOptions['domain'] = $domain;
        }

        setcookie(self::CSRF_COOKIE_NAME, $csrfToken, $csrfCookieOptions);
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

            $this->symfonySession->clear();
            $this->destroy();
        }

        // Delete cookies - with and without domain
        $cookieOptions = [
            'expires' => time() - 3600,
            'httponly' => true,
            'samesite' => 'Lax'
        ];

        // Delete without domain (for exact host match)
        setcookie(self::SESSION_COOKIE_NAME, '', $cookieOptions);

        $csrfOptions = $cookieOptions;
        $csrfOptions['httponly'] = false;
        setcookie(self::CSRF_COOKIE_NAME, '', $csrfOptions);

        // Delete with domain (for subdomain sharing)
        $domain = $this->getSessionDomain();
        if ($domain !== null) {
            $cookieOptions['domain'] = $domain;
            setcookie(self::SESSION_COOKIE_NAME, '', $cookieOptions);

            $csrfOptions['domain'] = $domain;
            setcookie(self::CSRF_COOKIE_NAME, '', $csrfOptions);
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
        $this->symfonySession->save();
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
        // if PHPSESSID cookie exists, delete ALL cookies to avoid confusion
        if (isset($_COOKIE['PHPSESSID']) || isset($_COOKIE['CSRF_TOKEN'])) {
            setcookie('PHPSESSID', '', ['expires' => time() - 3600]);
            setcookie('CSRF_TOKEN', '', ['expires' => time() - 3600]);
            $this->logout();
        }

        if (!$this->initialized) {
            $this->init($rememberMe);
        }

        $this->refreshFromDatabase($fs_id, $rememberMe);
    }

    /*
     * NOTE: if you change (or add) something in here, update LAST_SESSION_SCHEMA_CHANGE at the top of this class!
     */
    public function refreshFromDatabase($fs_id = null, $rememberMe = false): void
    {
        $this->checkInitialized();

        if ($fs_id === null) {
            $fs_id = $this->id();
        }

        $fs = $this->foodsaverGateway->getFoodsaverDetails($fs_id);
        if (!$fs) {
            throw new Exception('Foodsaver details not found in database.');
        }

        // Store CSRF tokens before clearing session
        $csrfTokens = $this->get('csrf');

        // Clear all session data
        $this->symfonySession->clear();

        // Set session metadata
        if ($rememberMe) {
            $ttl = strtotime(self::DEFAULT_PERSISTENT_SESSION_TIMESPAN, 0);
            $this->set('session_type', 'persistent');
            $this->set('session_expires', time() + $ttl);

            // Update the session handler's TTL for persistent sessions
            $this->sessionHandler?->setTtl($ttl);
        } else {
            $this->set('session_type', 'normal');
            $this->set('session_expires', time() + strtotime(self::DEFAULT_NORMAL_SESSION_TIMESPAN, 0));
        }

        $this->set('csrf', $csrfTokens);
        $this->set(self::SESSION_TIMESTAMP_FIELD_NAME, time());
        $this->setCSRFToken();

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
            'verified' => (int)$fs['verified'],
            'id' => $fs['id'],
        ]);
        /* TODO: This 'client' value is necessary because sessions are shared between beta and production. It can be
         remove after Release P, after which only 'user' is necessary. */
        $this->set('client', [
            'verified' => (int)$fs['verified'],
        ]);

        $this->set('login', true);

        // Force immediate write to Redis to prevent race conditions
        $this->symfonySession->save();

        // Set session cookie with correct expiration
        $this->setSessionCookie($this->get('session_expires'));
    }

    public function isVerified(): bool
    {
        if ($this->mayRole(Role::ORGA)) {
            return true;
        }

        return $this->user('verified') === 1;
    }

    /**
     * Generates a new CSRF token, stores it in the session, and returns it.
     *
     * @return string the generated CSRF token
     */
    public function generateCSRFToken(): string
    {
        $token = bin2hex(random_bytes(16));
        $expiresAt = strtotime(self::CSRF_TOKEN_LIFETIME);

        $csrf = $this->get('csrf');
        if (!$csrf || !is_array($csrf)) {
            $csrf = [];
        }

        // Cleanup expired tokens
        $cleanupThreshold = strtotime(self::CSRF_GRACE_PERIOD);
        $csrf = array_filter($csrf, function ($tokenData) use ($cleanupThreshold) {
            return is_array($tokenData) &&
                   isset($tokenData['expires']) &&
                   $tokenData['expires'] > $cleanupThreshold;
        });
        // Limit: maximum 5 active tokens
        if (count($csrf) >= 5) {
            // Remove oldest token
            uasort($csrf, fn ($a, $b) => $a['expires'] <=> $b['expires']);
            array_shift($csrf);
        }

        // Store new token with expiration
        $csrf[$token] = [
            'expires' => $expiresAt,
            'created' => time()
        ];

        $this->set('csrf', $csrf);

        return $token;
    }

    /**
     * Validates the provided CSRF token.
     *
     * @param string $token the CSRF token to validate
     * @return bool true if the token is valid, false otherwise
     */
    public function isValidCsrfToken(string $token): bool
    {
        if (defined('CSRF_TEST_TOKEN') && $token === CSRF_TEST_TOKEN) {
            return true;
        }
        $csrf = $this->get('csrf');
        if ($csrf === false || !is_array($csrf)) {
            return false;
        }

        // Check if token exists and is not expired
        if (!isset($csrf[$token])) {
            return false;
        }

        $tokenData = $csrf[$token];
        if (!is_array($tokenData) || !isset($tokenData['expires'])) {
            return false;
        }

        // Valid if not expired
        return $tokenData['expires'] > time();
    }

    /**
     * Checks if the CSRF token should be rotated based on its age.
     *
     * @param string $currentToken the current CSRF token to check
     * @return bool true if rotation is needed, false otherwise
     */
    public function shouldRotateCsrfToken(string $currentToken): bool
    {
        $csrf = $this->get('csrf');
        if ($csrf === false || !isset($csrf[$currentToken])) {
            return false;
        }

        $tokenData = $csrf[$currentToken];
        if (!is_array($tokenData) || !isset($tokenData['expires']) || !isset($tokenData['created'])) {
            return false;
        }

        $expiresAt = $tokenData['expires'];
        $createdAt = $tokenData['created'];
        $lifetime = $expiresAt - $createdAt;

        // Rotate if more than half of the token's lifetime has passed
        $halfwayPoint = $createdAt + ($lifetime / 2);

        return time() >= $halfwayPoint;
    }

    public function refreshCookiesIfNeeded(): void
    {
        $this->refreshCsrfTokenIfNeeded();
        $this->refreshSessionCookieIfNeeded();
    }

    private function refreshCsrfTokenIfNeeded(): void
    {
        $currentToken = $_COOKIE[self::CSRF_COOKIE_NAME] ?? null;
        if ($currentToken === null || !$this->shouldRotateCsrfToken($currentToken)) {
            return;
        }

        $newToken = $this->generateCSRFToken();
        $csrfCookieOptions = [
            'expires' => strtotime(self::CSRF_TOKEN_LIFETIME),
            'path' => '/',
            'secure' => $this->isCookieSecure(),
            'httponly' => false,
            'samesite' => 'Lax'
        ];

        $domain = $this->getSessionDomain();
        if ($domain !== null) {
            $csrfCookieOptions['domain'] = $domain;
        }

        setcookie(self::CSRF_COOKIE_NAME, $newToken, $csrfCookieOptions);
    }

    private function refreshSessionCookieIfNeeded(): void
    {
        if (!$this->initialized) {
            return;
        }

        $sessionExpires = $this->get('session_expires', false);
        if ($sessionExpires === false) {
            return;
        }

        $sessionType = $this->get('session_type', 'normal');
        $ttl = $sessionType === 'persistent'
            ? strtotime(self::DEFAULT_PERSISTENT_SESSION_TIMESPAN, 0)
            : strtotime(self::DEFAULT_NORMAL_SESSION_TIMESPAN, 0);

        // Only refresh if past halfway point of session lifetime
        $sessionAge = time() - ($sessionExpires - $ttl);
        if ($sessionAge < $ttl / 2) {
            return;
        }

        $newExpires = time() + $ttl;
        $this->set('session_expires', $newExpires);
        $this->setSessionCookie($newExpires);
    }

    private function setSessionCookie(int $expires): void
    {
        $sessionOptions = [
            'expires' => $expires,
            'path' => '/',
            'secure' => $this->isCookieSecure(),
            'httponly' => true,
            'samesite' => 'Lax'
        ];

        $domain = $this->getSessionDomain();
        if ($domain !== null) {
            $sessionOptions['domain'] = $domain;
        }

        setcookie(self::SESSION_COOKIE_NAME, session_id(), $sessionOptions);
    }

    public function isValidCsrfHeader(Request $request): bool
    {
        // enable CSRF Protection only for loggedin users
        if (!$this->id()) {
            return true;
        }

        $csrfToken = $request->headers->get('x-csrf-token');
        if (!isset($csrfToken)) {
            return false;
        }

        return $this->isValidCsrfToken($csrfToken);
    }

    public function isCookieSecure(): bool
    {
        if (in_array(getenv('FS_ENV'), ['dev', 'test'])) {
            return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on';
        }

        return true;
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
