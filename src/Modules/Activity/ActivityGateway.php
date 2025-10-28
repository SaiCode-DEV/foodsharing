<?php

namespace Foodsharing\Modules\Activity;

use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\DBConstants\Mailbox\MailboxFolder;

class ActivityGateway extends BaseGateway
{
    private const int ITEMS_PER_PAGE = 10;

    public function fetchAllFoodSharePointWallUpdates(int $fsId, int $page): array
    {
        $stm = '
			select w_id as id,
				f_name as name,
				region_id,
				fsp_location,
				w.body,
				w.time,
				w.attach,
				UNIX_TIMESTAMP(w.time) AS time_ts,
				fs.id AS fs_id,
				fs.name AS fs_name,
				fs.photo AS fs_photo,
				fsp_id
			from (SELECT
				max(hw.wallpost_id) as w_id,
				f.name as f_name,
				f.bezirk_id AS region_id,
				f.ort AS fsp_location,
				f.id AS fsp_id
			FROM
				fs_fairteiler_follower ff
				left outer join fs_fairteiler f on ff.fairteiler_id = f.id
				left outer join fs_fairteiler_has_wallpost hw on hw.fairteiler_id = f.id
		 	where
				ff.foodsaver_id = :foodsaver_id
			group by hw.fairteiler_id) source
			left outer join fs_wallpost w on source.w_id = w.id
			left outer join fs_foodsaver fs on w.foodsaver_id = fs.id
			where
				w.id IS NOT NULL
			order by w.id desc
			LIMIT :start_item_index, :items_per_page';
        $posts = $this->db->fetchAll(
            $stm,
            [
                ':foodsaver_id' => $fsId,
                ':start_item_index' => $page * self::ITEMS_PER_PAGE,
                ':items_per_page' => self::ITEMS_PER_PAGE,
            ]
        );

        foreach ($posts as &$post) {
            $this->formatImages($post);
        }

        return $posts;
    }

    public function fetchAllFriendWallUpdates(array $buddyIds, int $page): array
    {
        $stm = '
		SELECT
			w.id,
			w.body,
			w.time,
			w.attach,
			UNIX_TIMESTAMP(w.time) AS time_ts,
			fs.id AS fs_id,
			fs.name AS fs_name,
			fs.photo AS fs_photo,
			poster.id AS poster_id,
			poster.name AS poster_name
        FROM
        	(
        	select
            	max(hw.wallpost_id) as w_id
            from
				fs_foodsaver_has_wallpost hw
            where
				hw.foodsaver_id IN(' . implode(',', $buddyIds) . ')
            group by hw.foodsaver_id) source
		left outer join fs_wallpost w on source.w_id = w.id
		left outer join fs_foodsaver fs on w.foodsaver_id = fs.id
		LEFT outer JOIN fs_foodsaver poster ON w.foodsaver_id = poster.id
		WHERE
			w.id IS NOT NULL
		ORDER BY w.id DESC
		LIMIT :start_item_index, :items_per_page
		';

        $posts = $this->db->fetchAll(
            $stm,
            [
                ':start_item_index' => $page * self::ITEMS_PER_PAGE,
                ':items_per_page' => self::ITEMS_PER_PAGE
            ]
        );

        foreach ($posts as &$post) {
            $this->formatImages($post);
        }

        return $posts;
    }

    public function fetchAllMailboxUpdates(array $mb_ids, int $page): array
    {
        $stm = '
				SELECT
					m.id,
					m.sender,
					m.subject,
					m.body,
					m.time,
					UNIX_TIMESTAMP(m.time) AS time_ts,
					m.mailbox_id,
					b.name AS mb_name

				FROM
					fs_mailbox_message m
				LEFT JOIN
					fs_mailbox b
				ON b.id = m.mailbox_id

				WHERE
					m.mailbox_id IN(' . implode(',', $mb_ids) . ')
				AND
				    m.folder <> ' . MailboxFolder::FOLDER_TRASH . '

				ORDER BY m.id DESC
			LIMIT :start_item_index, :items_per_page
		';

        return $this->db->fetchAll(
            $stm,
            [
                ':start_item_index' => $page * self::ITEMS_PER_PAGE,
                ':items_per_page' => self::ITEMS_PER_PAGE
            ]
        );
    }

    public function fetchAllForumUpdates(array $regionIds, int $page, $isAmbassadorThread = false): array
    {
        $stm = '
			SELECT	t.id,
					t.name,
					t.`time`,
					UNIX_TIMESTAMP(t.`time`) AS time_ts,
					fs.id AS foodsaver_id,
					fs.name AS foodsaver_name,
					fs.photo AS foodsaver_photo,
					p.body AS post_body,
					p.`time` AS update_time,
					UNIX_TIMESTAMP(p.`time`) AS update_time_ts,
					t.last_post_id,
					bt.bezirk_id,
					b.name AS bezirk_name,
					bt.bot_theme
				FROM (
					SELECT t.id
					FROM fs_theme t
					JOIN fs_bezirk_has_theme bt
						ON bt.theme_id = t.id
						AND bt.bezirk_id IN (' . implode(',', $regionIds) . ')
						AND bt.bot_theme = :isAmbassadorThread
					WHERE t.active = 1
					ORDER BY t.last_post_id DESC
					LIMIT :start_item_index, :items_per_page
				) AS tt
				JOIN fs_theme t ON t.id = tt.id
				JOIN fs_theme_post p ON p.id = t.last_post_id AND p.hidden_time IS NULL
				JOIN fs_bezirk_has_theme bt ON bt.theme_id = t.id
				JOIN fs_bezirk b ON b.id = bt.bezirk_id
				JOIN fs_foodsaver fs ON fs.id = p.foodsaver_id AND fs.deleted_at IS NULL
				ORDER BY t.last_post_id DESC;
		';

        return $this->db->fetchAll(
            $stm,
            [
                ':start_item_index' => $page * self::ITEMS_PER_PAGE,
                ':items_per_page' => self::ITEMS_PER_PAGE,
                ':isAmbassadorThread' => $isAmbassadorThread ? 1 : 0
            ]
        );
    }

    public function fetchAllStoreUpdates(int $fsId, int $page): array
    {
        $stm = 'SELECT
                n.id,
                n.`body` as `text`,
                UNIX_TIMESTAMP( n.`time` ) AS update_time_ts,
                fs.name AS foodsaver_name,
                fs.is_sleeping AS foodsaver_is_sleeping,
                fs.id AS foodsaver_id,
                fs.photo AS foodsaver_photo,
                b.id AS betrieb_id,
                b.name AS betrieb_name,
                b.stadt AS region_name
            FROM (
                SELECT max(n.id) AS last_post_id
                FROM fs_wallpost n
								INNER JOIN fs_store_has_wallpost hp ON hp.wallpost_id = n.id  
                INNER JOIN fs_betrieb_team bt ON bt.betrieb_id = hp.store_id
                WHERE bt.active = 1 AND bt.foodsaver_id = :foodsaver_id
                GROUP BY hp.store_id
                ) source
            INNER JOIN fs_wallpost n ON n.id = last_post_id
						INNER JOIN fs_store_has_wallpost hp ON hp.wallpost_id = n.id  
            INNER JOIN fs_foodsaver fs ON fs.id = n.foodsaver_id
            INNER JOIN fs_betrieb b ON b.id = hp.store_id
            ORDER BY n.id DESC
            LIMIT :start_item_index, :items_per_page
		';

        return $this->db->fetchAll(
            $stm,
            [
                ':foodsaver_id' => $fsId,
                ':start_item_index' => $page * self::ITEMS_PER_PAGE,
                ':items_per_page' => self::ITEMS_PER_PAGE,
            ]
        );
    }

    public function fetchAllBuddies(array $buddyIds): array
    {
        $stm = 'SELECT photo,name,id FROM fs_foodsaver WHERE id IN(' . implode(',', $buddyIds) . ')';

        return $this->db->fetchAll($stm);
    }

    public function fetchAllEventUpdates(int $fsId, int $page): array
    {
        $stm = '
		select
			w_id as id,
			e_name as name,
			w.body,
			w.time,
			w.attach,
			UNIX_TIMESTAMP(w.time) AS time_ts,
			fs.id AS fs_id,
			fs.name AS fs_name,
			fs.photo AS fs_photo,
			event_region,
			event_id
       	from
			(SELECT
				max(hw.wallpost_id) as w_id,
				e.name as e_name,
				e.id AS event_id,
                b.name AS event_region
			FROM
				fs_foodsaver_has_event fhe
				left outer join fs_event e on fhe.event_id = e.id
				left outer join fs_event_has_wallpost hw on hw.event_id = e.id
				left outer join fs_bezirk b on e.bezirk_id = b.id
            where
				fhe.foodsaver_id = :foodsaver_id
			AND
				e.end > now()
			AND
				fhe.status <> 3
            group by hw.event_id
			) source
		left outer join fs_wallpost w on source.w_id = w.id
		left outer join fs_foodsaver fs on w.foodsaver_id = fs.id
		WHERE
			w.id IS NOT NULL
		ORDER BY w.id DESC
			LIMIT :start_item_index, :items_per_page
			';
        $events = $this->db->fetchAll(
            $stm,
            [
                ':foodsaver_id' => $fsId,
                ':start_item_index' => $page * self::ITEMS_PER_PAGE,
                ':items_per_page' => self::ITEMS_PER_PAGE,
            ]
        );
        foreach ($events as &$event) {
            $this->formatImages($event);
        }

        return $events;
    }

    private function formatImages(array &$post): void
    {
        if (!empty($post['attach'])) {
            $data = json_decode((string)$post['attach'], true);
            if (isset($data['image'])) { // Legacy images
                $post['gallery'] = array_column($data['image'], 'file');
            } elseif (isset($data['images'])) {
                $post['gallery'] = $data['images'];
            }
        }
    }
}
