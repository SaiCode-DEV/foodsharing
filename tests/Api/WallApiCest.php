<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode;
use Tests\Support\ApiTester;

class WallApiCest
{
    private $foodsaver;
    private $orga;
    private $postText = 'This is my post.';
    private $pictures = ['/api/uploads/12-34', '/api/uploads/abc-def'];
    private $posts;

    public function _before(ApiTester $I): void
    {
        $this->foodsaver = $I->createFoodsaver();
        $this->orga = $I->createOrga();
        $this->posts = [[
                'foodsaver_id' => $this->foodsaver['id'],
                'body' => '1',
                'attach' => null,
                'time' => date('Y-m-d H:i:s', time() - 10),
            ], [
                'foodsaver_id' => $this->foodsaver['id'],
                'body' => '2',
                'attach' => json_encode(['images' => $this->pictures]),
                'time' => date('Y-m-d H:i:s', time() - 5),
        ]];
        $this->havePostInDatabase($I, $this->posts[0], 'fs_foodsaver_has_wallpost', 'foodsaver_id', $this->foodsaver['id']);
        $this->havePostInDatabase($I, $this->posts[1], 'fs_foodsaver_has_wallpost', 'foodsaver_id', $this->foodsaver['id']);
    }

    // Post

    public function canPostToWall(ApiTester $I): void
    {
        $I->login($this->foodsaver['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/wall/foodsaver/' . $this->foodsaver['id'], ['body' => $this->postText]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'body' => $this->postText,
            'author' => [
                'id' => $this->foodsaver['id'],
                'name' => $this->foodsaver['name'],
            ],
            'pictures' => null,
        ]);

        $post = [
            'foodsaver_id' => $this->foodsaver['id'],
            'body' => $this->postText,
            'attach' => null,
        ];
        $this->seePostInDatabase($I, $post, 'fs_foodsaver_has_wallpost', 'foodsaver_id', $this->foodsaver['id']);
    }

    public function canPostImages(ApiTester $I): void
    {
        $I->login($this->foodsaver['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/wall/foodsaver/' . $this->foodsaver['id'], ['body' => '', 'pictures' => $this->pictures]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'body' => '',
            'author' => [
                'id' => $this->foodsaver['id'],
                'name' => $this->foodsaver['name'],
            ],
            'pictures' => $this->pictures,
        ]);

        $post = [
            'foodsaver_id' => $this->foodsaver['id'],
            'body' => '',
            'attach' => json_encode(['images' => $this->pictures]),
        ];
        $this->seePostInDatabase($I, $post, 'fs_foodsaver_has_wallpost', 'foodsaver_id', $this->foodsaver['id']);
    }

    public function cantPostToWallLoggedOut(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/wall/foodsaver/' . $this->foodsaver['id'], ['body' => $this->postText]);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
    }

    public function cantPostToWallWithoutPermission(ApiTester $I): void
    {
        // Example for a forbidden wall
        $I->login($this->orga['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/wall/foodsaver/' . $this->foodsaver['id'], ['body' => $this->postText]);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function cantPostEmptyPost(ApiTester $I): void
    {
        $I->login($this->orga['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/wall/foodsaver/' . $this->foodsaver['id'], ['body' => $this->postText]);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    // Get

    public function canGetWallPosts(ApiTester $I): void
    {
        $I->login($this->foodsaver['email']);
        $I->sendGet('api/wall/foodsaver/' . $this->foodsaver['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'posts' => [
                ['pictures' => $this->pictures, 'body' => '2', 'author' => [
                    'id' => $this->foodsaver['id'],
                    'name' => $this->foodsaver['name']]],
                ['pictures' => null, 'body' => '1']],
            'mayPost' => true,
            'mayDelete' => false,
        ]);
    }

    public function canGetWallPostsWithLegacyImages(ApiTester $I): void
    {
        $legacyPost = [
            'foodsaver_id' => $this->foodsaver['id'],
            'body' => 'legacy',
            'attach' => json_encode(['image' => [['file' => '659ea325eed654.89502817.png']]]),
            'time' => date('Y-m-d H:i:s'),
        ];
        $this->havePostInDatabase($I, $legacyPost, 'fs_foodsaver_has_wallpost', 'foodsaver_id', $this->foodsaver['id']);
        $I->login($this->foodsaver['email']);
        $I->sendGet('api/wall/foodsaver/' . $this->foodsaver['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['posts' => [[
            'body' => 'legacy', 'pictures' => ['659ea325eed654.89502817.png']
        ]]]);
    }

    public function cantGetWallPostsLoggedOut(ApiTester $I): void
    {
        $I->sendGet('api/wall/foodsaver/' . $this->foodsaver['id']);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function cantGetWallPostsWithoutPermission(ApiTester $I): void
    {
        $I->login($this->foodsaver['email']);
        $I->sendGet('api/wall/usernotes/' . $this->foodsaver['id']);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    // Delete

    public function canDeleteOwnWallPost(ApiTester $I): void
    {
        $I->login($this->foodsaver['email']);
        $I->sendDelete('api/wall/foodsaver/' . $this->foodsaver['id'] . '/' . 1);
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function canDeleteWallPostAsOrga(ApiTester $I): void
    {
        $I->login($this->orga['email']);
        $I->sendDelete('api/wall/foodsaver/' . $this->foodsaver['id'] . '/' . 1);
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function cantDeleteWallPostLoggedOut(ApiTester $I): void
    {
        $I->sendDelete('api/wall/foodsaver/' . $this->foodsaver['id'] . '/' . 1);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
    }

    public function cantDeleteWallPostWithoutPermission(ApiTester $I): void
    {
        $post = [
            'foodsaver_id' => $this->orga['id'],
            'body' => $this->postText
        ];
        $id = $this->havePostInDatabase($I, $post, 'fs_foodsaver_has_wallpost', 'foodsaver_id', $this->orga['id']);

        $I->login($this->foodsaver['email']);
        $I->sendDelete('api/wall/foodsaver/' . $this->foodsaver['id'] . '/' . $id);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function cantDeleteWallPostFromWrongWall(ApiTester $I): void
    {
        $I->login($this->foodsaver['email']);
        $I->sendDelete('api/wall/foodsaver/' . $this->orga['id'] . '/1');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    // Helper

    private function seePostInDatabase(ApiTester $I, array $post, string $linkTable, string $foreinKey, int $targetId): void
    {
        $I->seeInDatabase('fs_wallpost', $post);
        $id = $I->grabFromDatabase('fs_wallpost', 'id', $post);

        $I->seeInDatabase($linkTable, [
            $foreinKey => $targetId,
            'wallpost_id' => $id
        ]);
    }

    private function havePostInDatabase(ApiTester $I, array $post, string $linkTable, string $foreinKey, int $targetId): int
    {
        $id = $I->haveInDatabase('fs_wallpost', $post);
        $I->haveInDatabase($linkTable, [
            $foreinKey => $targetId,
            'wallpost_id' => $id
        ]);

        return $id;
    }
}
