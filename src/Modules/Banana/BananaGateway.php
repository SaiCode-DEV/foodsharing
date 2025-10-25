<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Banana;

use DateTime;
use Foodsharing\Modules\Banana\DTO\Banana;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Foodsaver\Profile;

class BananaGateway extends BaseGateway
{
    public function __construct(
        Database $db,
    ) {
        parent::__construct($db);
    }

    public function addBanana(int $recipientId, int $senderId, string $message): int
    {
        return $this->db->insert('fs_rating', [
            'rater_id' => $senderId,
            'foodsaver_id' => $recipientId,
            'msg' => $message,
            'time' => $this->db->now(),
        ]);
    }

    public function getBanana(int $recipientId, int $senderId): ?Banana
    {
        $data = $this->db->fetch('SELECT
                    fs.id, fs.name, fs.photo, fs.is_sleeping, r.`msg`, r.`time`
            FROM `fs_rating` r
            INNER JOIN `fs_foodsaver` fs ON fs.id = r.rater_id
            WHERE r.rater_id = :senderId AND r.foodsaver_id = :recipientId',
            [':recipientId' => $recipientId, ':senderId' => $senderId]
        );

        return empty($data) ? null : Banana::create($data['msg'], new Profile($data), new DateTime($data['time']));
    }

    public function hasGivenBanana(int $recipientId, int $senderId): bool
    {
        return $this->db->exists('fs_rating', ['foodsaver_id' => $recipientId, 'rater_id' => $senderId]);
    }

    public function deleteBanana(int $recipientId, int $senderId): bool
    {
        return $this->db->delete('fs_rating', ['foodsaver_id' => $recipientId, 'rater_id' => $senderId]) > 0;
    }

    public function getReceivedBananasCount(int $recipientId): int
    {
        return $this->db->count('fs_rating', ['foodsaver_id' => $recipientId]);
    }

    /**
     * @return Banana[] The bananas reveived by the given user
     */
    public function getReceivedBananas(int $recipientId): array
    {
        $data = $this->db->fetchAll('SELECT
                    fs.id, fs.name, fs.photo, fs.is_sleeping, r.`msg`, r.`time`
            FROM `fs_foodsaver` fs
            INNER JOIN `fs_rating` r
            WHERE 	r.rater_id = fs.id
            AND r.foodsaver_id = :recipientId
            ORDER BY time DESC',
            [':recipientId' => $recipientId]
        );

        return array_map(fn ($d) => Banana::create($d['msg'], new Profile($d), new DateTime($d['time'])), $data);
    }

    /**
     * @return Banana[] The bananas send by the given user
     */
    public function getSentBananas(int $senderId): array
    {
        $data = $this->db->fetchAll('SELECT
                    fs.id, fs.name, fs.photo, fs.is_sleeping, r.`msg`, r.`time`
            FROM `fs_foodsaver` fs
            INNER JOIN `fs_rating` r
            WHERE 	r.foodsaver_id = fs.id
            AND r.rater_id = :senderId
            ORDER BY time DESC',
            [':senderId' => $senderId]
        );

        return array_map(fn ($d) => Banana::create($d['msg'], new Profile($d), new DateTime($d['time'])), $data);
    }
}
