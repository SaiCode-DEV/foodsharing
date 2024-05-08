<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Achievement;

use Foodsharing\Modules\Achievement\DTO\Achievement;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;

class AchievementGateway extends BaseGateway
{
    public function __construct(
        Database $db,
    ) {
        parent::__construct($db);
    }

    /**
     * Adds a given achievement to the database.
     *
     * @return int the id of the added Achievement
     */
    public function addAchievement(Achievement $achievement): int
    {
        return $this->db->insert('fs_achievement', [
            'name' => $achievement->name,
            'description' => $achievement->description,
            'validity_in_days_after_assignment' => $achievement->validityInDaysAfterAssignment,
            'is_requestable_by_foodsaver' => $achievement->isRequestableByFoodsaver,
        ]);
    }

    /**
     * Updates a given achievement in the database.
     * The id of the given Achievement object defines the row to update, the other properties the new values for that achievement.
     */
    public function updateAchievement(Achievement $achievement): void
    {
        $this->db->update('fs_achievement', [
            'name' => $achievement->name,
            'description' => $achievement->description,
            'validity_in_days_after_assignment' => $achievement->validityInDaysAfterAssignment,
            'is_requestable_by_foodsaver' => $achievement->isRequestableByFoodsaver,
        ], [
            'id' => $achievement->id
        ]);
    }

    /**
     * Fetches an achievement from the database.
     */
    public function getAchievement(int $id): Achievement
    {
        $achievement = $this->db->fetchById('fs_achievement', '*', $id);

        return Achievement::createFromArray($achievement);
    }
}
