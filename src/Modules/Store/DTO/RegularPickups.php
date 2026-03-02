<?php

namespace Foodsharing\Modules\Store\DTO;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

class RegularPickups
{
    /**
     * @var RegularPickup[]
     * */
    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: RegularPickup::class)))]
    public array $regularPickups = [];
}
