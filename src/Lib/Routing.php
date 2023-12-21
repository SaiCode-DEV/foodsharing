<?php

namespace Foodsharing\Lib;

use Foodsharing\Modules\Application\ApplicationControl;
use Foodsharing\Modules\Basket\BasketControl;
use Foodsharing\Modules\Basket\BasketXhr;
use Foodsharing\Modules\Blog\BlogControl;
use Foodsharing\Modules\BusinessCard\BusinessCardControl;
use Foodsharing\Modules\Dashboard\DashboardControl;
use Foodsharing\Modules\Email\EmailControl;
use Foodsharing\Modules\Event\EventControl;
use Foodsharing\Modules\Foodsaver\FoodsaverControl;
use Foodsharing\Modules\Foodsaver\FoodsaverXhr;
use Foodsharing\Modules\FoodSharePoint\FoodSharePointControl;
use Foodsharing\Modules\Index\IndexControl;
use Foodsharing\Modules\Legal\LegalControl;
use Foodsharing\Modules\Login\LoginControl;
use Foodsharing\Modules\Logout\LogoutControl;
use Foodsharing\Modules\Mailbox\MailboxControl;
use Foodsharing\Modules\Mailbox\MailboxXhr;
use Foodsharing\Modules\Main\MainXhr;
use Foodsharing\Modules\Map\MapControl;
use Foodsharing\Modules\Map\MapXhr;
use Foodsharing\Modules\Message\MessageControl;
use Foodsharing\Modules\PassportGenerator\PassportGeneratorControl;
use Foodsharing\Modules\Profile\ProfileControl;
use Foodsharing\Modules\Profile\ProfileXhr;
use Foodsharing\Modules\Quiz\QuizControl;
use Foodsharing\Modules\Quiz\QuizXhr;
use Foodsharing\Modules\Region\RegionXhr;
use Foodsharing\Modules\RegionAdmin\RegionAdminControl;
use Foodsharing\Modules\Register\RegisterControl;
use Foodsharing\Modules\Relogin\ReloginControl;
use Foodsharing\Modules\Report\ReportControl;
use Foodsharing\Modules\Report\ReportXhr;
use Foodsharing\Modules\Settings\SettingsControl;
use Foodsharing\Modules\Settings\SettingsXhr;
use Foodsharing\Modules\Store\StoreControl;
use Foodsharing\Modules\Store\StoreXhr;
use Foodsharing\Modules\StoreChain\StoreChainControl;
use Foodsharing\Modules\StoreUser\StoreUserControl;
use Foodsharing\Modules\Voting\VotingControl;
use Foodsharing\Modules\WallPost\WallPostXhr;
use Foodsharing\Modules\WorkGroup\WorkGroupControl;
use Foodsharing\Modules\WorkGroup\WorkGroupXhr;

/**
 * @deprecated please don't add anything new to these mappings.
 *  This is being slowly moved to Symfony routing, and can then be removed, or inlined into what's left of the Xhr system at that point.
 */
class Routing
{
    private const MODULES = [
        'activity' => 'Activity',
        'application' => 'Application',
        'basket' => 'Basket',
        'bell' => 'Bell',
        'blog' => 'Blog',
        'buddy' => 'Buddy',
        'bcard' => 'BusinessCard',
        'dashboard' => 'Dashboard',
        'email' => 'Email',
        'event' => 'Event',
        'fairteiler' => 'FoodSharePoint',
        'foodsaver' => 'Foodsaver',
        'index' => 'Index',
        'legal' => 'Legal',
        'login' => 'Login',
        'logout' => 'Logout',
        'mailbox' => 'Mailbox',
        'main' => 'Main',
        'map' => 'Map',
        'msg' => 'Message',
        'message' => 'Message',
        'passgen' => 'PassportGenerator',
        'poll' => 'Voting',
        'profile' => 'Profile',
        'quiz' => 'Quiz',
        'region' => 'RegionAdmin',
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
        'blog' => BlogControl::class,
        'basket' => BasketControl::class,
        'bcard' => BusinessCardControl::class,
        'dashboard' => DashboardControl::class,
        'email' => EmailControl::class,
        'event' => EventControl::class,
        'fairteiler' => FoodSharePointControl::class,
        'foodsaver' => FoodsaverControl::class,
        'index' => IndexControl::class,
        'legal' => LegalControl::class,
        'login' => LoginControl::class,
        'logout' => LogoutControl::class,
        'mailbox' => MailboxControl::class,
        'map' => MapControl::class,
        'msg' => MessageControl::class,
        'message' => MessageControl::class,
        'passgen' => PassportGeneratorControl::class,
        'poll' => VotingControl::class,
        'profile' => ProfileControl::class,
        'quiz' => QuizControl::class,
        'region' => RegionAdminControl::class,
        'register' => RegisterControl::class,
        'relogin' => ReloginControl::class,
        'report' => ReportControl::class,
        'settings' => SettingsControl::class,
        'betrieb' => StoreControl::class,
        'fsbetrieb' => StoreUserControl::class,
        'groups' => WorkGroupControl::class,
        'store' => StoreControl::class,
        'chain' => StoreChainControl::class,
    ];

    private const XHR = [
        'foodsaver' => FoodsaverXhr::class,
        'mailbox' => MailboxXhr::class,
        'main' => MainXhr::class,
        'map' => MapXhr::class,
        'profile' => ProfileXhr::class,
        'quiz' => QuizXhr::class,
        'report' => ReportXhr::class,
        'settings' => SettingsXhr::class,
        'betrieb' => StoreXhr::class,
        'wallpost' => WallPostXhr::class,
        'groups' => WorkGroupXhr::class,
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
    ];

    private const RENAMES = [
        'bezirk' => 'region',
        'statistics' => 'statistik',
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
