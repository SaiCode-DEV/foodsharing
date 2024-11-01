<?php

namespace Foodsharing\Lib;

use Foodsharing\Modules\BusinessCard\BusinessCardControl;
use Foodsharing\Modules\Index\IndexControl;
use Foodsharing\Modules\Mailbox\MailboxControl;
use Foodsharing\Modules\StoreUser\StoreUserControl;
use Foodsharing\Modules\Voting\VotingControl;

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
        'bcard' => 'BusinessCard',
        'index' => 'Index',
        'mailbox' => 'Mailbox',
        'poll' => 'Voting',
        'fsbetrieb' => 'StoreUser',
    ];

    private const CLASSES = [
        'bcard' => BusinessCardControl::class,
        'index' => IndexControl::class,
        'mailbox' => MailboxControl::class,
        'poll' => VotingControl::class,
        'fsbetrieb' => StoreUserControl::class,
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
        'legal',
        'report',
        'register',
        'application',
        'msg',
        'logout',
        'relogin',
        'dashboard',
        'support',
        'groups',
    ];

    private const RENAMES = [
        'bezirk' => 'region',
        'statistics' => 'statistik',
        'map' => 'karte',
        'basket' => 'essenskoerbe'
    ];

    public static function getClassName(string $appName): ?string
    {
        return self::CLASSES[$appName] ?? null;
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
