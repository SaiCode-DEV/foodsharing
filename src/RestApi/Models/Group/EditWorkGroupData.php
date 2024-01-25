<?php

namespace Foodsharing\RestApi\Models\Group;

use Foodsharing\Modules\Core\DBConstants\Region\ApplyType;
use OpenApi\Annotations as OA;
use Symfony\Component\Validator\Constraints as Assert;

class EditWorkGroupData
{
    /**
     * The working group's title.
     *
     * @OA\Property(example="Testgruppe")
     */
    #[Assert\NotBlank]
    public string $name = '';

    /**
     * The group's description text.
     *
     * @OA\Property(example="This is a working group.")
     */
    public ?string $description = null;

    /**
     * Denotes who is allowed to apply for this group.
     *
     * @OA\Property(example=0)
     */
    #[Assert\Type('integer')]
    #[Assert\Range(min: 0, max: 3)]
    public int $applyType = ApplyType::NOBODY;

    /**
     * If the apply type is REQUIRES_PROPERTIES, a foodsaver must have this many bananas before being allowed to apply
     * for this group.
     *
     * @OA\Property(example=0)
     */
    #[Assert\Type('integer')]
    #[Assert\Range(min: 0, max: 20, minMessage: 'group.application_requirements.banana_count_errors.min', maxMessage: 'group.application_requirements.banana_count_errors.max')]
    public int $requiredBananas = 0;

    /**
     * If the apply type is REQUIRES_PROPERTIES, a foodsaver must have been to this many pickups before being allowed
     * to apply for this group.
     *
     * @OA\Property(example=0)
     */
    #[Assert\Type('integer')]
    #[Assert\Range(min: 0, max: 100)]
    public int $requiredPickups = 0;

    /**
     * If the apply type is REQUIRES_PROPERTIES, a foodsaver must have been registered this many weeks before being
     * allowed to apply for this group.
     *
     * @OA\Property(example=0)
     */
    #[Assert\Range(min: 0, max: 52)]
    public int $requiredWeeks = 0;

    /**
     * Filename of the working group's photo.
     *
     * @OA\Property(example="test.jpg")
     */
    public ?string $photo = null;
}
