<?php

namespace Foodsharing\Modules\Event;

class InvitationStatus
{
    final public const int INVITED = 0; // invited
    final public const int ACCEPTED = 1; // will join
    final public const int MAYBE = 2; // might join
    final public const int WONT_JOIN = 3; // will not join (but has been invited)

    public static function isValidStatus(int $status): bool
    {
        return in_array($status, [
            self::INVITED,
            self::ACCEPTED,
            self::MAYBE,
            self::WONT_JOIN,
        ]);
    }
}
