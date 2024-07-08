<?php

namespace Foodsharing\Lib;

use Foodsharing\Modules\Application\ApplicationControl;
use Foodsharing\Modules\Basket\BasketXhr;
use Foodsharing\Modules\BusinessCard\BusinessCardControl;
use Foodsharing\Modules\Dashboard\DashboardControl;
use Foodsharing\Modules\Index\IndexControl;
use Foodsharing\Modules\Logout\LogoutControl;
use Foodsharing\Modules\Mailbox\MailboxControl;
use Foodsharing\Modules\Message\MessageControl;
use Foodsharing\Modules\Register\RegisterControl;
use Foodsharing\Modules\Relogin\ReloginControl;
use Foodsharing\Modules\Report\ReportControl;
use Foodsharing\Modules\Report\ReportXhr;
use Foodsharing\Modules\Settings\SettingsXhr;
use Foodsharing\Modules\Store\StoreController;
use Foodsharing\Modules\StoreUser\StoreUserControl;
use Foodsharing\Modules\Voting\VotingControl;
use Foodsharing\Modules\WorkGroup\WorkGroupControl;

/**
 * @deprecated please don't add anything new to these mappings.
 *  This is being slowly moved to Symfony routing, and can then be removed, or inlined into what's left of the Xhr system at that point.
 */
class Routing
{
    // needed for webpack resource loading
    // for FoodsharingController, this is derived from the controller name
    // (which should match the module name)
    private const MODULES = [
        'activity' => 'Activity',
        'application' => 'Application',
        'bell' => 'Bell',
        'buddy' => 'Buddy',
        'bcard' => 'BusinessCard',
        'dashboard' => 'Dashboard',
        'index' => 'Index',
        'logout' => 'Logout',
        'mailbox' => 'Mailbox',
        'msg' => 'Message',
        'message' => 'Message',
        'poll' => 'Voting',
        'register' => 'Register',
        'relogin' => 'Relogin',
        'report' => 'Report',
        'search' => 'Search',
        'betrieb' => 'Store',
        'fsbetrieb' => 'StoreUser',
        'wallpost' => 'WallPost',
        'groups' => 'WorkGroup',
        'store' => 'Store',
    ];

    private const CLASSES = [
        'application' => ApplicationControl::class,
        'bcard' => BusinessCardControl::class,
        'dashboard' => DashboardControl::class,
        'index' => IndexControl::class,
        'logout' => LogoutControl::class,
        'mailbox' => MailboxControl::class,
        'msg' => MessageControl::class,
        'message' => MessageControl::class,
        'poll' => VotingControl::class,
        'register' => RegisterControl::class,
        'relogin' => ReloginControl::class,
        'report' => ReportControl::class,
        'fsbetrieb' => StoreUserControl::class,
        'groups' => WorkGroupControl::class,
        'store' => StoreController::class,
    ];

    private const XHR = [
        'report' => ReportXhr::class,
        'settings' => SettingsXhr::class,
        'basket' => BasketXhr::class,
    ];

    private const PORTED = [
        'content',
        'team',
        'bezirk',
        'statistics',
        'map',
        'blog',
        'betrieb',
        'fairteiler',
        'login',
        'profile',
        'chain',
        'event',
        'quiz',
        'settings',
        'legal'
    ];

    private const RENAMES = [
        'bezirk' => 'region',
        'statistics' => 'statistik',
        'map' => 'karte',
        'basket' => 'essenskoerbe'
    ];

    public static function getClassName(string $appName, $type = 'Xhr'): ?string
    {
        if ($type === 'Xhr') {
            return self::XHR[$appName] ?? null;
        } elseif ($type === 'Control') {
            return self::CLASSES[$appName] ?? null;
        } else {
            return null;
        }
    }

    public static function getModuleName(string $appName): ?string
    {
        return self::MODULES[$appName] ?? null;
    }

    public static function isPorted(string $pageName): bool
    {
        return in_array($pageName, self::PORTED);
    }

    public static function getPortedName(string $pageName): string
    {
        return array_key_exists($pageName, self::RENAMES) ? self::RENAMES[$pageName] : $pageName;
    }
}
