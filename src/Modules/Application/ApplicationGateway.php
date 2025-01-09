<?php

namespace Foodsharing\Modules\Application;

use Foodsharing\Modules\Application\DTO\WorkingGroupApplication;
use Foodsharing\Modules\Core\BaseGateway;

class ApplicationGateway extends BaseGateway
{
    private const int STATUS_NOT_ACTIVE = 0;
    private const int STATUS_ACTIVE = 1;

    /**
     * Returns the open application that a user sent to a working group, or null if the user did not apply to that group
     * or if the application was already accepted.
     */
    public function getApplication(int $regionId, int $fsId): ?WorkingGroupApplication
    {
        $stm = '
			SELECT 	fs.`id`,
					fs.`name`,
					fs.`is_sleeping`,
					fs.`photo`,
					fb.application
			FROM 	`fs_foodsaver_has_bezirk` fb,
					`fs_foodsaver` fs
			WHERE 	fb.foodsaver_id = fs.id
			AND 	fb.bezirk_id =  :region_id
			AND     fb.active = :not_active
			AND 	fb.foodsaver_id = :foodsaver_id
		';
        $data = $this->db->fetch($stm, [':region_id' => $regionId, ':foodsaver_id' => $fsId, ':not_active' => self::STATUS_NOT_ACTIVE]);

        return empty($data) ? null : WorkingGroupApplication::create($regionId, $data);
    }

    /**
     * Makes the user an active group member. This has no effect if the user did not apply or if the user already is an
     * active group member.
     */
    public function acceptApplication(int $regionId, int $foodsaverId): void
    {
        $this->updateActivityStatus($regionId, $foodsaverId, self::STATUS_ACTIVE);
    }

    /**
     * Deletes a user's application. This has no effect if the user did not apply or if the user already is an active
     * group member.
     */
    public function denyApplication(int $regionId, int $foodsaverId): void
    {
        $this->db->delete('fs_foodsaver_has_bezirk', [
            'bezirk_id' => $regionId,
            'foodsaver_id' => $foodsaverId,
            'active' => self::STATUS_NOT_ACTIVE,
        ]);
    }

    private function updateActivityStatus(int $regionId, int $foodsaverId, int $value): void
    {
        $this->db->update(
            'fs_foodsaver_has_bezirk',
            ['active' => $value],
            ['bezirk_id' => $regionId, 'foodsaver_id' => $foodsaverId]
        );
    }
}
