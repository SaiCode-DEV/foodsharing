<?php

declare(strict_types=1);

namespace Api;

use Codeception\Util\HttpCode as Http;
use Faker\Factory;
use Tests\Support\ApiTester;

/**
 * @group api-group-2
 */
class BlogApiCest
{
    private $user;
    private $userOrga;
    private $region;
    private $blogPost;
    private $faker;

    public function _before(ApiTester $I)
    {
        $this->user = $I->createFoodsaver();
        $this->userOrga = $I->createOrga();
        $this->region = $I->createRegion();
        $this->blogPost = $I->addBlogPost($this->userOrga['id'], $this->region['id']);
        $this->faker = Factory::create('de_DE');
    }

    public function getFetchAllBlogPosts(ApiTester $I)
    {
        $I->sendGET('api/blog/');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'entries' => [[
                'id' => $this->blogPost['id'],
                'title' => $this->blogPost['name'],
                'teaser' => $this->blogPost['teaser'],
                'authorName' => $this->userOrga['name'] . ' ' . $this->userOrga['nachname'],
                'picture' => $this->blogPost['picture'],
            ]]
        ]);
    }

    public function canFetchBlogPost(ApiTester $I)
    {
        $I->sendGET('api/blog/' . $this->blogPost['id']);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'title' => $this->blogPost['name'],
            'content' => $this->blogPost['body'],
            'authorName' => $this->userOrga['name'] . ' ' . $this->userOrga['nachname'],
            'picture' => $this->blogPost['picture'],
        ]);
    }

    public function canNotAddBlogPostWithoutLogin(ApiTester $I)
    {
        $numBefore = count($I->grabColumnFromDatabase('fs_blog_entry', 'id'));

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('api/blog', $this->createRandomPost());
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);

        $numAfter = count($I->grabColumnFromDatabase('fs_blog_entry', 'id'));
        assert($numAfter === $numBefore);
    }

    public function canOnlyAddPostAsOrga(ApiTester $I)
    {
        $numBefore = count($I->grabColumnFromDatabase('fs_blog_entry', 'id'));

        $I->login($this->user['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('api/blog', $this->createRandomPost());
        $I->seeResponseCodeIs(Http::FORBIDDEN);

        $numAfter = count($I->grabColumnFromDatabase('fs_blog_entry', 'id'));
        assert($numAfter === $numBefore);
    }

    public function canNotAddInvalidPost(ApiTester $I)
    {
        $numBefore = count($I->grabColumnFromDatabase('fs_blog_entry', 'id'));

        $post = $this->createRandomPost();
        $post['title'] = '';

        $I->login($this->userOrga['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('api/blog', $post);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $numAfter = count($I->grabColumnFromDatabase('fs_blog_entry', 'id'));
        assert($numAfter === $numBefore);
    }

    public function canAddBlogPost(ApiTester $I)
    {
        $post = $this->createRandomPost();

        $I->login($this->userOrga['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('api/blog', $post);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'title' => $post['title'],
            'content' => $post['content'],
            'authorName' => $this->userOrga['name'] . ' ' . $this->userOrga['nachname'],
            'picture' => $post['picture'] ?? '',
        ]);

        $postId = $I->grabDataFromResponseByJsonPath('id')[0];
        $I->seeInDatabase('fs_blog_entry', [
            'id' => $postId
        ]);
    }

    private function createRandomPost(): array
    {
        return [
            'regionId' => $this->region['id'],
            'title' => $this->faker->realText(50),
            'teaser' => $this->faker->realText(200),
            'content' => $this->faker->realText(1000),
            'picture' => null,
            'isPublished' => true,
        ];
    }
}
