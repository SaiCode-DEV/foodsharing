<?php

// table `fs_betrieb`

namespace Foodsharing\Modules\Core\DBConstants\Store;

/**
 * column `betrieb_status_id`
 * status of the cooperation between foodsharing and a store
 * INT(10)          UNSIGNED NOT NULL.
 */
enum CooperationStatus: int
{
    case UNCLEAR = 0;
    case NO_CONTACT = 1;
    case IN_NEGOTIATION = 2;
    case DOES_NOT_WANT_TO_WORK_WITH_US = 4;
    case COOPERATION_ESTABLISHED = 5;
    case GIVES_TO_OTHER_CHARITY = 6;
    case PERMANENTLY_CLOSED = 7;

    public static function isValidStatus(int $status): bool
    {
        return CooperationStatus::tryFrom($status) != null;
    }
}
