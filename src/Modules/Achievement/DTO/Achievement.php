<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Achievement\DTO;

use DateTime;
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

    #[Assert\NotBlank]
    #[OA\Property(example: 'fas fa-hands-wash')]
    public ?string $icon;

    #[Assert\Positive]
    #[OA\Property(example: '356')]
    public ?int $validityInDaysAfterAssignment = null;

    public bool $isRequestableByFoodsaver = false;

    #[Assert\IsNull]
    public ?DateTime $createdAt = null;

    #[Assert\IsNull]
    public ?DateTime $updatedAt = null;

    public static function createFromArray(array $data): Achievement
    {
        $achievement = new self();

        $achievement->id = $data['id'];
        $achievement->regionId = $data['region_id'];
        $achievement->name = $data['name'];
        $achievement->description = $data['description'];
        $achievement->icon = $data['icon'];
        $achievement->validityInDaysAfterAssignment = $data['validity_in_days_after_assignment'];
        $achievement->isRequestableByFoodsaver = (bool)$data['is_requestable_by_foodsaver'];
        $achievement->createdAt = new DateTime($data['created_at']);
        $achievement->updatedAt = isset($data['updated_at']) ? new DateTime($data['updated_at']) : null;

        return $achievement;
    }
}
