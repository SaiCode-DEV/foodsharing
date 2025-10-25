<?php

namespace Foodsharing\Modules\Team;

use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Team\DTO\TeamMember;

class TeamGateway extends BaseGateway
{
    /**
     * @return TeamMember[]
     */
    public function getTeam($region_id = RegionIDs::TEAM_BOARD_MEMBER): array
    {
        $stm = '
				SELECT
					fs.id,
					fs.name,
					fs.photo,
					fs.about_me_public,
					fs.position
				FROM
					fs_foodsaver_has_bezirk hb

				LEFT JOIN
					fs_foodsaver fs
				ON
					hb.foodsaver_id = fs.id

				LEFT JOIN
					fs_mailbox mb
				ON
					fs.mailbox_id = mb.id
				WHERE
					hb.bezirk_id = :region_id
				AND
				    hb.active = 1
				ORDER BY fs.name
		';
        $orgas = $this->db->fetchAll($stm, [':region_id' => $region_id]);

        return array_map(fn ($entry) => TeamMember::create(
            $entry['id'],
            $entry['name'],
            $entry['photo'] ?? null,
            $entry['about_me_public'],
            $entry['position']
        ), $orgas);
    }
}
