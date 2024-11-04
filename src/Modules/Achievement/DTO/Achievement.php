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

    #[Assert\NotEqualTo('')]
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

    public function __construct(?array $data = null)
    {
        if (is_null($data)) {
            return;
        }

        $this->id = $data['id'];
        $this->regionId = $data['region_id'];
        $this->name = $data['name'];
        $this->description = $data['description'];
        $this->icon = $data['icon'];
        $this->validityInDaysAfterAssignment = $data['validity_in_days_after_assignment'];
        $this->isRequestableByFoodsaver = (bool)$data['is_requestable_by_foodsaver'];
        $this->createdAt = new DateTime($data['created_at']);
        $this->updatedAt = isset($data['updated_at']) ? new DateTime($data['updated_at']) : null;
    }
}
