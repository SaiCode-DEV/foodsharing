<?php

namespace Foodsharing\RestApi\Models\FoodSharePoint;

class AddFoodSharePointResponse
{
    public function __construct(
        readonly int $id,
        readonly bool $isAdded
    ) {
    }
}
