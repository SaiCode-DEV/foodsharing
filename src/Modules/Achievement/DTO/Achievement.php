<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Achievement\DTO;

use DateTime;
use Foodsharing\Modules\Core\DBConstants\Achievement\DuplicateMode;
use Foodsharing\Modules\Core\DBConstants\Achievement\VisibilityType;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

class Achievement
{
    #[Assert\IsNull]
    #[OA\Property(example: 1)]
    public ?int $id;

    #[Assert\PositiveOrZero]
    #[OA\Property(example: 1)]
    public int $regionId;

    #[Assert\NotBlank]
    #[OA\Property(example: 'Food Hygiene Certificate')]
    public string $name;

    #[Assert\NotBlank]
    #[OA\Property(example: 'Awarded for completing a food hygiene course')]
    public string $description;

    #[Assert\NotEqualTo('')]
    #[OA\Property(example: 'fas fa-hands-wash')]
    public ?string $icon;

    #[Assert\Positive]
    #[OA\Property(example: '356')]
    public ?int $validityInDaysAfterAssignment = null;

    #[Assert\NotBlank]
    public VisibilityType $visibilityType = VisibilityType::SCOPE;

    #[Assert\NotBlank]
    public DuplicateMode $duplicateMode = DuplicateMode::OVERRIDE;

    #[Assert\IsNull]
    public ?DateTime $createdAt = null;

    #[Assert\IsNull]
    public ?DateTime $updatedAt = null;

    public static function create(
        ?int $id,
        int $regionId,
        string $name,
        string $description,
        ?string $icon,
        ?int $validityInDaysAfterAssignment,
        ?DateTime $createdAt,
        ?DateTime $updatedAt,
        VisibilityType $visibilityType,
        DuplicateMode $duplicateMode,
    ): Achievement {
        $achievement = new self();
        $achievement->id = $id;
        $achievement->regionId = $regionId;
        $achievement->name = $name;
        $achievement->description = $description;
        $achievement->icon = $icon;
        $achievement->validityInDaysAfterAssignment = $validityInDaysAfterAssignment;
        $achievement->createdAt = $createdAt;
        $achievement->updatedAt = $updatedAt;
        $achievement->visibilityType = $visibilityType;
        $achievement->duplicateMode = $duplicateMode;

        return $achievement;
    }
}
