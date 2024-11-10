<?php

namespace Foodsharing\Modules\Core\DBConstants\Store;

/**
 * column 'sticker' in table 'fs_betrieb', TINYINT(4).
 */
enum StickerStatus: int
{
    case HAS_NO_STICKER = 0;
    case HAS_STICKER = 1;
    case NOT_CHOSEN = 2;
}
