<?php

namespace Foodsharing\Modules\Team;

use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;

class TeamGateway extends BaseGateway
{
    public function getTeam($region_id = RegionIDs::TEAM_BOARD_MEMBER): array
    {
        $out = [];
        $stm = '
				SELECT
					fs.id,
					CONCAT(mb.name,"@' . PLATFORM_MAILBOX_HOST . '") AS email,
					fs.name,
					fs.photo,
					fs.about_me_public,
					fs.rolle,
					fs.geschlecht,
					fs.position,
					fs.contact_public
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
				ORDER BY fs.name
		';
        $orgas = $this->db->fetchAll($stm, [':region_id' => $region_id]);
        foreach ($orgas as $o) {
            $out[(int)$o['id']] = $o;
        }

        return $out;
    }
}
