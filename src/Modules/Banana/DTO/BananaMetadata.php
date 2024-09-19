<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Banana\DTO;

final class BananaMetadata
{
    public function __construct(
        public int $receivedCount = 0,
        public bool $mayGiveBanana = false,
        public bool $mayDeleteBananas = false,
    ) {
    }
}
