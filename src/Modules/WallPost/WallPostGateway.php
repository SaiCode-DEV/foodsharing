<?php

namespace Foodsharing\Modules\WallPost;

use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\DBConstants\WallType;
use Foodsharing\Modules\Core\Pagination;
use Foodsharing\Modules\WallPost\DTO\WallPost;

class WallPostGateway extends BaseGateway
{
    private string $selectColumns = 'post.id, DATE_FORMAT(CONVERT_TZ(post.time, "' . TIME_ZONE . '", "UTC"), "%Y-%m-%dT%TZ") as time, post.body, post.attach, foodsaver.id AS foodsaver_id, foodsaver.name AS foodsaver_name, foodsaver.photo AS foodsaver_photo, foodsaver.is_sleeping AS foodsaver_is_sleeping';

    public function addPost(WallPost $wallPost, int $foodsaverId, WallType $target, int $targetId): int
    {
        $attach = $wallPost->pictures ? json_encode(['images' => $wallPost->pictures]) : null;
        $postId = $this->db->insert('fs_wallpost', [
            'foodsaver_id' => $foodsaverId,
            'body' => $wallPost->body,
            'time' => $this->db->now(),
            'attach' => $attach,
        ]);
        $this->linkPost($postId, $target, $targetId);

        return $postId;
    }

    public function deletePost(int $postId, WallType $target): int
    {
        $this->unlinkPost($postId, $target);

        return $this->db->delete('fs_wallpost', ['id' => $postId]);
    }

    public function getPost(int $postId): WallPost
    {
        $post = $this->db->fetch("SELECT {$this->selectColumns}
			FROM fs_wallpost post
			LEFT JOIN fs_foodsaver foodsaver ON foodsaver.id = post.foodsaver_id
			WHERE post.id = :postId
			LIMIT 1
		", ['postId' => $postId]);

        return WallPost::createFromArray($post);
    }

    /**
     * @return WallPost[]
     */
    public function getPosts(WallType $target, int $targetId, Pagination $pagination): array
    {
        $posts = $this->db->fetchAll("SELECT {$this->selectColumns}
		    FROM fs_wallpost post
            INNER JOIN fs_foodsaver foodsaver ON post.foodsaver_id = foodsaver.id
            INNER JOIN {$this->getLinkTableName($target)} has_post ON post.id = has_post.wallpost_id
			WHERE has_post.`{$this->getLinkTableForeignIdColumnName($target)}` = :targetId
			ORDER BY post.time DESC
        " . $this->buildPaginationSqlLimit($pagination),
            $this->addPaginationSqlLimitParameters($pagination, ['targetId' => $targetId]));

        return array_map(WallPost::createFromArray(...), $posts);
    }

    /**
     * @param int[] $postIds
     */
    public function getReactionsForPosts(array $postIds): array
    {
        return $this->db->fetchAll("SELECT
			    r.`post_id`,
                r.`key`,
                r.`foodsaver_id`, fs.`name` as foodsaver_name
			FROM fs_wall_post_reaction r
			LEFT JOIN fs_foodsaver fs ON fs.`id` = r.`foodsaver_id`
			WHERE r.`post_id` IN ({$this->db->generatePlaceholders(count($postIds))})
            ORDER BY r.`time`",
            $postIds
        );
    }

    public function addReaction(int $postId, int $foodsaverId, string $key): void
    {
        $this->db->insertOrUpdate('fs_wall_post_reaction', [
            'post_id' => $postId,
            'foodsaver_id' => $foodsaverId,
            'key' => $key,
            'time' => $this->db->now(),
        ]);
    }

    public function removeReaction(int $postId, int $foodsaverId, string $key): void
    {
        $this->db->delete('fs_wall_post_reaction', [
            'post_id' => $postId,
            'foodsaver_id' => $foodsaverId,
            'key' => $key
        ]);
    }

    public function getAuthorId(int $postId): ?int
    {
        return $this->db->fetchValueByCriteria('fs_wallpost', 'foodsaver_id', ['id' => $postId]);
    }

    public function isLinkedToTarget(int $postId, WallType $target, int $targetId): bool
    {
        return $this->db->exists(
            $this->getLinkTableName($target),
            [
                'wallpost_id' => $postId,
                $this->getLinkTableForeignIdColumnName($target) => $targetId
            ]
        );
    }

    private function linkPost(int $postId, WallType $target, int $targetId): void
    {
        $this->db->insert($this->getLinkTableName($target), [$this->getLinkTableForeignIdColumnName($target) => $targetId, 'wallpost_id' => $postId]);
    }

    private function unlinkPost(int $postId, WallType $target): int
    {
        return $this->db->delete($this->getLinkTableName($target), ['wallpost_id' => $postId]);
    }

    private function getLinkTableName(WallType $target): string
    {
        if ($target === WallType::REGION) {
            $target = WallType::WORKING_GROUP;
        }

        return "fs_{$target->value}_has_wallpost";
    }

    private function getLinkTableForeignIdColumnName(WallType $target): string
    {
        if ($target === WallType::REGION) {
            $target = WallType::WORKING_GROUP;
        }

        return $target->value . '_id';
    }

    public function countPosts(WallType $target, int $targetId): int
    {
        return $this->db->count(
            $this->getLinkTableName($target),
            [$this->getLinkTableForeignIdColumnName($target) => $targetId]
        );
    }

    public function deletePostsForTarget(WallType $target, int $targetId): void
    {
        $this->db->execute("DELETE post
            FROM fs_wallpost post
            JOIN {$this->getLinkTableName($target)} link
                ON link.wallpost_id = post.id
            WHERE link.`{$this->getLinkTableForeignIdColumnName($target)}` = :targetId
        ", ['targetId' => $targetId]);
    }
}
