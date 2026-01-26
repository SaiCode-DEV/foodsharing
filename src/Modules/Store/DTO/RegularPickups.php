<?php

namespace Foodsharing\Modules\Store\DTO;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

class RegularPickups
{
    /**
     * @var RegularPickup[]
     * */
    #[Assert\NotBlank]
    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: RegularPickup::class)))]
    public array $regularPickups = [];
}
