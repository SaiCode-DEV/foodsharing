<?php

namespace Foodsharing\Modules\Core\DBConstants\Store;

/**
 * column 'presse' in table 'fs_betrieb', TINYINT(4).
 */
enum PublicityStatus: int
{
    case PUBLIC_MENTIONING_NOT_ALLOWED = 0;
    case PUBLIC_MENTIONING_ALLOWED = 1;
    case NOT_CHOSEN = 2;
}
