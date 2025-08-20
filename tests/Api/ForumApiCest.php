<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode;
use Exception;
use Faker\Factory;
use Foodsharing\Modules\Core\DBConstants\Info\InfoType;
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

    public function testSetForumNotificationForNewPostDefaultBehaviorF1(ApiTester $I)
    {
        $I->login($this->user['email']);

        // Expect no registrated notifications
        $I->sendGet('api/notifications/forum');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseEquals('[]');

        $thread = $I->sendGet('/api/forum/thread/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertFalse($j['data']['isFollowingBell']);
        $I->assertFalse($j['data']['isFollowingEmail']);

        // Send post
        $I->login($this->user1['email']);
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/posts', ['body' => 'Test bell']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect E-Mail receive
        $I->expectNumMails(0, 20);

        // Expect receive of bell
        $I->login($this->user['email']);
        $I->sendGET('api/bells');
        $I->seeResponseCodeIs(HttpCode::OK);
        $bells = json_decode($I->grabResponse(), true);
        $I->assertCount(0, $bells);
    }

    public function testSetForumNotificationForNewPostOnlyForSetBellF7(ApiTester $I)
    {
        $I->login($this->user['email']);

        // Expect no registrated notifications
        $I->sendGet('api/notifications/forum');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseEquals('[]');

        // Register user with bell
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/follow/bell');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGet('api/notifications/forum');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseEquals('[]');

        $thread = $I->sendGet('/api/forum/thread/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertTrue($j['data']['isFollowingBell']);
        $I->assertFalse($j['data']['isFollowingEmail']);

        $I->login($this->user1['email']);
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/posts', ['body' => 'Test bell']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect E-Mail receive
        $I->expectNumMails(0, 20);

        // Expect receive of bell
        $I->login($this->user['email']);
        $I->sendGET('api/bells');
        $I->seeResponseCodeIs(HttpCode::OK);
        $bells = json_decode($I->grabResponse(), true);
        $I->assertCount(1, $bells);
        $I->assertEquals('forum_post.one', $bells[0]['key']);
        $I->assertEquals($this->thread['name'], $bells[0]['payload']['title']);
    }

    public function testSetForumNotificationForNewPostBothButOnyActivateForBellF8(ApiTester $I)
    {
        $I->login($this->user['email']);
        // Expect no registrated notifications
        $I->sendGet('api/notifications/forum');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([]);

        // Register user with bell
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/follow/bell');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendDelete('api/forum/thread/' . $this->thread['id'] . '/follow/email');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGet('api/notifications/forum');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseEquals('[]');

        $thread = $I->sendGet('/api/forum/thread/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertTrue($j['data']['isFollowingBell']);
        $I->assertFalse($j['data']['isFollowingEmail']);

        $I->login($this->user1['email']);
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/posts', ['body' => 'Test bell']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect E-Mail receive
        $I->expectNumMails(0, 20);

        // Expect receive of bell
        $I->login($this->user['email']);
        $I->sendGET('api/bells');
        $I->seeResponseCodeIs(HttpCode::OK);
        $bells = json_decode($I->grabResponse(), true);
        $I->assertCount(1, $bells);
        $I->assertEquals('forum_post.one', $bells[0]['key']);
        $I->assertEquals($this->thread['name'], $bells[0]['payload']['title']);
    }

    public function testSetForumNotificationForNewPostUnfollowBellF2(ApiTester $I)
    {
        $I->login($this->user['email']);

        // Register user with bell
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/follow/bell');
        $I->seeResponseCodeIs(HttpCode::OK);

        // Unfollow thread by bell
        $I->sendDelete('api/forum/thread/' . $this->thread['id'] . '/follow/bell');
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect no registrated notifications
        $I->sendGet('api/notifications/forum');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseEquals('[]');

        $thread = $I->sendGet('/api/forum/thread/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertFalse($j['data']['isFollowingBell']);
        $I->assertFalse($j['data']['isFollowingEmail']);

        // Send test mail
        $I->login($this->user1['email']);
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/posts', ['body' => 'Test email']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect no E-Mail receive
        $I->expectNumMails(0, 20);

        // Expect receive of bell
        $I->login($this->user['email']);
        $I->sendGET('api/bells');
        $I->seeResponseCodeIs(HttpCode::OK);
        $bells = json_decode($I->grabResponse(), true);
        $I->assertCount(0, $bells);
    }

    public function testSetForumNotificationForNewPostViaEmailF6(ApiTester $I)
    {
        $I->login($this->user['email']);
        // Expect no registrated notifications
        $I->sendGet('api/notifications/forum');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([]);

        // Register user with only email
        $I->sendDelete('api/forum/thread/' . $this->thread['id'] . '/follow/bell');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/follow/email');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGet('api/notifications/forum');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([[
            'id' => $this->thread['id'],
            'theme_name' => $this->thread['name'],
            'infotype' => InfoType::EMAIL,
            'region_or_group_name' => $this->region['name']]]);

        $thread = $I->sendGet('/api/forum/thread/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertFalse($j['data']['isFollowingBell']); // Expect that it is true (user can not disable it via notification)
        $I->assertTrue($j['data']['isFollowingEmail']);

        $I->login($this->user1['email']);
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/posts', ['body' => 'Test email']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect E-Mail receive
        $I->expectNumMails(1, 20);
        $mail = $I->getMails()[0];
        $I->assertStringContainsString($this->thread['name'], $mail->subject);

        // Expect receive of bell
        $I->login($this->user['email']);
        $I->sendGET('api/bells');
        $I->seeResponseCodeIs(HttpCode::OK);
        $bells = json_decode($I->grabResponse(), true);
        $I->assertCount(0, $bells);
    }

    // The system behavior is different when no entry in DB table exists then when one exists
    // This tests the tbale entry exists but no notification is active
    public function testSetForumNotificationForNewPostViaPreviouslyEmailF5(ApiTester $I)
    {
        $I->login($this->user['email']);

        // Expect no registrated notifications
        $I->sendGet('api/notifications/forum');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([]);

        // Register user with E-Mail
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/follow/email');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGet('api/notifications/forum');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([[
            'id' => $this->thread['id'],
            'theme_name' => $this->thread['name'],
            'infotype' => InfoType::EMAIL,
            'region_or_group_name' => $this->region['name']]]);

        $thread = $I->sendGet('/api/forum/thread/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertTrue($j['data']['isFollowingBell']);
        $I->assertTrue($j['data']['isFollowingEmail']);

        $I->login($this->user1['email']);
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/posts', ['body' => 'Test email']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect E-Mail receive
        $I->expectNumMails(1, 20);
        $mail = $I->getMails()[0];
        $I->assertStringContainsString($this->thread['name'], $mail->subject);

        // Expect receive of bell
        $I->login($this->user['email']);
        $I->sendGET('api/bells');
        $I->seeResponseCodeIs(HttpCode::OK);
        $bells = json_decode($I->grabResponse(), true);
        $I->assertCount(1, $bells);
        $I->assertEquals('forum_post.one', $bells[0]['key']);
        $I->assertEquals($this->thread['name'], $bells[0]['payload']['title']);
    }

    public function testSetForumNotificationForNewPostUnfollowEMailF3(ApiTester $I)
    {
        $I->login($this->user['email']);
        // Expect no bell present
        $I->sendGET('api/bells');
        $I->seeResponseCodeIs(HttpCode::OK);
        $bells = json_decode($I->grabResponse(), true);
        $I->assertCount(0, $bells); // -> Broken still reported

        // Register user with E-Mail
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/follow/email');
        $I->seeResponseCodeIs(HttpCode::OK);

        // Unfollow thread by e-mail
        $I->sendDelete('api/forum/thread/' . $this->thread['id'] . '/follow/email');
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect no registrated notifications
        $I->sendGet('api/notifications/forum');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseEquals('[]');

        $thread = $I->sendGet('/api/forum/thread/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertTrue($j['data']['isFollowingBell']); // Notification-Setting-UI does not support it
        $I->assertFalse($j['data']['isFollowingEmail']);

        // Send test mail
        $I->login($this->user1['email']);
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/posts', ['body' => 'Test email']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect no E-Mail receive
        $I->expectNumMails(0, 20);

        // Expect receive of bell
        $I->login($this->user['email']);
        $I->sendGET('api/bells');
        $I->seeResponseCodeIs(HttpCode::OK);
        $bells = json_decode($I->grabResponse(), true);
        $I->assertCount(1, $bells);
        $I->assertEquals('forum_post.one', $bells[0]['key']);
        $I->assertEquals($this->thread['name'], $bells[0]['payload']['title']);
    }

    public function testSetForumNotificationForNewPostF9(ApiTester $I)
    {
        $I->login($this->user['email']);
        // Expect no registrated notifications
        $I->sendGet('api/notifications/forum');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([]);

        $thread = $I->sendGet('/api/forum/thread/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertFalse($j['data']['isFollowingBell']);
        $I->assertFalse($j['data']['isFollowingEmail']);

        // Register user with bell
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/follow/bell'); // not working (Supports only E-Mail)
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/follow/email');
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect registrated notification for E-Mail and Bell
        $I->sendGet('api/notifications/forum');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([[
            'id' => $this->thread['id'],
            'theme_name' => $this->thread['name'],
            'infotype' => InfoType::EMAIL,
            'region_or_group_name' => $this->region['name']]]);

        $thread = $I->sendGet('/api/forum/thread/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertTrue($j['data']['isFollowingBell']);
        $I->assertTrue($j['data']['isFollowingEmail']);

        // Expect Bell notification information
        $I->login($this->user1['email']);
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/posts', ['body' => 'Test email']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect E-Mail receive
        $I->expectNumMails(1, 20);
        $mail = $I->getMails()[0];
        $I->assertStringContainsString($this->thread['name'], $mail->subject);

        // Expect receive of bell
        $I->login($this->user['email']);
        $I->sendGET('api/bells');
        $I->seeResponseCodeIs(HttpCode::OK);
        $bells = json_decode($I->grabResponse(), true);
        $I->assertCount(1, $bells);
        $I->assertEquals('forum_post.one', $bells[0]['key']);
        $I->assertEquals($this->thread['name'], $bells[0]['payload']['title']);
    }

    public function testSetForumNotificationForNewPostUnfollowF4(ApiTester $I)
    {
        $I->login($this->user['email']);

        // Register user with bell and email
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/follow/bell');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/follow/email');
        $I->seeResponseCodeIs(HttpCode::OK);

        // Unfollow thread by e-mail
        $I->sendDelete('api/forum/thread/' . $this->thread['id'] . '/follow/email');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->sendDelete('api/forum/thread/' . $this->thread['id'] . '/follow/bell');
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect no registrated notifications
        $I->sendGet('api/notifications/forum');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseEquals('[]');

        $thread = $I->sendGet('/api/forum/thread/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertFalse($j['data']['isFollowingBell']);
        $I->assertFalse($j['data']['isFollowingEmail']);

        // Send test mail
        $I->login($this->user1['email']);
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/posts', ['body' => 'Test email']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect no E-Mail receive
        $I->expectNumMails(0, 20);

        // Expect receive of bell
        $I->login($this->user['email']);
        $I->sendGET('api/bells');
        $I->seeResponseCodeIs(HttpCode::OK);
        $bells = json_decode($I->grabResponse(), true);
        $I->assertCount(0, $bells);
    }

    public function testSetForumNotificationForNewPostViaEMailByNotificationControllerN5(ApiTester $I)
    {
        $I->login($this->user['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        // Expect no registrated notifications
        $I->sendGet('api/notifications/forum');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseEquals('[]');

        // Register user with bell
        $I->sendPatch('api/notifications/forum', [['id' => $this->thread['id'], 'infotype' => InfoType::EMAIL]]);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect registrated notification for E-Mail and Bell
        $I->sendGet('api/notifications/forum');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseEquals('[]');

        $thread = $I->sendGet('/api/forum/thread/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertFalse($j['data']['isFollowingBell']);
        $I->assertFalse($j['data']['isFollowingEmail']);

        // Send post
        $I->login($this->user1['email']);
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/posts', ['body' => 'Test email']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect E-Mail receive
        $I->expectNumMails(0, 20);

        // Expect receive of bell
        $I->login($this->user['email']);
        $I->sendGET('api/bells');
        $I->seeResponseCodeIs(HttpCode::OK);
        $bells = json_decode($I->grabResponse(), true);
        $I->assertCount(0, $bells);
    }

    public function testSetForumNotificationForNewPostViaEMailByNotificationControllerN4(ApiTester $I)
    {
        $I->login($this->user['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        // Expect no registrated notifications
        $I->sendGet('api/notifications/forum');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseEquals('[]');

        // Register user with bell and email
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/follow/bell');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/follow/email');
        $I->seeResponseCodeIs(HttpCode::OK);

        // Register user with bell
        $I->sendPatch('api/notifications/forum', [['id' => $this->thread['id'], 'infotype' => InfoType::EMAIL]]);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect registrated notification for E-Mail and Bell
        $I->sendGet('api/notifications/forum');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([[
           'id' => $this->thread['id'],
           'theme_name' => $this->thread['name'],
           'infotype' => InfoType::EMAIL,
           'region_or_group_name' => $this->region['name']]]);

        $thread = $I->sendGet('/api/forum/thread/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertTrue($j['data']['isFollowingBell']);
        $I->assertTrue($j['data']['isFollowingEmail']);

        // Send post
        $I->login($this->user1['email']);
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/posts', ['body' => 'Test email']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect E-Mail receive
        $I->expectNumMails(1, 20);
        $mail = $I->getMails()[0];
        $I->assertStringContainsString($this->thread['name'], $mail->subject);

        // Expect receive of bell
        $I->login($this->user['email']);
        $I->sendGET('api/bells');
        $I->seeResponseCodeIs(HttpCode::OK);
        $bells = json_decode($I->grabResponse(), true);
        $I->assertCount(1, $bells);
        $I->assertEquals('forum_post.one', $bells[0]['key']);
        $I->assertEquals($this->thread['name'], $bells[0]['payload']['title']);
    }

    public function testSetForumNotificationForNewPostViaBellByNotificationControllerN3(ApiTester $I)
    {
        $I->login($this->user['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        // Expect no registrated notifications
        $I->sendGet('api/notifications/forum');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([]);

        // Register user with bell
        $I->sendPatch('api/notifications/forum', [['id' => $this->thread['id'], 'infotype' => InfoType::BELL]]);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect registrated notification for E-Mail and Bell
        $rsp = $I->sendGet('api/notifications/forum');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseEquals('[]');

        $thread = $I->sendGet('/api/forum/thread/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertFalse($j['data']['isFollowingBell']);
        $I->assertFalse($j['data']['isFollowingEmail']);

        // Send post
        $I->login($this->user1['email']);
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/posts', ['body' => 'Test email']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect E-Mail receive
        $I->expectNumMails(0, 20);

        // Expect receive of bell
        $I->login($this->user['email']);
        $I->sendGET('api/bells');
        $I->seeResponseCodeIs(HttpCode::OK);
        $bells = json_decode($I->grabResponse(), true);
        $I->assertCount(0, $bells);
    }

    public function testSetForumNotificationForNewPostViaBellByNotificationControllerN2(ApiTester $I)
    {
        $I->login($this->user['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        // Expect no registrated notifications
        $I->sendGet('api/notifications/forum');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([]);

        // Register user with bell and email
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/follow/bell');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/follow/email');
        $I->seeResponseCodeIs(HttpCode::OK);

        // Register user with bell
        $I->sendPatch('api/notifications/forum', [['id' => $this->thread['id'], 'infotype' => InfoType::BELL]]);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect registrated notification for E-Mail and Bell
        $rsp = $I->sendGet('api/notifications/forum');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseEquals('[]');

        $thread = $I->sendGet('/api/forum/thread/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertTrue($j['data']['isFollowingBell']);
        $I->assertFalse($j['data']['isFollowingEmail']);

        // Send post
        $I->login($this->user1['email']);
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/posts', ['body' => 'Test email']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect E-Mail receive
        $I->expectNumMails(0, 20);

        // Expect receive of bell
        $I->login($this->user['email']);
        $I->sendGET('api/bells');
        $I->seeResponseCodeIs(HttpCode::OK);
        $bells = json_decode($I->grabResponse(), true);
        $I->assertCount(1, $bells);
        $I->assertEquals('forum_post.one', $bells[0]['key']);
        $I->assertEquals($this->thread['name'], $bells[0]['payload']['title']);
    }

    public function testSetForumNotificationForNewPostUnfollowByNotificationControllerN1(ApiTester $I)
    {
        $I->login($this->user['email']);

        // Register user with bell and email
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/follow/bell');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/follow/email');
        $I->seeResponseCodeIs(HttpCode::OK);

        // Unfollow thread by e-mail
        // Register user with bell
        $I->sendPatch('api/notifications/forum', [['id' => $this->thread['id'], 'infotype' => InfoType::NONE]]);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect no registrated notifications
        $I->sendGet('api/notifications/forum');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseEquals('[]');

        $thread = $I->sendGet('/api/forum/thread/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertTrue($j['data']['isFollowingBell']);
        $I->assertFalse($j['data']['isFollowingEmail']);

        // Send test mail
        $I->login($this->user1['email']);
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/posts', ['body' => 'Test email']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect no E-Mail receive
        $I->expectNumMails(0, 20);

        // Expect receive of bell
        $I->login($this->user['email']);
        $I->sendGET('api/bells');
        $I->seeResponseCodeIs(HttpCode::OK);
        $bells = json_decode($I->grabResponse(), true);
        $I->assertCount(1, $bells);
        $I->assertEquals('forum_post.one', $bells[0]['key']);
        $I->assertEquals($this->thread['name'], $bells[0]['payload']['title']);
    }

    public function testSetForumNotificationForMention(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');

        $I->login($this->user1['email']);

        // Unfollow thread by e-mail
        $I->sendDelete('api/forum/thread/' . $this->thread['id'] . '/follow/email');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->sendDelete('api/forum/thread/' . $this->thread['id'] . '/follow/bell'); // not working (Supports only E-Mail)
        $I->seeResponseCodeIs(HttpCode::OK);

        // default behavior of mention
        // Send test mail
        $I->login($this->user['email']);
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/posts', ['body' => '1 Test email @' . $this->user1['id']]);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect no E-Mail receive
        $I->expectNumMails(0, 20);

        // Expect receive of bell
        $I->login($this->user1['email']);
        $I->sendGET('api/bells');
        $I->seeResponseCodeIs(HttpCode::OK);
        $bells = json_decode($I->grabResponse(), true);
        $I->assertCount(1, $bells); // -> Broken still reported

        // Test mention enabled
        $I->clearTable('fs_bell');
        $I->clearTable('fs_foodsaver_has_bell');
        $I->login($this->user1['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/notifications/mention', ['mention' => true]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_foodsaver_has_options', [
            'foodsaver_id' => $this->user1['id'],
            'option_type' => 4,
            'option_value' => false]);

        // Send test mail
        $I->login($this->user['email']);
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/posts', ['body' => '2 Test email @' . $this->user1['id']]);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect no E-Mail receive
        $I->expectNumMails(0, 20);

        // Expect receive of bell
        $I->login($this->user1['email']);
        $I->sendGET('api/bells');
        $I->seeResponseCodeIs(HttpCode::OK);
        $bells = json_decode($I->grabResponse(), true);
        $I->assertCount(1, $bells); // -> Broken still reported

        // Test mention disabled
        $I->clearTable('fs_bell');
        $I->clearTable('fs_foodsaver_has_bell');
        $I->login($this->user1['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/notifications/mention', ['mention' => false]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_foodsaver_has_options', [
            'foodsaver_id' => $this->user1['id'],
            'option_type' => 4,
            'option_value' => true]);

        // Send test mail
        $I->login($this->user['email']);
        $I->sendPost('api/forum/thread/' . $this->thread['id'] . '/posts', ['body' => '3 Test email @' . $this->user1['id']]);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect no E-Mail receive
        $I->expectNumMails(0, 20);

        // Expect receive of bell
        $I->login($this->user1['email']);
        $I->sendGET('api/bells');
        $I->seeResponseCodeIs(HttpCode::OK);
        $bells = json_decode($I->grabResponse(), true);
        $I->assertCount(0, $bells);
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

        $I->dontSeeInDatabase('fs_theme', ['id' => $inactiveThread['id']]);
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

    final public function checkNotificationForThreadCreatedButNotActivated(ApiTester $I): void
    {
        $moderatedRegion = $I->createRegion('ModeratedRegion', ['moderated' => true]);
        $I->addRegionMember($moderatedRegion['id'], $this->user['id']);
        $I->addRegionAdmin($moderatedRegion['id'], $this->ambassador['id']);
        $I->login($this->user['email']);
        $title = $this->faker->text(16);
        $I->sendPost('api/forum/' . $moderatedRegion['id'] . '/0', [
            'title' => $title,
            'body' => $this->faker->text(100),
            'sendMail' => false
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeInDatabase('fs_theme', ['foodsaver_id' => $this->user['id'], 'name' => $title, 'active' => 0]);

        $I->expectNumMails(1, 20);
        $mail = $I->getMails();
        $I->assertStringContainsString($this->ambassador['email'], $mail[0]->headers->to);
        $I->assertStringContainsString($title, $mail[0]->subject);

        $I->login($this->ambassador['email']);
        $I->sendGET('api/bells');
        $I->seeResponseCodeIs(HttpCode::OK);
        $bells = json_decode($I->grabResponse(), true);
        $I->assertCount(1, $bells);
        $I->assertEquals('forum_not_activated_thread', $bells[0]['key']);
    }

    final public function checkNotificationForThreadCreatedButActivated(ApiTester $I): void
    {
        $moderatedRegion = $I->createRegion('ModeratedRegion', ['moderated' => true]);
        $I->addRegionMember($moderatedRegion['id'], $this->user['id']);
        $I->addRegionAdmin($moderatedRegion['id'], $this->ambassador['id']);
        $I->login($this->user['email']);
        $title = $this->faker->text(16);
        $I->sendPost('api/forum/' . $moderatedRegion['id'] . '/0', [
            'title' => $title,
            'body' => $this->faker->text(100),
            'sendMail' => false
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $respo = json_decode($I->grabResponse(), true);
        $threadId = $respo['data']['id'];

        $I->login($this->ambassador['email']);
        $I->sendGET('api/bells');
        $I->seeResponseCodeIs(HttpCode::OK);
        $bells = json_decode($I->grabResponse(), true);
        $I->assertCount(1, $bells);
        $I->assertEquals('forum_not_activated_thread', $bells[0]['key']);

        $I->login($this->ambassador['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/forum/thread/' . $threadId, ['isActive' => true]);

        $I->seeInDatabase('fs_theme', ['foodsaver_id' => $this->user['id'], 'name' => $title, 'active' => 1]);

        $I->login($this->ambassador['email']);
        $I->sendGET('api/bells');
        $I->seeResponseCodeIs(HttpCode::OK);
        $bells = json_decode($I->grabResponse(), true);
        $I->assertCount(0, $bells);

        // Actual no E-Mail or Bell notification is generated for the activated
    }

    final public function checkNotificationForThreadCreatedButIsActiveValueBehavior(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $moderatedRegion = $I->createRegion('ModeratedRegion', ['moderated' => true]);
        $I->addRegionMember($moderatedRegion['id'], $this->user['id']);
        $I->addRegionAdmin($moderatedRegion['id'], $this->ambassador['id']);
        $I->login($this->user['email']);
        $title = $this->faker->text(16);
        $I->sendPost('api/forum/' . $moderatedRegion['id'] . '/0', [
            'title' => $title,
            'body' => $this->faker->text(100),
            'sendMail' => false
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $respo = json_decode($I->grabResponse(), true);
        $threadId = $respo['data']['id'];

        $I->login($this->ambassador['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/forum/thread/' . $threadId, ['isActive' => false]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/forum/thread/' . $threadId, ['isActive' => 'aaa']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/forum/thread/' . $threadId, ['isActive' => 1]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/forum/thread/' . $threadId, []);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/forum/thread/' . $threadId, ['isActive' => null]);
        $I->seeInDatabase('fs_theme', ['foodsaver_id' => $this->user['id'], 'name' => $title, 'active' => 0]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/forum/thread/' . $threadId, ['isActive' => true]);
        $I->seeInDatabase('fs_theme', ['foodsaver_id' => $this->user['id'], 'name' => $title, 'active' => 1]);
    }
}
