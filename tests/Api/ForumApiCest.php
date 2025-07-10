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
    private $user1;
    private $user2;
    private $user3;
    private $region;
    private $thread;
    private $ambassador;
    private $faker;

    final public function _before(ApiTester $I): void
    {
        $this->user = $I->createFoodsaver();
        $this->ambassador = $I->createAmbassador();
        $this->user1 = $I->createFoodsaver();
        $this->user2 = $I->createFoodsaver();
        $this->user3 = $I->createFoodsaver();

        $this->region = $I->createRegion(fillMailbox: false);
        $I->addRegionMember($this->region['id'], $this->user['id']);
        $I->addRegionMember($this->region['id'], $this->user1['id']);
        $I->addRegionMember($this->region['id'], $this->user2['id']);
        $I->addRegionMember($this->region['id'], $this->user3['id']);

        $this->thread = $I->addForumThread($this->region['id'], $this->user['id']);

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

    final public function MentionedSameUserMultiplyTimes(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $threadPath = 'api/forum/thread/' . $this->thread['id'];

        $body = 'Besprechung, @' . $this->user1['id'] .
            ' übernimmt du verifizieren @' . $this->user2['id'] . ' und @' . $this->user3['id'] . ' für @' . $this->user2['id'] .
            ' eine Einführungsabholung durchführen. ';

        $I->sendPOST($threadPath . '/posts', [
            'body' => $body
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $posts = $I->grabEntriesFromDatabase('fs_theme_post', ['body' => $body]);
        $I->assertCount(1, $posts);

        $bells = $I->grabEntriesFromDatabase('fs_bell', ['identifier' => 'forum-mention-' . $posts[0]['id']]);
        $I->assertCount(1, $bells);
        $I->seeNumRecords(1, 'fs_foodsaver_has_bell', ['foodsaver_id' => $this->user1['id'], 'bell_id' => $bells[0]['id']]);
        $I->seeNumRecords(1, 'fs_foodsaver_has_bell', ['foodsaver_id' => $this->user2['id'], 'bell_id' => $bells[0]['id']]);
        $I->seeNumRecords(1, 'fs_foodsaver_has_bell', ['foodsaver_id' => $this->user3['id'], 'bell_id' => $bells[0]['id']]);
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
        $moderatedRegion = $I->createRegion(null, ['type' => UnitType::CITY, 'moderated' => true], false);
        $I->addRegionMember($moderatedRegion['id'], $this->user['id']);
        $I->addRegionAdmin($moderatedRegion['id'], $this->ambassador['id']);

        $inactiveThread = $I->addForumThread($moderatedRegion['id'], $this->user['id'], false, ['active' => false]);
        $I->login($this->ambassador['email']);
        $I->sendDELETE('api/forum/thread/' . $inactiveThread['id']);
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
        $orga = $I->createOrga();
        $I->login($orga['email']);
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
