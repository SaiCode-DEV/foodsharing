<?php

namespace Foodsharing\Lib;

use Exception;
use Flourish\fSession;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Symfony\Component\HttpFoundation\Request;

use function array_key_exists;

class Session
{
    // update this whenever adding new fields to the session!!!
    // this should be a unix timestamp, together with a human readable date in a comment.
    private const LAST_SESSION_SCHEMA_CHANGE = 1_716_804_439; // 2024-05-28 19:07 UTC

    private const SESSION_TIMESTAMP_FIELD_NAME = 'last_updated_ts';

    private const DEFAULT_NORMAL_SESSION_TIMESPAN = '24 hours';

    private const DEFAULT_PERSISTENT_SESSION_TIMESPAN = '14 days';

    public function __construct(
        private readonly FoodsaverGateway $foodsaverGateway,
        private bool $initialized = false
    ) {
    }

    public function initIfCookieExists()
    {
        if (isset($_COOKIE[session_name()]) && !$this->initialized) {
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

        ini_set('session.save_handler', 'redis');
        ini_set('session.save_path', 'tcp://' . REDIS_HOST . ':' . REDIS_PORT);

        fSession::setLength(
            self::DEFAULT_NORMAL_SESSION_TIMESPAN,
            self::DEFAULT_PERSISTENT_SESSION_TIMESPAN
        );

        if ($rememberMe) {
            // This regenerates the session id even if it's already persistent, we want to only set it when logging in
            fSession::enablePersistence();
        }

        fSession::open();

        $cookieExpires = $this->isPersistent() ? strtotime(self::DEFAULT_PERSISTENT_SESSION_TIMESPAN) : 0;
        if (!isset($_COOKIE['CSRF_TOKEN']) || !$_COOKIE['CSRF_TOKEN'] || !$this->isValidCsrfToken($_COOKIE['CSRF_TOKEN'])) {
            setcookie('CSRF_TOKEN', $this->generateCrsfToken(), ['expires' => $cookieExpires, 'path' => '/']);
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

        // Refresh content of session if it is older then 1 day
        if ($this->id() !== null && $this->has(self::SESSION_TIMESTAMP_FIELD_NAME)) {
            $last_update = $this->get(self::SESSION_TIMESTAMP_FIELD_NAME);
            if (strtotime($last_update) > strtotime('+1 day', time())) {
                $this->refreshFromDatabase();
            }
        }
    }

    private function isPersistent(): bool
    {
        return $_SESSION['fSession::type'] === 'persistent';
    }

    public function logout()
    {
        $_SESSION['client'] = [];
        unset($_SESSION['client']);

        if ($this->initialized) {
            $this->set('user', false);
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

    // temporary workaround to aid in migration off of fAuthorization
    private function fAuthorizationFallbackGet(string $key, string $flourishName = null): mixed
    {
        $flourishName ??= $key;
        if ($this->has($key)) {
            return $this->get($key);
        } elseif ($this->has('Flourish\fAuthorization::' . $flourishName)) {
            return $this->get('Flourish\fAuthorization::' . $flourishName);
        } elseif ($this->has('fAuthorization::' . $flourishName)) {
            return $this->get('fAuthorization::' . $flourishName);
        } else {
            return null;
        }
    }

    public function id(): ?int
    {
        return $this->fAuthorizationFallbackGet('userId', 'user_token');
    }

    protected function setAuthLevel(Role $role): void
    {
        $this->set('role', $role);
    }

    public function role(): ?Role
    {
        $role = $this->fAuthorizationFallbackGet('role', 'user_auth_level');

        if (is_string($role)) { // came from user_auth_level, so we need to convert it
            $role = Role::fromOldLevelName($role);
            $this->setAuthLevel($role); // store it again under the new key, so we stop needing the old key in the future
        }

        return $role;
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
        fSession::destroy();
    }

    public function has($key): bool
    {
        if (!$this->initialized) {
            return false;
        }

        return array_key_exists($key, $_SESSION);
    }

    public function set($key, $value): void
    {
        /* fail silently when session does not exist. This allows us at some point to also support sessions for not logged in users.
        It doesn't do any harm in other cases as we previously generated 500 responses */
        if ($this->initialized) {
            fSession::set($key, $value);
        }
    }

    public function get($key)
    {
        if (!$this->initialized) {
            return false;
        }

        return fSession::get($key, false);
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
        $bType = $_SESSION['fSession::type'];
        $bExpired = $_SESSION['fSession::expires'];
        $bCsrf = $_SESSION['csrf'];
        session_unset();
        $_SESSION['fSession::type'] = $bType;
        $_SESSION['fSession::expires'] = $bExpired;
        $_SESSION['csrf'] = $bCsrf;

        // used by Session::initIfCookieExists to determine if it should call this method to update session data
        $this->set(self::SESSION_TIMESTAMP_FIELD_NAME, time());

        $this->setId($fs['id']);
        $this->setAuthLevel(Role::tryFrom($fs['rolle']));

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

        $_SESSION['login'] = true;
        $_SESSION['client'] = [
            'id' => $fs['id'],
            'bezirk_id' => $fs['bezirk_id'],
            'rolle' => (int)$fs['rolle'],
            'verified' => (int)$fs['verified'],
            'last_activity' => $fs['last_activity'],
        ];
    }

    public function isVerified(): bool
    {
        if ($this->mayRole(Role::ORGA)) {
            return true;
        }

        if (isset($_SESSION['client']['verified']) && $_SESSION['client']['verified'] == 1) {
            return true;
        }

        return false;
    }

    public function generateCrsfToken(): string
    {
        $token = bin2hex(random_bytes(16));

        // old key, uses fSession array logic so:
        // csrf => [ 'cookie' => [ '<$token>' => true ] ]
        // commented out but not removed for posterity
        //$this->set("csrf[$key][$token]", true);

        // the new format gets rid of the 'cookie' key, but keeps the "set" structure
        // e.g. tokens are keys, and them being set indicates their validity.
        // this is faster than iterating over a list of tokens to check for presence

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
            if (isset($csrf['cookie'])) { // old token storage
                return isset($csrf['cookie'][$token]) && $csrf['cookie'][$token] === true;
            }

            // new token, just check if it's in there
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
}
