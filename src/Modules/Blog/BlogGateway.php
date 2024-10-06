<?php

namespace Foodsharing\Modules\Blog;

use Carbon\Carbon;
use DateTimeZone;
use Exception;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Blog\DTO\BlogPost;
use Foodsharing\Modules\Blog\DTO\BlogPostList;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\RestApi\Models\Blog\BlogPostData;
use Foodsharing\Utility\Sanitizer;

final class BlogGateway extends BaseGateway
{
    private readonly BellGateway $bellGateway;
    private readonly FoodsaverGateway $foodsaverGateway;
    private readonly Sanitizer $sanitizerService;
    private readonly Session $session;

    public function __construct(
        BellGateway $bellGateway,
        Database $db,
        FoodsaverGateway $foodsaverGateway,
        Sanitizer $sanitizerService,
        Session $session,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
    ) {
        parent::__construct($db);
        $this->bellGateway = $bellGateway;
        $this->foodsaverGateway = $foodsaverGateway;
        $this->sanitizerService = $sanitizerService;
        $this->session = $session;
    }

    public function setPublished(int $blogId, bool $isPublished): int
    {
        return $this->db->update('fs_blog_entry', ['active' => intval($isPublished)], ['id' => $blogId]);
    }

    public function update_blog_entry(int $id, array $data): int
    {
        $data_stripped = [
            'bezirk_id' => $data['bezirk_id'],
            'foodsaver_id' => $data['foodsaver_id'],
            'name' => strip_tags((string)$data['name']),
            'teaser' => strip_tags((string)$data['teaser']),
            'body' => $data['body'],
            'time' => strip_tags((string)$data['time']),
        ];

        if (!empty($data['picture'])) {
            $data_stripped['picture'] = strip_tags((string)$data['picture']);
        }

        return $this->db->update(
            'fs_blog_entry',
            $data_stripped,
            ['id' => $id]
        );
    }

    public function getAuthorOfPost(int $article_id)
    {
        $val = false;
        try {
            $val = $this->db->fetchByCriteria('fs_blog_entry', ['bezirk_id', 'foodsaver_id'], ['id' => $article_id]);
        } catch (Exception) {
            // has to be caught until we can check whether a to be fetched value does really exist.
        }

        return $val;
    }

    /**
     * Returns the blog post with the given id, or null if the id does not exist.
     */
    public function getPost(int $id): ?BlogPost
    {
        $blogPost = $this->db->fetch('
			SELECT
				b.`id`,
				b.`name`,
				b.`time`,
				UNIX_TIMESTAMP(b.`time`) AS time_ts,
				b.`body`,
				b.`picture`,
				CONCAT(fs.name," ",fs.nachname) AS fs_name
			FROM
				`fs_blog_entry` b,
				`fs_foodsaver` fs
			WHERE
				b.foodsaver_id = fs.id
			AND
				b.`active` = 1
			AND
				b.id = :fs_id',
            [':fs_id' => $id]);

        if (empty($blogPost)) {
            return null;
        }

        $blogPost['body'] = $this->sanitizerService->purifyHtml($blogPost['body'] ?? '');

        return BlogPost::create(
            $blogPost['id'],
            $blogPost['name'],
            $blogPost['body'],
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

        $posts = array_map(function ($post) {
            return BlogPost::create(
                $post['id'],
                $post['name'],
                $post['teaser'],
                Carbon::createFromTimestamp($post['time_ts'], new DateTimeZone('Europe/Berlin')),
                $post['fs_name'],
                $post['picture']
            );
        }, $postData);

        return BlogPostList::create($posts, $postData[0]['totalPosts'] ?? 0);
    }

    public function getBlogpostList(): array
    {
        if ($this->session->mayRole(Role::ORGA)) {
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

        $blogEntry['body'] = $this->sanitizerService->purifyHtml($blogEntry['body'] ?? '');

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
                'body' => $data->content,
                'time' => Carbon::now()->format('Y-m-d H:i:s'),
                'picture' => strip_tags($data->picture),
                'active' => 1,
            ]
        );

        $foodsaver = [];
        $orgateam = $this->foodsaverGateway->getOrgaTeam();
        $botschafter = $this->foodsaverGateway->getAdminsOrAmbassadors($data->regionId);

        foreach ($orgateam as $o) {
            $foodsaver[$o['id']] = $o;
        }
        foreach ($botschafter as $b) {
            $foodsaver[$b['id']] = $b;
        }

        $bellData = Bell::create(
            'blog_new_check_title',
            'blog_new_check',
            'fas fa-bullhorn',
            ['href' => '/blog?sub=edit&id=' . $id],
            [
                'user' => $this->session->user('name'),
                'teaser' => $this->sanitizerService->tt($data->teaser, 100),
                'title' => $data->title
            ],
            BellType::createIdentifier(BellType::NEW_BLOG_POST, $id)
        );
        $this->bellGateway->addBell($foodsaver, $bellData);

        return $id;
    }
}
