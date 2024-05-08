<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Achievement\DTO;

use DateTime;

class Achievement
{
    public int $id;
    public string $name;
    public string $description;
    public ?int $validityInDaysAfterAssignment = null;
    public bool $isRequestableByFoodsaver = false;
    public ?DateTime $createdAt = null;
    public ?DateTime $updatedAt = null;

    public static function createFromArray(array $data): Achievement
    {
        $achievement = new self();

        $achievement->id = $data['id'];
        $achievement->name = $data['name'];
        $achievement->description = $data['description'];
        $achievement->validityInDaysAfterAssignment = $data['validity_in_days_after_assignment'];
        $achievement->isRequestableByFoodsaver = (bool)$data['is_requestable_by_foodsaver'];
        $achievement->createdAt = new DateTime($data['created_at']);
        $achievement->updatedAt = isset($data['updated_at']) ? new DateTime($data['updated_at']) : null;

        return $achievement;
    }
}
