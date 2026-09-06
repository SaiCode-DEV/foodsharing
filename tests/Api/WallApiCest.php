<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode;
use Tests\Support\ApiTester;

/**
 * @group api-group-3
 */
class WallApiCest
{
    private array $foodsaver;
    private array $orga;
    private string $postText = 'This is my post.';
    private array $pictures;
    private array $picturePaths;
    private array $posts;

    public function _before(ApiTester $I): void
    {
        $this->foodsaver = $I->createFoodsaver();
        $this->orga = $I->createOrga();
        $this->pictures = [
            $I->createUpload($this->foodsaver['id'], null, null),
            $I->createUpload($this->foodsaver['id'], null, null)
        ];
        $this->picturePaths = array_column($this->pictures, 'uuid');
        $this->posts = [[
                'foodsaver_id' => $this->foodsaver['id'],
                'body' => '1',
                'attach' => null,
                'time' => date('Y-m-d H:i:s', time() - 10),
            ], [
                'foodsaver_id' => $this->foodsaver['id'],
                'body' => '2',
                'attach' => json_encode(['images' => $this->picturePaths]),
                'time' => date('Y-m-d H:i:s', time() - 5),
        ]];
        $this->posts[0]['id'] = $this->havePostInDatabase($I, $this->posts[0], 'fs_foodsaver_has_wallpost', 'foodsaver_id', $this->foodsaver['id']);
        $this->posts[1]['id'] = $this->havePostInDatabase($I, $this->posts[1], 'fs_foodsaver_has_wallpost', 'foodsaver_id', $this->foodsaver['id']);
    }

    // Post

    public function canPostToWall(ApiTester $I): void
    {
        $I->login($this->foodsaver['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/walls/foodsaver/' . $this->foodsaver['id'], ['body' => $this->postText]);
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
        $newPictures = [
            $I->createUpload($this->foodsaver['id'], null, null),
            $I->createUpload($this->foodsaver['id'], null, null)
        ];
        $newPicturePaths = array_column($newPictures, 'uuid');
        foreach ($newPictures as $p) {
            codecept_debug($p);
        }

        $I->login($this->foodsaver['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/walls/foodsaver/' . $this->foodsaver['id'], ['body' => '', 'pictures' => $newPicturePaths]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'body' => '',
            'author' => [
                'id' => $this->foodsaver['id'],
                'name' => $this->foodsaver['name'],
            ],
            'pictures' => $newPicturePaths,
        ]);

        $post = [
            'foodsaver_id' => $this->foodsaver['id'],
            'body' => '',
            'attach' => json_encode(['images' => $newPicturePaths]),
        ];
        $this->seePostInDatabase($I, $post, 'fs_foodsaver_has_wallpost', 'foodsaver_id', $this->foodsaver['id']);
    }

    public function cantPostToWallLoggedOut(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/walls/foodsaver/' . $this->foodsaver['id'], ['body' => $this->postText]);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
    }

    public function cantPostToWallWithoutPermission(ApiTester $I): void
    {
        // Example for a forbidden wall
        $I->login($this->orga['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/walls/foodsaver/' . $this->foodsaver['id'], ['body' => $this->postText]);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function cantPostEmptyPost(ApiTester $I): void
    {
        $I->login($this->foodsaver['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/walls/foodsaver/' . $this->foodsaver['id'], ['body' => '']);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    // Get

    public function canGetWallPosts(ApiTester $I): void
    {
        $I->login($this->foodsaver['email']);
        $I->sendGet('api/walls/foodsaver/' . $this->foodsaver['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'posts' => [
                ['pictures' => $this->picturePaths, 'body' => $this->posts[1]['body'], 'author' => [
                    'id' => $this->foodsaver['id'],
                    'name' => $this->foodsaver['name']]
                ],
                ['pictures' => null, 'body' => $this->posts[0]['body']]
            ],
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
        $I->sendGet('api/walls/foodsaver/' . $this->foodsaver['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['posts' => [[
            'body' => 'legacy', 'pictures' => ['659ea325eed654.89502817.png']
        ]]]);
    }

    public function cantGetWallPostsLoggedOut(ApiTester $I): void
    {
        $I->sendGet('api/walls/foodsaver/' . $this->foodsaver['id']);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function cantGetWallPostsWithoutPermission(ApiTester $I): void
    {
        $I->login($this->foodsaver['email']);
        $I->sendGet('api/walls/usernotes/' . $this->foodsaver['id']);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    // Delete

    public function canDeleteOwnWallPost(ApiTester $I): void
    {
        $I->login($this->foodsaver['email']);
        $I->sendDelete('api/walls/foodsaver/' . $this->foodsaver['id'] . '/posts/' . $this->posts[0]['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function canDeleteWallPostAsOrga(ApiTester $I): void
    {
        $I->login($this->orga['email']);
        $I->sendDelete('api/walls/foodsaver/' . $this->foodsaver['id'] . '/posts/' . $this->posts[0]['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function cantDeleteNonExistentPost(ApiTester $I): void
    {
        $I->login($this->orga['email']);
        $I->sendDelete('api/walls/foodsaver/123/posts/123456');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function cantDeleteWallPostLoggedOut(ApiTester $I): void
    {
        $I->sendDelete('api/walls/foodsaver/' . $this->foodsaver['id'] . '/posts/' . $this->posts[0]['id']);
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
        $I->sendDelete('api/walls/foodsaver/' . $this->orga['id'] . '/posts/' . $id);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function cantDeleteWallPostFromWrongWall(ApiTester $I): void
    {
        $I->login($this->foodsaver['email']);
        $I->sendDelete('api/walls/foodsaver/' . $this->orga['id'] . '/posts/' . $this->posts[0]['id']);
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
