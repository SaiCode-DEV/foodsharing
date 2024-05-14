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

    public function passGen(int $bot_id, int $fsid): int
    {
        return $this->db->insert('fs_pass_gen', [
            'foodsaver_id' => $fsid,
            'date' => $this->db->now(),
            'bot_id' => $bot_id,
        ]);
    }

    public function updateLastGen(array $foodsaver): int
    {
        return $this->db->update('fs_foodsaver', ['last_pass' => $this->db->now()], ['id' => $foodsaver]);
    }

    public function getLastGen(int $fsId): ?\DateTime
    {
        $lastPass = $this->db->fetchValueByCriteria('fs_foodsaver', 'last_pass', ['id' => $fsId]);

        return $lastPass ? Carbon::parse($lastPass) : null; // 'Y-m-d H:i:s'
    }
}
