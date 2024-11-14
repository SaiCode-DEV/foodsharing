<?php

namespace Foodsharing\Lib;

/**
 *  This translates old page= values to new URLs in order to keep old links working.
 */
class LegacyRoutes
{
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
        'mailbox',
        'poll',
        'bcard',
        'fsbetrieb',
    ];

    private const RENAMES = [
        'bezirk' => 'region',
        'statistics' => 'statistik',
        'map' => 'karte',
        'basket' => 'essenskoerbe',
        'fsbetrieb' => 'store',
    ];

    public static function isValidLegacyPageName(string $pageName): bool
    {
        return in_array($pageName, self::PORTED);
    }

    public static function getPortedName(string $pageName): string
    {
        return array_key_exists($pageName, self::RENAMES) ? self::RENAMES[$pageName] : $pageName;
    }
}
