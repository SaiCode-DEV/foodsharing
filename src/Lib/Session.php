<?php

namespace Foodsharing\Lib;

use Exception;
use Flourish\fSession;
use Foodsharing\Lib\Db\Mem;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\UserOptionType;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Login\LoginGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Settings\SettingsGateway;

use function array_key_exists;

class Session
{
    // update this whenever adding new fields to the session!!!
    // this should be a unix timestamp, together with a human readable date in a comment.
    private const LAST_SESSION_SCHEMA_CHANGE = 1_706_911_008; // 2024-02-02 22:57 UTC

    private const SESSION_TIMESTAMP_FIELD_NAME = 'last_updated_ts';

    final public const DEFAULT_LOCALE = 'de';

    private const DEFAULT_NORMAL_SESSION_TIMESPAN = '24 hours';

    private const DEFAULT_PERSISTENT_SESSION_TIMESPAN = '14 days';

    public function __construct(
        private readonly Mem $mem,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly RegionGateway $regionGateway,
        private readonly LoginGateway $loginGateway,
        private readonly SettingsGateway $settingsGateway,
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
            $this->mem->logout($this->id());
            $this->set('user', false);
            $this->destroy();
        }
    }

    public function user($index)
    {
        $user = $this->get('user');

        return $user[$index];
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

    public function getLocation(): ?array
    {
        if (!$this->initialized || !$this->id()) {
            return null;
        }

        $loc = fSession::get('g_location', null);
        if (!$loc) {
            $loc = $this->foodsaverGateway->getFoodsaverAddress($this->id());
            $this->set('g_location', ['lat' => $loc['lat'], 'lon' => $loc['lon']]);
        }

        return $loc;
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

    public function getLocale()
    {
        if (!$this->initialized) {
            return self::DEFAULT_LOCALE;
        }

        return fSession::get('locale', self::DEFAULT_LOCALE);
    }

    /**
     * gets a user specific option and will be available after next login.
     */
    public function getOption($key)
    {
        return $this->get('useroption_' . $key);
    }

    public function setOption($key, $val)
    {
        $this->foodsaverGateway->setOption($this->id(), $key, $val);
        $this->set('useroption_' . $key, $val);
    }

    public function getRegions(): array
    {
        return $_SESSION['client']['bezirke'] ?? [];
    }

    /**
     * @deprecated helper that makes ancient code easier to read (in theory, DashboardControl could use this)
     */
    private function getManagedRegions(): array
    {
        return $_SESSION['client']['botschafter'] ?? [];
    }

    public function listRegionIDs(): array
    {
        $regions = $this->getRegions();
        $out = [];
        foreach ($regions as $region) {
            $out[] = $region['id'];
        }

        return $out;
    }

    public function getMyAmbassadorRegionIds(bool $includeWorkingGroups = true): array
    {
        $managedRegions = $this->getManagedRegions();

        if (!$includeWorkingGroups) {
            $managedRegions = array_filter($managedRegions, fn ($region) => !UnitType::isGroup($region['type']));
        }

        $out = [];
        foreach ($managedRegions as $region) {
            $out[] = $region['bezirk_id'];
        }

        return $out;
    }

    public function isAdminFor(?int $regionId): bool
    {
        if ($this->isAmbassador()) {
            $managedRegions = $this->getManagedRegions();
            foreach ($managedRegions as $region) {
                if ($region['bezirk_id'] == $regionId) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getCurrentRegionId()
    {
        if (isset($_SESSION['client']['bezirk_id'])) {
            return $_SESSION['client']['bezirk_id'];
        }
    }

    public function setPhoto($file)
    {
        $_SESSION['client']['photo'] = $file;
    }

    public function isAmbassador(): bool
    {
        return isset($_SESSION['client']['botschafter']);
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

        // used by Session::initIfCookieExists to determine if it should call this method to update session data
        $this->set(self::SESSION_TIMESTAMP_FIELD_NAME, time());

        if ($fs_id === null) {
            $fs_id = $this->id();
        }

        $fs = $this->foodsaverGateway->getFoodsaverDetails($fs_id);
        if (!$fs) {
            throw new Exception('Foodsaver details not found in database.');
        }

        $this->setId($fs['id']);
        $this->setAuthLevel(Role::tryFrom($fs['rolle']));

        $this->set('g_location', [
            'lat' => $fs['lat'],
            'lon' => $fs['lon']
        ]);

        if ((int)$fs['bezirk_id'] > 0 && $this->role()->isAtLeast(Role::FOODSAVER)) {
            $this->regionGateway->addMember($fs_id, $fs['bezirk_id']);
        }

        if ($master = $this->regionGateway->getMasterId($fs['bezirk_id'])) {
            $this->regionGateway->addMember($fs_id, $master);
        }

        $this->setId($fs['id']);
        $this->setAuthLevel(Role::tryFrom($fs['rolle']));

        $this->set('user', [
            'name' => $fs['name'],
            'nachname' => $fs['nachname'],
            'photo' => $fs['photo'],
            'bezirk_id' => $fs['bezirk_id'],
            'email' => $fs['email'],
            'type' => $fs['type'],
            'verified' => $fs['verified'],
            'token' => $fs['token'],
            'mailbox_id' => $fs['mailbox_id'],
            'gender' => $fs['geschlecht'],
            'privacy_policy_accepted_date' => $fs['privacy_policy_accepted_date'],
            'privacy_notice_accepted_date' => $fs['privacy_notice_accepted_date'],
            'last_activity' => $fs['last_activity']
        ]);

        /*
         * Add entry into user -> session set
         */
        $this->mem->userAddSession($fs_id, session_id());

        /*
         * store all options in the session
        */
        if (!empty($fs['option'])) {
            $options = unserialize($fs['option']);
            foreach ($options as $key => $val) {
                $this->setOption($key, $val);
            }
        }

        $_SESSION['login'] = true;
        $_SESSION['client'] = [
            'id' => $fs['id'],
            'bezirk_id' => $fs['bezirk_id'],
            'group' => ['member' => true],
            'photo' => $fs['photo'],
            'rolle' => (int)$fs['rolle'],
            'verified' => (int)$fs['verified'],
            'last_activity' => $fs['last_activity']
        ];
        if ($this->role()->isAtLeast(Role::FOODSAVER)) {
            if ($r = $this->regionGateway->listRegionsForBotschafter($fs['id'])
            ) {
                $_SESSION['client']['botschafter'] = $r;
                foreach ($r as $rr) {
                    $this->regionGateway->addOrUpdateMember($fs['id'], $rr['id']);
                }
            }

            $_SESSION['client']['bezirke'] = $this->regionGateway->listForFoodsaver($fs['id']);
        }

        $this->set('locale', $this->settingsGateway->getUserOption($fs['id'], UserOptionType::LOCALE));
    }

    public function mayBezirk(int $regionId): bool
    {
        // Users that are not logged in don't have a role we could compare to
        if ($this->role() === null) {
            return false;
        }

        if ($this->role()->isAtLeast(Role::ORGA)) {
            return true;
        }
        // use database check if the session includes the region to unsure previleges are lost after removal from a region
        $isMember = isset($_SESSION['client']['bezirke'][$regionId]);
        if ($isMember && !$this->regionGateway->hasMember($this->id(), $regionId)) {
            unset($_SESSION['client']['bezirke'][$regionId]);

            return false;
        }

        return $isMember;
    }

    public function isAdminForAWorkGroup()
    {
        if ($all_group_admins = $this->mem->get('all_global_group_admins')) {
            return in_array($this->id(), unserialize($all_group_admins));
        }

        return false;
    }

    public function isVerified()
    {
        if ($this->mayRole(Role::ORGA)) {
            return true;
        }

        if (isset($_SESSION['client']['verified']) && $_SESSION['client']['verified'] == 1) {
            return true;
        }

        return false;
    }

    /**
     * Checks if the current user is an ambassador for one of the regions in the list of region IDs.
     *
     * @param array $regionIds list of region IDs
     * @param bool $include_groups if working group should be included in the check
     * @param bool $include_parent_regions if the parent regions should be included in the check
     */
    public function isAmbassadorForRegion($regionIds, $include_groups = true, $include_parent_regions = false): bool
    {
        if (is_array($regionIds) && count($regionIds) && $this->isAmbassador()) {
            if ($include_parent_regions) {
                $regionIds = $this->regionGateway->listRegionsIncludingParents($regionIds);
            }
            $managedRegions = $this->getManagedRegions();
            foreach ($managedRegions as $region) {
                foreach ($regionIds as $regId) {
                    $consider = $include_groups || UnitType::isRegion($region['type']);
                    if ($consider && $region['bezirk_id'] == $regId) {
                        return true;
                    }
                }
            }
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

    public function isValidCsrfHeader(): bool
    {
        // enable CSRF Protection only for loggedin users
        if (!$this->id()) {
            return true;
        }

        if (!isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return false;
        }

        return $this->isValidCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN']);
    }

    public function updateLastActivity()
    {
        $session_last_activity = $_SESSION['client']['last_activity'];
        if ($session_last_activity === '0000-00-00 00:00:00') {
            $session_last_activity = date('Y-m-d');
        }

        $last_activity = date('Y-m-d', strtotime((string)$session_last_activity));
        $today = date('Y-m-d');

        if ($this->isPersistent() && $today != $last_activity) {
            $this->loginGateway->updateLastActivityInDatabase($this->id());
            $this->refreshFromDatabase();
        }
    }
}
