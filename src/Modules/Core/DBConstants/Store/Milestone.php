<?php

// table `fs_betrieb_notiz`

namespace Foodsharing\Modules\Core\DBConstants\Store;

class Milestone
{
    final public const NONE = 0; // regular user wallpost / comment
    final public const CREATED = 1;
    final public const ACCEPTED = 2;
    final public const STATUS_CHANGED = 3; // this is no longer generated
    // ancient or unused = 4;
    final public const DROPPED = 5; // this is no longer generated

    public static function isStoreMilestone(int $value): bool
    {
        return in_array($value, [
            self::CREATED,
            self::STATUS_CHANGED,
        ]);
    }

    public static function isTeamMilestone(int $value): bool
    {
        return in_array($value, [
            self::ACCEPTED,
            self::DROPPED,
        ]);
    }
}
