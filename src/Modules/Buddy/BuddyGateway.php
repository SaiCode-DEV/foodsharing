<?php

namespace Foodsharing\Modules\Buddy;

use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\DBConstants\Buddy\BuddyId;

class BuddyGateway extends BaseGateway
{
    public function listBuddies($fsId): array
    {
        $stm = '
            SELECT
                b.foodsaver_id AS fsId, b.buddy_id AS buddyId,
                fs.name, fs.photo, b.confirmed
            FROM fs_buddy b
            JOIN fs_foodsaver fs ON fs.id = (CASE WHEN b.foodsaver_id = :fsId THEN b.buddy_id ELSE b.foodsaver_id END)
            WHERE b.foodsaver_id = :fsId OR b.buddy_id = :fsId
        ';

        return $this->db->fetchAll($stm, [':fsId' => $fsId]);
    }

    public function listBuddyIds($fsId): array
    {
        return $this->db->fetchAllValuesByCriteria('fs_buddy', 'buddy_id', ['foodsaver_id' => $fsId, 'confirmed' => 1]);
    }

    public function removeRequest(int $foodsaverId, int $buddyId): void
    {
        $this->db->delete('fs_buddy', ['foodsaver_id' => $foodsaverId, 'buddy_id' => $buddyId]);
    }

    public function hasSentBuddyRequest(int $buddyId, int $foodsaverId): bool
    {
        return $this->db->exists('fs_buddy', ['foodsaver_id' => $buddyId, 'buddy_id' => $foodsaverId]);
    }

    public function buddyRequest(int $buddyId, int $foodsaverId): bool
    {
        $this->db->insertOrUpdate('fs_buddy', [
            'foodsaver_id' => $foodsaverId,
            'buddy_id' => $buddyId,
            'confirmed' => BuddyId::REQUESTED
        ]);

        return true;
    }

    public function confirmBuddy(int $buddyId, int $foodsaverId): void
    {
        $this->db->insertOrUpdate('fs_buddy', [
            'foodsaver_id' => $foodsaverId,
            'buddy_id' => $buddyId,
            'confirmed' => BuddyId::BUDDY
        ]);
        $this->db->insertOrUpdate('fs_buddy', [
            'foodsaver_id' => $buddyId,
            'buddy_id' => $foodsaverId,
            'confirmed' => BuddyId::BUDDY
        ]);
    }

    public function unconfirmBuddy(int $buddyId, int $foodsaverId): void
    {
        $this->db->update('fs_buddy', [
            'confirmed' => BuddyId::REQUESTED
        ], [
            'foodsaver_id' => $foodsaverId,
            'buddy_id' => $buddyId,
        ]);
    }
}
