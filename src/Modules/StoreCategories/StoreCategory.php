<?php

namespace Foodsharing\Modules\StoreCategories;

/**
 * Contains the properties of a store category to be displayed in the store category admin tool.
 */
class StoreCategory
{
    public function __construct(
        public int $id = 0,
        public string $name = '',
        public int $numberOfStores = 0
    ) {
    }
}
