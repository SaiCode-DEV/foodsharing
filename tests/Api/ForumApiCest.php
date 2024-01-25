<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode;
use Exception;
use Faker\Factory;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Tests\Support\ApiTester;

class ForumApiCest
{
    private $user;
    private $region;
    private $thread;
    private $ambassador;
    private $orga;
    private $moderatedRegion;
    private $inactiveThread;
    private $faker;

    final public function _before(ApiTester $I): void
    {
        $this->user = $I->createFoodsaver();
        $this->ambassador = $I->createAmbassador();
        $this->orga = $I->createOrga();

        $this->region = $I->createRegion();
        $I->addRegionMember($this->region['id'], $this->user['id']);
        $this->thread = $I->addForumThread($this->region['id'], $this->user['id']);

        $this->moderatedRegion = $I->createRegion(null, ['type' => UnitType::CITY, 'moderated' => true]);
        $I->addRegionMember($this->moderatedRegion['id'], $this->user['id']);
        $I->addRegionAdmin($this->moderatedRegion['id'], $this->ambassador['id']);
        $this->inactiveThread = $I->addForumThread($this->moderatedRegion['id'], $this->user['id'], false, ['active' => false]);

        $this->faker = Factory::create('de_DE');
    }

    final public function deleteNonExistingForumPostIs404(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendDELETE('api/forum/post/9999999');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseIsJson();
    }

    final public function deleteOwnPostSucceeds(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendDELETE('api/forum/post/' . $this->thread['post']['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
    }

    final public function deleteForeignPostFails403(ApiTester $I): void
    {
        $foreigner = $I->createFoodsaver();
        $I->login($foreigner['email']);
        $I->sendDELETE('api/forum/post/' . $this->thread['post']['id']);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseIsJson();
    }

    /**
     * @throws Exception
     */
    final public function canUseEmojis(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $body = 'I am so 😂 for you! ' . $this->faker->text(50);
        $threadPath = 'api/forum/thread/' . $this->thread['id'];
        $I->sendPOST($threadPath . '/posts', [
            'body' => $body
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_theme_post', ['body' => $body]);
        $I->sendGET($threadPath);
        $I->seeResponseIsJson();
        $I->assertEquals(
            $body,
            $I->grabDataFromResponseByJsonPath('$.data.posts[1].body')[0]
        );
    }

    /**
     * @throws Exception
     */
    final public function canDeleteInactiveThreadAsAmbassador(ApiTester $I): void
    {
        $I->login($this->ambassador['email']);
        $I->sendDELETE('api/forum/thread/' . $this->inactiveThread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    /**
     * @throws Exception
     */
    final public function canNotDeleteActiveThread(ApiTester $I): void
    {
        $I->login($this->ambassador['email']);
        $I->sendPATCH('api/forum/thread/' . $this->thread['id'], [
            'isActive' => true
        ]);
        $I->sendDELETE('api/forum/thread/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    final public function canCloseThreads(ApiTester $I): void
    {
        $I->login($this->orga['email']);
        $I->sendPatch('api/forum/thread/' . $this->thread['id'], [
            'status' => 1
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_theme', [
            'id' => $this->thread['id'],
            'status' => 1
        ]);

        $I->sendPatch('api/forum/thread/' . $this->thread['id'], [
            'status' => 0
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_theme', [
            'id' => $this->thread['id'],
            'status' => 0
        ]);
    }

    final public function canNotCloseThreadsAsFoodsaver(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendPatch('api/forum/thread/' . $this->thread['id'], [
            'status' => 1
        ]);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeInDatabase('fs_theme', [
            'id' => $this->thread['id'],
            'status' => 0
        ]);
    }

    final public function canNotPostToClosedThreads(ApiTester $I): void
    {
        $I->updateInDatabase('fs_theme', ['status' => 1], ['id' => $this->thread['id']]);

        $I->login($this->user['email']);
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/posts', [
            'body' => $this->faker->text(100)
        ]);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }
}
