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
    #[Assert\NotEqualTo(value: ApplyType::REQUIRES_PROPERTIES, message: 'This application type is deprecated and not allowed to be set anymore.')]
    public int $applyType = ApplyType::NOBODY;

    /**
     * Filename of the working group's photo.
     *
     * @OA\Property(example="test.jpg")
     */
    public ?string $photo = null;

    /**
     * The prompt for applications to the group.
     *
     * @OA\Property(example="Please describe why you want to join this group.")
     */
    #[Assert\NotBlank(allowNull: true)]
    public ?string $applicationPrompt = null;

    /**
     * The working group's category.
     *
     * @OA\Property(example=1)
     */
    #[Assert\Type('integer')]
    public ?int $groupCategory = null;
}
