<?php

namespace Foodsharing\Modules\Store\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

class EditPickupData
{
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    #[OA\Property(example: 4, description: 'Maximum allowed users on this pickup.')]
    public int $totalSlots;

    #[Assert\Length(max: 100)]
    #[OA\Property(example: 'Please come to the back door.', description: 'Description of this pickup.')]
    public ?string $description = null;
}
