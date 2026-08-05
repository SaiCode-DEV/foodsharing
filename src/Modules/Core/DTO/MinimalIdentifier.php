<?php

namespace Foodsharing\Modules\Core\DTO;

/**
 * Describes a identifier of a entry.
 */
class MinimalIdentifier
{
    public function __construct(
        /**
         * Unique identifier of entry.
         */
        public int $id
    ) {
    }
}
