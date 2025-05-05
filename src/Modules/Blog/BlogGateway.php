<?php

namespace Foodsharing\Modules\Blog;

use Carbon\Carbon;
use DateTimeZone;
use Exception;
use Foodsharing\Modules\Blog\DTO\BlogPost;
use Foodsharing\Modules\Blog\DTO\BlogPostList;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Permissions\BlogPermissions;
use Foodsharing\RestApi\Models\Blog\BlogPostData;
use Foodsharing\Utility\Sanitizer;

final class BlogGateway extends BaseGateway
{
    public function __construct(
        Database $db,
        private readonly Sanitizer $sanitizer,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
        private readonly BlogPermissions $blogPermissions,
    ) {
        parent::__construct($db);
    }

    public function setPublished(int $blogId, bool $isPublished): int
    {
        return $this->db->update('fs_blog_entry', ['active' => intval($isPublished)], ['id' => $blogId]);
    }

    public function update_blog_entry(int $userId, BlogPostData $data): int
    {
        $data_stripped = [
            'bezirk_id' => $data->regionId,
            'foodsaver_id' => $userId,
            'name' => strip_tags($data->title),
            'teaser' => strip_tags($data->teaser),
            'body' => $this->sanitizer->purifyHtml($data->content),
            'time' => Carbon::now()->format('Y-m-d H:i:s'),
        ];

        if (!empty($data->picture)) {
            $data_stripped['picture'] = strip_tags((string)$data->picture);
        }

        return $this->db->update(
            'fs_blog_entry',
            $data_stripped,
            ['id' => $data->id]
        );
    }

    public function getPostAuthor(int $article_id): bool|int
    {
        try {
            $val = $this->db->fetchByCriteria('fs_blog_entry', ['foodsaver_id'], ['id' => $article_id]);
        } catch (Exception) {
            // has to be caught until we can check whether a to be fetched value does really exist.
        }

        return $val['foodsaver_id'] ?? false;
    }

    /**
     * Returns the blog post with the given id, or null if the id does not exist or if the post is not activated.
     *
     * @param int $id id of the post
     * @param bool $onlyActive if false, this will also return a deactivated post, if it exists
     */
    public function getPost(int $id, bool $onlyActive = true): ?BlogPost
    {
        $filter = $onlyActive ? 'AND b.`active` = 1' : '';

        $blogPost = $this->db->fetch('
			SELECT
				b.`id`,
				b.`name`,
				b.`time`,
				UNIX_TIMESTAMP(b.`time`) AS time_ts,
                b.`teaser`,
				b.`body`,
				b.`picture`,
				CONCAT(fs.name," ",fs.nachname) AS fs_name
			FROM
				`fs_blog_entry` b,
				`fs_foodsaver` fs
			WHERE
				b.foodsaver_id = fs.id
			' . $filter . '
			AND
				b.id = :fs_id',
            [':fs_id' => $id]);

        if (empty($blogPost)) {
            return null;
        }

        $blogPost['body'] = $this->sanitizer->purifyHtml($blogPost['body'] ?? '');

        return BlogPost::create(
            $blogPost['id'],
            $blogPost['name'],
            $blogPost['body'],
            $blogPost['teaser'],
            Carbon::createFromTimestamp($blogPost['time_ts'], new DateTimeZone('Europe/Berlin')),
            $blogPost['fs_name'],
            $blogPost['picture']
        );
    }

    /**
     * Returns a page of 10 posts from the list of blog posts. The page numbers start at 0. Instead of the full body,
     * the posts will only contain a teaser text.
     *
     * @throws Exception
     */
    public function listNews(int $page): BlogPostList
    {
        $postData = $this->db->fetchAll(
            '
			SELECT
				b.`id`,
				b.`name`,
				UNIX_TIMESTAMP(b.`time`) AS time_ts,
				b.`active`,
				b.`teaser`,
				b.`picture`,
				CONCAT(fs.name," ",fs.nachname) AS fs_name,
			COUNT(*) OVER () as totalPosts
			FROM
				`fs_blog_entry` b,
				`fs_foodsaver` fs
			WHERE
				b.foodsaver_id = fs.id
			AND
				b.`active` = 1
			ORDER BY
				b.`id` DESC
			LIMIT :page,10',
            [':page' => $page * 10]
        );

        $posts = array_map(fn ($post) => BlogPost::create(
            $post['id'],
            $post['name'],
            '', // skip body for overview, more performant
            $post['teaser'],
            Carbon::createFromTimestamp($post['time_ts'], new DateTimeZone('Europe/Berlin')),
            $post['fs_name'],
            $post['picture']
        ), $postData);

        return BlogPostList::create($posts, $postData[0]['totalPosts'] ?? 0);
    }

    public function getBlogpostList(): array
    {
        if ($this->blogPermissions->mayAdministrateBlog()) {
            $filter = '';
        } else {
            $ownRegionIds = implode(',', array_map('intval', $this->currentUserUnits->listRegionIDs()));
            $filter = 'WHERE `bezirk_id` IN (' . $ownRegionIds . ')';
        }

        return $this->db->fetchAll('
			SELECT 	 	b.`id`,
						b.`name`,
						b.`time`,
						UNIX_TIMESTAMP(b.`time`) AS time_ts,
						b.`active`,
						b.`teaser`,
						b.`bezirk_id`,
						fs.`id` AS foodsaver_id,
						fs.`name` AS foodsaver_name,
						fs.`photo` AS foodsaver_photo
			FROM 		`fs_blog_entry` b
            LEFT OUTER JOIN fs_foodsaver fs ON fs.id = b.foodsaver_id
			' . $filter . '
			ORDER BY `time` DESC');
    }

    public function del_blog_entry(int $id): int
    {
        return $this->db->delete('fs_blog_entry', ['id' => $id]);
    }

    public function getOne_blog_entry(int $id): array
    {
        $blogEntry = $this->db->fetch(
            '
			SELECT
			`id`,
			`bezirk_id`,
			`foodsaver_id`,
			`active`,
			`name`,
			`teaser`,
			`body`,
			`time`,
			UNIX_TIMESTAMP(`time`) AS time_ts,
			`picture`
			FROM 		`fs_blog_entry`
			WHERE 		`id` = :fs_id',
            [':fs_id' => $id]
        );

        $blogEntry['body'] = $this->sanitizer->purifyHtml($blogEntry['body'] ?? '');

        return $blogEntry;
    }

    public function addBlogPost(int $authorId, BlogPostData $data): int
    {
        $id = $this->db->insert(
            'fs_blog_entry',
            [
                'bezirk_id' => $data->regionId,
                'foodsaver_id' => $authorId,
                'name' => strip_tags($data->title),
                'teaser' => strip_tags($data->teaser),
                'body' => $this->sanitizer->purifyHtml($data->content),
                'time' => Carbon::now()->format('Y-m-d H:i:s'),
                'picture' => strip_tags((string)$data->picture),
                'active' => $data->isPublished ? 1 : 0,
            ]
        );

        return $id;
    }
}
