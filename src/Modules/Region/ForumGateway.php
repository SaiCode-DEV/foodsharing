<?php

namespace Foodsharing\Modules\Region;

use Carbon\Carbon;
use Exception;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;
use Foodsharing\Modules\Core\DBConstants\Region\ThreadStatus;
use Foodsharing\Modules\Core\PaginatedContent;
use Foodsharing\Modules\Core\Pagination;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\Region\DTO\ForumPost;
use Foodsharing\Modules\Region\DTO\ForumPostSummary;
use Foodsharing\Modules\Region\DTO\ForumThread;
use Foodsharing\Modules\Region\DTO\ForumThreadForListView;
use Foodsharing\Modules\Region\Exceptions\NoVisiblePostException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class ForumGateway extends BaseGateway
{
    public function __construct(
        Database $db,
        private readonly ForumFollowerGateway $forumFollowerGateway,
        protected TranslatorInterface $translator,
    ) {
        parent::__construct($db);
    }

    // Thread-related
    public function getThreadInfo(int $threadId): array
    {
        return $this->db->fetch('
		SELECT		t.name as title,
					bt.bezirk_id as region_id,
					bt.bot_theme as ambassador_forum
		FROM		fs_theme t
		LEFT JOIN   fs_bezirk_has_theme bt ON bt.theme_id = t.id
		WHERE		t.id = :thread_id
		', ['thread_id' => $threadId]);
    }

    /**
     * @return ForumThread where `permissions`, `subscriptionsStatus` and `posts` is not initialized
     */
    public function getThread(int $threadId): ForumThread
    {
        $threadData = $this->db->fetch('SELECT
                t.`id`, t.`name`, t.`sticky`, t.`status`, t.`active`,
                t.`foodsaver_id`, t.`last_post_id`,
                r.`bezirk_id` AS regionId, r.`bot_theme` AS subforumId
			FROM fs_theme t
			JOIN fs_bezirk_has_theme AS r ON r.theme_id = t.id
			WHERE 		t.id = :thread_id',
            ['thread_id' => $threadId]
        );

        if (!$threadData) {
            throw new DatabaseNoValueFoundException();
        }

        $thread = new ForumThread();
        $thread->id = $threadData['id'];
        $thread->title = $threadData['name'];
        $thread->pinnedLevel = $threadData['sticky'];
        $thread->isLocked = ($threadData['status'] === ThreadStatus::CLOSED);
        $thread->isActive = (bool)$threadData['active'];
        $thread->regionId = $threadData['regionId'];
        $thread->subforumId = $threadData['subforumId'];
        $thread->creatorId = $threadData['foodsaver_id'];
        $thread->lastPostId = $threadData['last_post_id'];

        return $thread;
    }

    public function addThread($foodsaverId, $regionId, $title, $body, $isActive, $ambassadorForum = false)
    {
        $isAmbassadorForum = $ambassadorForum ? 1 : 0;
        $threadId = $this->db->insert('fs_theme', [
            'foodsaver_id' => $foodsaverId,
            'name' => $title,
            'time' => date('Y-m-d H:i:s'),
            'active' => $isActive,
            'status' => ThreadStatus::OPEN,
        ]);

        $this->forumFollowerGateway->followThreadByBell($foodsaverId, $threadId);

        $this->db->insert('fs_bezirk_has_theme', [
            'bezirk_id' => $regionId,
            'theme_id' => $threadId,
            'bot_theme' => $isAmbassadorForum
        ]);

        $this->addPost($foodsaverId, $threadId, $body);

        return $threadId;
    }

    /**
     * Activates a thread (sets active = 1).
     * @return bool if the thread was activated, false if it was already active
     */
    public function activateThread(int $threadId): bool
    {
        return $this->db->update('fs_theme', ['active' => 1], ['id' => $threadId, 'active' => 0]) > 0;
    }

    public function deleteThread($thread_id)
    {
        $this->db->delete('fs_theme_post', ['theme_id' => $thread_id]);
        $this->db->delete('fs_theme', ['id' => $thread_id]);
    }

    public function getBotThreadStatus($thread_id)
    {
        return $this->db->fetch('
			SELECT  ht.bot_theme,
					ht.bezirk_id
			FROM
					fs_bezirk_has_theme ht
			WHERE   ht.theme_id = :theme_id
		', ['theme_id' => $thread_id]);
    }

    public function setStickiness(int $thread_id, int $stickiness)
    {
        return $this->db->update(
            'fs_theme',
            ['sticky' => $stickiness],
            ['id' => $thread_id]
        );
    }

    /**
     * Returns the {@see ThreadStatus} of a thread. Throws an exception if the thread does not exist.
     */
    public function getThreadStatus(int $threadId): int
    {
        return $this->db->fetchValueByCriteria('fs_theme', 'status', ['id' => $threadId]);
    }

    /**
     * Sets the status of a thread and returns whether the status was set successfully, see {@see ThreadStatus}.
     */
    public function setThreadStatus(int $threadId, int $status): bool
    {
        return $this->db->update('fs_theme', ['status' => $status], ['id' => $threadId]) > 0;
    }

    /**
     * Sets the title of a thread and returns whether the title was set successfully.
     */
    public function setThreadTitle(int $threadId, string $title): bool
    {
        return $this->db->update('fs_theme', ['name' => $title], ['id' => $threadId]) > 0;
    }

    // Post-related

    public function addPost($fs_id, $thread_id, $body)
    {
        // First, check if the exact same post already exists AND is the last post in the thread.
        $lastPostBody = $this->db->fetchAllValues('
            SELECT body FROM fs_theme_post
            WHERE theme_id = :thread_id
            AND foodsaver_id = :fs_id
            ORDER BY time DESC
            LIMIT 1', [
            'thread_id' => $thread_id,
            'fs_id' => $fs_id
        ]);

        // If so, raise an exception to prevent duplicate posts.
        if (count($lastPostBody) > 0 && $lastPostBody[0] === $body) {
            throw new ConflictHttpException('Duplicate post detected');
        }
        // If not, insert the new post.
        $post_id = $this->db->insert(
            'fs_theme_post',
            [
                'theme_id' => $thread_id,
                'foodsaver_id' => $fs_id,
                'body' => $body,
                'time' => date('Y-m-d H:i:s')
            ]
        );

        $this->db->update('fs_theme', ['last_post_id' => $post_id], ['id' => $thread_id]);

        return $post_id;
    }

    private function getPostSelect()
    {
        return "SELECT
                fs.id AS author_id,
                IF(fs.deleted_at IS NOT NULL,\"{$this->translator->trans('forum.deleted_user')}\", fs.name) AS author_name,
                fs.photo AS author_photo,
                fs.is_sleeping AS author_is_sleeping,
                p.body AS body,
                p.`time`,
                p.id,
                UNIX_TIMESTAMP(p.`time`) AS time_ts,
                p.hidden_reason,
                moderator.id AS moderator_id,
                moderator.name AS moderator_name,
                p.hidden_time,
                b.`type` AS region_type
			FROM fs_theme_post p
			INNER JOIN fs_foodsaver fs ON p.foodsaver_id = fs.id
			LEFT JOIN fs_bezirk_has_theme ht ON ht.theme_id = p.theme_id
			LEFT JOIN fs_bezirk b ON b.id = ht.bezirk_id
            LEFT OUTER JOIN fs_foodsaver moderator ON moderator.id = p.hidden_by";
    }

    /**
     * @param int[] $postIds
     */
    public function getReactionsForPosts(array $postIds)
    {
        return $this->db->fetchAll("SELECT
                r.`post_id`,
                r.`key`,
                r.`foodsaver_id`, fs.`name` as foodsaver_name
			FROM fs_post_reaction r
			LEFT JOIN fs_foodsaver fs ON fs.`id` = r.`foodsaver_id`
			WHERE r.`post_id` IN ({$this->db->generatePlaceholders(count($postIds))})
            ORDER BY r.`time`",
            $postIds
        );
    }

    public function addReaction($postId, $fsId, $key): bool
    {
        $this->db->insertOrUpdate(
            'fs_post_reaction',
            [
                'post_id' => $postId,
                'foodsaver_id' => $fsId,
                'key' => $key,
                'time' => $this->db->now()
            ]
        );

        return true;
    }

    public function removeReaction($postId, $fsId, $key)
    {
        $this->db->delete(
            'fs_post_reaction',
            [
                'post_id' => $postId,
                'foodsaver_id' => $fsId,
                'key' => $key
            ]
        );
    }

    /**
     * @return ForumPost[]
     */
    public function listPosts($threadId): array
    {
        $posts = $this->db->fetchAll(
            $this->getPostSelect() . '
			WHERE p.theme_id = :threadId
			ORDER BY p.`time`
		', ['threadId' => $threadId]);
        $posts = array_map(fn ($post) => ForumPost::createFromArray($post), $posts);

        return $posts;
    }

    public function getPost($postId)
    {
        return $this->db->fetch(
            $this->getPostSelect() . '
			WHERE 		p.id = :postId

			ORDER BY 	p.`time`
		', ['postId' => $postId]);
    }

    public function deletePost($id)
    {
        $this->db->delete('fs_theme_post', ['id' => $id]);
    }

    public function hidePost(int $postId, int $moderatorId, string $reason): void
    {
        $this->db->update('fs_theme_post', [
            'hidden_time' => $this->db->now(),
            'hidden_by' => $moderatorId,
            'hidden_reason' => $reason,
        ], ['id' => $postId]);
    }

    /**
     * Udates the saved last post for given thread id.
     * Use setLastPostId instead if the new last_post_id is known.
     * @throws NoVisiblePostException
     */
    public function updateLastPostId(int $threadId): void
    {
        $lastPostId = $this->db->fetchValue('SELECT MAX(`id`)
            FROM fs_theme_post p
            WHERE theme_id = ?
            AND p.hidden_time IS NULL',
            [$threadId]);
        if ($lastPostId) {
            $this->setLastPostId($threadId, $lastPostId);
        } else {
            throw new NoVisiblePostException();
        }
    }

    public function setLastPostId(int $threadId, int $postId): void
    {
        $this->db->update('fs_theme', ['last_post_id' => $postId], ['id' => $threadId]);
    }

    public function getThreadIdForPost(int $postId): int
    {
        return $this->db->fetchValueById('fs_theme_post', 'theme_id', $postId);
    }

    public function restorePost(int $postId): bool
    {
        return $this->db->update('fs_theme_post', [
            'hidden_time' => null,
            'hidden_by' => null,
            'hidden_reason' => null,
        ], ['id' => $postId]) > 0;
    }

    public function isPostHidden(int $postId): bool
    {
        return !$this->db->exists('fs_theme_post', ['id' => $postId, 'hidden_by' => null]);
    }

    public function getRegionForPost($post_id)
    {
        return $this->db->fetchValue('
			SELECT 	bt.bezirk_id

			FROM 	fs_bezirk_has_theme bt,
					fs_theme_post tp,
					fs_theme t
			WHERE 	t.id = tp.theme_id
			AND 	t.id = bt.theme_id
			AND 	tp.id = :id
		', ['id' => $post_id]);
    }

    public function getForumsForThread($threadId)
    {
        return $this->db->fetchAll('
		SELECT
			bt.bezirk_id AS forumId,
			bt.bot_theme AS forumSubId
		FROM
			fs_bezirk_has_theme bt

		WHERE bt.theme_id = :threadId
		', ['threadId' => $threadId]);
    }

    public function getThreadForPost(int $postId): ?int
    {
        $threadId = $this->db->fetchByCriteria('fs_theme_post',
            ['theme_id'],
            ['id' => $postId]
        );
        if (empty($threadId)) {
            return null;
        } else {
            return $threadId['theme_id'];
        }
    }

    /**
     * @return PaginatedContent with entrys of type ForumThreadForListView
     */
    public function getForumThreadsForListView(int $regionId, int $subforumId, Pagination $pagination): PaginatedContent
    {
        $params = [
            'regionId' => $regionId,
            'subforumId' => $subforumId,
        ];
        $threadsData = $this->db->fetchAll('SELECT
                t.`id`, t.`name`, t.`sticky`, t.`status`,
                p.`time` AS lastPostTime,
                fs.`id` AS fs_id, fs.`name` AS fs_name, fs.`photo` AS fs_photo, fs.`is_sleeping` AS fs_is_sleeping,
                COUNT(*) OVER() AS totalCount
            FROM fs_theme t
            JOIN fs_theme_post p ON p.`id` = t.`last_post_id`
            JOIN fs_foodsaver fs ON fs.`id` = p.`foodsaver_id`
            JOIN fs_bezirk_has_theme r ON r.`theme_id` = t.`id`
            WHERE r.`bezirk_id` = :regionId
                AND r.`bot_theme` = :subforumId
                AND t.`active` = 1
            ORDER BY t.`sticky` DESC, p.`time` DESC
            ' . $this->buildPaginationSqlLimit($pagination),
            $this->addPaginationSqlLimitParameters($pagination, $params),
        );

        if (empty($threadsData)) {
            return PaginatedContent::create(0, $pagination->offset, []);
        }
        $totalCount = $threadsData[0]['totalCount'];

        $threads = array_map(function ($threadData) {
            $thread = new ForumThreadForListView();
            $thread->id = $threadData['id'];
            $thread->title = $threadData['name'];
            $thread->pinnedLevel = $threadData['sticky'];
            $thread->isLocked = ($threadData['status'] === ThreadStatus::CLOSED);
            $thread->latestPost = new ForumPostSummary();
            $thread->latestPost->createdAt = new Carbon($threadData['lastPostTime']);
            $thread->latestPost->author = new Profile($threadData, 'fs_');

            return $thread;
        }, $threadsData);

        return PaginatedContent::create($totalCount, $pagination->offset, $threads);
    }
}
