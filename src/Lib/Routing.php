<?php

namespace Foodsharing\Lib;

use Foodsharing\Modules\Application\ApplicationControl;
use Foodsharing\Modules\Basket\BasketXhr;
use Foodsharing\Modules\BusinessCard\BusinessCardControl;
use Foodsharing\Modules\Dashboard\DashboardControl;
use Foodsharing\Modules\Foodsaver\FoodsaverControl;
use Foodsharing\Modules\Index\IndexControl;
use Foodsharing\Modules\Legal\LegalControl;
use Foodsharing\Modules\Logout\LogoutControl;
use Foodsharing\Modules\Mailbox\MailboxControl;
use Foodsharing\Modules\Map\MapXhr;
use Foodsharing\Modules\Message\MessageControl;
use Foodsharing\Modules\PassportGenerator\PassportGeneratorControl;
use Foodsharing\Modules\Region\RegionXhr;
use Foodsharing\Modules\Register\RegisterControl;
use Foodsharing\Modules\Relogin\ReloginControl;
use Foodsharing\Modules\Report\ReportControl;
use Foodsharing\Modules\Report\ReportXhr;
use Foodsharing\Modules\Settings\SettingsControl;
use Foodsharing\Modules\Settings\SettingsXhr;
use Foodsharing\Modules\Store\StoreController;
use Foodsharing\Modules\Store\StoreXhr;
use Foodsharing\Modules\StoreChain\StoreChainControl;
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
        'foodsaver' => 'Foodsaver',
        'index' => 'Index',
        'legal' => 'Legal',
        'logout' => 'Logout',
        'mailbox' => 'Mailbox',
        'msg' => 'Message',
        'message' => 'Message',
        'passgen' => 'PassportGenerator',
        'poll' => 'Voting',
        'register' => 'Register',
        'relogin' => 'Relogin',
        'report' => 'Report',
        'search' => 'Search',
        'settings' => 'Settings',
        'betrieb' => 'Store',
        'fsbetrieb' => 'StoreUser',
        'wallpost' => 'WallPost',
        'groups' => 'WorkGroup',
        'store' => 'Store',
        'chain' => 'StoreChain',
    ];

    private const CLASSES = [
        'application' => ApplicationControl::class,
        'bcard' => BusinessCardControl::class,
        'dashboard' => DashboardControl::class,
        'foodsaver' => FoodsaverControl::class,
        'index' => IndexControl::class,
        'legal' => LegalControl::class,
        'logout' => LogoutControl::class,
        'mailbox' => MailboxControl::class,
        'msg' => MessageControl::class,
        'message' => MessageControl::class,
        'passgen' => PassportGeneratorControl::class,
        'poll' => VotingControl::class,
        'register' => RegisterControl::class,
        'relogin' => ReloginControl::class,
        'report' => ReportControl::class,
        'settings' => SettingsControl::class,
        'fsbetrieb' => StoreUserControl::class,
        'groups' => WorkGroupControl::class,
        'store' => StoreController::class,
        'chain' => StoreChainControl::class,
    ];

    private const XHR = [
        'map' => MapXhr::class,
        'report' => ReportXhr::class,
        'settings' => SettingsXhr::class,
        'betrieb' => StoreXhr::class,
        'store' => StoreXhr::class,
        'basket' => BasketXhr::class,
        'region' => RegionXhr::class,
        'bezirk' => RegionXhr::class,
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
        'event',
        'quiz',
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
