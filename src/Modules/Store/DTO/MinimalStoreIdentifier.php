<?php

namespace Foodsharing\Modules\Store\DTO;

class MinimalStoreIdentifier
{
    public function __construct(
        public int $id = 0,
        public string $name = '',
    ) {
    }
}
