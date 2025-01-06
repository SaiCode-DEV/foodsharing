<?php

namespace Foodsharing\Modules\PassportGenerator;

use Carbon\Carbon;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;

final class PassportGeneratorGateway extends BaseGateway
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function logPassGeneration(int $generatedUserId, array $userIds): int
    {
        $now = $this->db->now();

        $data = array_map(fn ($userId) => [
            'foodsaver_id' => $userId,
            'date' => $now,
            'bot_id' => $generatedUserId,
        ], $userIds);

        return $this->db->insertMultiple('fs_pass_gen', $data);
    }

    public function updateFoodsaverLastPassDate(array $userIds): int
    {
        return $this->db->update('fs_foodsaver', ['last_pass' => $this->db->now()], ['id' => $userIds]);
    }

    public function getFoodsaverLastPassDate(int $fsId): ?\DateTime
    {
        $lastPass = $this->db->fetchValueByCriteria('fs_foodsaver', 'last_pass', ['id' => $fsId]);

        return $lastPass ? Carbon::parse($lastPass) : null; // 'Y-m-d H:i:s'
    }
}
