<?php

namespace Foodsharing\RestApi\Models\FoodSharePoint;

class AddFoodSharePointResponse
{
    public function __construct(
        public readonly int $id,
        public readonly bool $isAdded
    ) {
    }
}
