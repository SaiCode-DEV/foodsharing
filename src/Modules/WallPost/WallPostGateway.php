<?php

namespace Foodsharing\Modules\WallPost;

use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\WallPost\DTO\WallPost;

class WallPostGateway extends BaseGateway
{
    private array $targets = [
        'bezirk',
        'event',
        'fairteiler',
        'foodsaver',
        'fsreports',
        'question',
        'usernotes',
        // 'store', // table exists, no access for now
    ];

    private string $selectColumns = 'post.id, post.time, post.body, post.attach, foodsaver.id AS foodsaver_id, foodsaver.name, foodsaver.photo';

    public function addPost(WallPost $wallPost, int $foodsaverId, string $target, int $targetId): int
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

    public function deletePost(int $postId, string $target): int
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

    public function getPosts(string $target, int $targetId, int $limit = 50): array
    {
        $posts = $this->db->fetchAll("SELECT {$this->selectColumns}
		    FROM fs_wallpost post
            INNER JOIN fs_foodsaver foodsaver ON post.foodsaver_id = foodsaver.id
            INNER JOIN {$this->getLinkTableName($target)} has_post ON post.id = has_post.wallpost_id
			WHERE has_post.`{$this->getLinkTableForeignIdColumnName($target)}` = :targetId
			ORDER BY post.time DESC
			LIMIT :limit
		", ['targetId' => $targetId, 'limit' => $limit]);

        return array_map([WallPost::class, 'createFromArray'], $posts);
    }

    public function getAuthorId(int $postId): ?int
    {
        return $this->db->fetchValueByCriteria('fs_wallpost', 'foodsaver_id', ['id' => $postId]);
    }

    public function isLinkedToTarget(int $postId, string $target, int $targetId): bool
    {
        return $this->db->exists(
            $this->getLinkTableName($target),
            [
                'wallpost_id' => $postId,
                $this->getLinkTableForeignIdColumnName($target) => $targetId
            ]
        );
    }

    private function linkPost(int $postId, string $target, int $targetId): void
    {
        $this->db->insert($this->getLinkTableName($target), [$this->getLinkTableForeignIdColumnName($target) => $targetId, 'wallpost_id' => $postId]);
    }

    private function unlinkPost(int $postId, string $target): int
    {
        return $this->db->delete($this->getLinkTableName($target), ['wallpost_id' => $postId]);
    }

    private function assertIsValidTarget(string $target): void
    {
        if (!in_array($target, $this->targets, true)) {
            throw new \Exception('Invalid wall target');
        }
    }

    private function getLinkTableName(string $target): string
    {
        $this->assertIsValidTarget($target);

        return "fs_{$target}_has_wallpost";
    }

    private function getLinkTableForeignIdColumnName(string $target): string
    {
        $this->assertIsValidTarget($target);

        return $target . '_id';
    }

    public function countPosts(string $target, int $targetId): int
    {
        return $this->db->count(
            $this->getLinkTableName($target),
            [$this->getLinkTableForeignIdColumnName($target) => $targetId]
        );
    }
}
