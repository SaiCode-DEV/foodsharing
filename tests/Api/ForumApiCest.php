<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Attribute\Examples;
use Codeception\Example;
use Codeception\Util\HttpCode;
use DateTime;
use Exception;
use Faker\Factory;
use Faker\Generator;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Tests\Support\ApiTester;

/**
 * @group api-group-1
 *
 * TODO: Test rename thread
 * TODO: Test list threads
 * TODO: Test post feature
 * TODO: Test reaction feature
 */
class ForumApiCest
{
    private array $user;
    private array $user1;
    private array $user2;
    private array $user3;
    private array $unverifiedUser;
    private array $userWithoutMembershipInRegion;
    private array $region;
    private array $thread;
    private array $ambassador;
    private Generator $faker;

    final public function _before(ApiTester $I): void
    {
        $lastLoginDate = new DateTime();
        $lastLoginDate->modify('-2 months -1minutes');
        $lastLoginDateString = $lastLoginDate->format('Y-m-d H:i:s');
        $this->user = $I->createFoodsaver(null, ['last_login' => $lastLoginDateString]);
        $this->ambassador = $I->createAmbassador();
        $this->user1 = $I->createFoodsaver(null, ['last_login' => $lastLoginDateString]);
        $this->user2 = $I->createFoodsaver(null, ['last_login' => $lastLoginDateString]);
        $this->user3 = $I->createFoodsaver(null, ['last_login' => $lastLoginDateString]);
        $this->unverifiedUser = $I->createFoodsaver(null, ['verified' => 0, 'last_login' => $lastLoginDateString]);
        $this->userWithoutMembershipInRegion = $I->createFoodsaver(null, ['last_login' => $lastLoginDateString]);

        $this->region = $I->createRegion();
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
        $I->sendGet('api/notifications/threads');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseEquals('[]');

        $thread = $I->sendGet('/api/forum/threads/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertFalse($j['subscriptionsStatus']['isBellSubscribed']);
        $I->assertFalse($j['subscriptionsStatus']['isMailSubscribed']);

        // Send post
        $I->login($this->user1['email']);
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/posts', ['body' => 'Test bell']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect E-Mail receive
        $I->expectNumMails(0, 20);

        // Expect receive of bell
        $this->expectNoBellForUser($I, $this->user);
    }

    public function testSetForumNotificationForNewPostOnlyForSetBellF7(ApiTester $I)
    {
        $I->login($this->user['email']);

        // Expect no registrated notifications
        $I->sendGet('api/notifications/threads');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseEquals('[]');

        // Register user with bell
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/follow/bell');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGet('api/notifications/threads');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([['id' => $this->thread['id'], 'bell' => true, 'email' => false]]);

        $thread = $I->sendGet('/api/forum/threads/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertTrue($j['subscriptionsStatus']['isBellSubscribed']);
        $I->assertFalse($j['subscriptionsStatus']['isMailSubscribed']);

        $I->login($this->user1['email']);
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/posts', ['body' => 'Test bell']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect E-Mail receive
        $I->expectNumMails(0, 20);

        // Expect receive of bell
        $bell = $this->expectBellForUser($I, $this->user, 'forum_post.one');
        $I->assertEquals($this->thread['name'], $bell['payload']['title']);
    }

    public function testSetForumNotificationForNewPostBothButOnyActivateForBellF8(ApiTester $I)
    {
        $I->login($this->user['email']);
        // Expect no registrated notifications
        $I->sendGET('api/notifications/threads');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([]);

        // Register user with bell
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/follow/bell');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendDELETE('api/forum/threads/' . $this->thread['id'] . '/follow/email');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGet('api/notifications/threads');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([['id' => $this->thread['id'], 'bell' => true, 'email' => false]]);

        $thread = $I->sendGet('/api/forum/threads/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertTrue($j['subscriptionsStatus']['isBellSubscribed']);
        $I->assertFalse($j['subscriptionsStatus']['isMailSubscribed']);

        $I->login($this->user1['email']);
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/posts', ['body' => 'Test bell']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect E-Mail receive
        $I->expectNumMails(0, 20);

        // Expect receive of bell
        $bell = $this->expectBellForUser($I, $this->user, 'forum_post.one');
        $I->assertEquals($this->thread['name'], $bell['payload']['title']);
    }

    public function testSetForumNotificationForNewPostUnfollowBellF2(ApiTester $I)
    {
        $I->login($this->user['email']);

        // Register user with bell
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/follow/bell');
        $I->seeResponseCodeIs(HttpCode::OK);

        // Unfollow thread by bell
        $I->sendDELETE('api/forum/threads/' . $this->thread['id'] . '/follow/bell');
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect no registrated notifications
        $I->sendGet('api/notifications/threads');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseEquals('[]');

        $thread = $I->sendGet('/api/forum/threads/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertFalse($j['subscriptionsStatus']['isBellSubscribed']);
        $I->assertFalse($j['subscriptionsStatus']['isMailSubscribed']);

        // Send test mail
        $I->login($this->user1['email']);
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/posts', ['body' => 'Test email']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect no E-Mail receive
        $I->expectNumMails(0, 20);

        // Expect receive of bell
        $this->expectNoBellForUser($I, $this->user);
    }

    public function testSetForumNotificationForNewPostViaEmailF6(ApiTester $I)
    {
        $I->login($this->user['email']);
        // Expect no registrated notifications
        $I->sendGET('api/notifications/threads');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([]);

        // Register user with only email
        $I->sendDELETE('api/forum/threads/' . $this->thread['id'] . '/follow/bell');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/follow/email');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGet('api/notifications/threads');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([[
            'id' => $this->thread['id'],
            'name' => $this->thread['name'],
            'email' => true,
            'bell' => false,
            'region' => ['id' => $this->region['id']]]]);

        $thread = $I->sendGet('/api/forum/threads/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertFalse($j['subscriptionsStatus']['isBellSubscribed']); // Expect that it is true (user can not disable it via notification)
        $I->assertTrue($j['subscriptionsStatus']['isMailSubscribed']);

        $I->login($this->user1['email']);
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/posts', ['body' => 'Test email']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect E-Mail receive
        $I->expectNumMails(1, 20);
        $mail = $I->getMails()[0];
        $I->assertStringContainsString($this->thread['name'], $mail->subject);

        // Expect receive of bell
        $this->expectNoBellForUser($I, $this->user);
    }

    // The system behavior is different when no entry in DB table exists then when one exists
    // This tests the tbale entry exists but no notification is active
    public function testSetForumNotificationForNewPostViaPreviouslyEmailF5(ApiTester $I)
    {
        $I->login($this->user['email']);

        // Expect no registrated notifications
        $I->sendGet('api/notifications/threads');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([]);

        // Register user with E-Mail
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/follow/email');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGet('api/notifications/threads');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([[
            'id' => $this->thread['id'],
            'name' => $this->thread['name'],
            'email' => true,
            'region' => ['id' => $this->region['id']]]]);

        $thread = $I->sendGet('/api/forum/threads/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertTrue($j['subscriptionsStatus']['isBellSubscribed']);
        $I->assertTrue($j['subscriptionsStatus']['isMailSubscribed']);

        $I->login($this->user1['email']);
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/posts', ['body' => 'Test email']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect E-Mail receive
        $I->expectNumMails(1, 20);
        $mail = $I->getMails()[0];
        $I->assertStringContainsString($this->thread['name'], $mail->subject);

        // Expect receive of bell
        $bell = $this->expectBellForUser($I, $this->user, 'forum_post.one');
        $I->assertEquals($this->thread['name'], $bell['payload']['title']);
    }

    public function testSetForumNotificationForNewPostUnfollowEMailF3(ApiTester $I)
    {
        // Expect no bell present
        $this->expectNoBellForUser($I, $this->user); // -> Broken still reported

        // Register user with E-Mail
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/follow/email');
        $I->seeResponseCodeIs(HttpCode::OK);

        // Unfollow thread by e-mail
        $I->sendDELETE('api/forum/threads/' . $this->thread['id'] . '/follow/email');
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect no registrated notifications
        $I->sendGet('api/notifications/threads');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([['id' => $this->thread['id'], 'bell' => true, 'email' => false]]);

        $thread = $I->sendGet('/api/forum/threads/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertTrue($j['subscriptionsStatus']['isBellSubscribed']); // Notification-Setting-UI does not support it
        $I->assertFalse($j['subscriptionsStatus']['isMailSubscribed']);

        // Send test mail
        $I->login($this->user1['email']);
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/posts', ['body' => 'Test email']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect no E-Mail receive
        $I->expectNumMails(0, 20);

        // Expect receive of bell
        $bell = $this->expectBellForUser($I, $this->user, 'forum_post.one');
        $I->assertEquals($this->thread['name'], $bell['payload']['title']);
    }

    public function testSetForumNotificationForNewPostF9(ApiTester $I)
    {
        $I->login($this->user['email']);
        // Expect no registrated notifications
        $I->sendGet('api/notifications/threads');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([]);

        $thread = $I->sendGet('/api/forum/threads/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertFalse($j['subscriptionsStatus']['isBellSubscribed']);
        $I->assertFalse($j['subscriptionsStatus']['isMailSubscribed']);

        // Register user with bell
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/follow/bell'); // not working (Supports only E-Mail)
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/follow/email');
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect registrated notification for E-Mail and Bell
        $I->sendGet('api/notifications/threads');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([[
            'id' => $this->thread['id'],
            'name' => $this->thread['name'],
            'email' => true,
            'region' => ['id' => $this->region['id']]]]);

        $thread = $I->sendGet('/api/forum/threads/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertTrue($j['subscriptionsStatus']['isBellSubscribed']);
        $I->assertTrue($j['subscriptionsStatus']['isMailSubscribed']);

        // Expect Bell notification information
        $I->login($this->user1['email']);
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/posts', ['body' => 'Test email']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect E-Mail receive
        $I->expectNumMails(1, 20);
        $mail = $I->getMails()[0];
        $I->assertStringContainsString($this->thread['name'], $mail->subject);

        // Expect receive of bell
        $bell = $this->expectBellForUser($I, $this->user, 'forum_post.one');
        $I->assertEquals($this->thread['name'], $bell['payload']['title']);
    }

    public function testSetForumNotificationForNewPostUnfollowF4(ApiTester $I)
    {
        $I->login($this->user['email']);

        // Register user with bell and email
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/follow/bell');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/follow/email');
        $I->seeResponseCodeIs(HttpCode::OK);

        // Unfollow thread by e-mail
        $I->sendDELETE('api/forum/threads/' . $this->thread['id'] . '/follow/email');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->sendDELETE('api/forum/threads/' . $this->thread['id'] . '/follow/bell');
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect no registrated notifications
        $I->sendGet('api/notifications/threads');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseEquals('[]');

        $thread = $I->sendGet('/api/forum/threads/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertFalse($j['subscriptionsStatus']['isBellSubscribed']);
        $I->assertFalse($j['subscriptionsStatus']['isMailSubscribed']);

        // Send test mail
        $I->login($this->user1['email']);
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/posts', ['body' => 'Test email']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect no E-Mail receive
        $I->expectNumMails(0, 20);

        // Expect receive of bell
        $this->expectNoBellForUser($I, $this->user);
    }

    public function testSetForumNotificationForNewPostViaEMailByNotificationControllerN5(ApiTester $I)
    {
        $I->login($this->user['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        // Expect no registrated notifications
        $I->sendGet('api/notifications/threads');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseEquals('[]');

        $I->sendPatch('api/notifications/threads', ['notifications' => [['id' => $this->thread['id'], 'email' => true]]]);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect registrated notification for E-Mail and Bell
        $I->sendGet('api/notifications/threads');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([['id' => $this->thread['id'], 'bell' => true, 'email' => true]]);

        $thread = $I->sendGet('/api/forum/threads/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertTrue($j['subscriptionsStatus']['isBellSubscribed']);
        $I->assertTrue($j['subscriptionsStatus']['isMailSubscribed']);

        // Send post
        $I->login($this->user1['email']);
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/posts', ['body' => 'Test email']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect E-Mail receive
        $I->expectNumMails(1, 20);

        // Expect receive of bell
        $this->expectBellForUser($I, $this->user);
    }

    public function testSetForumNotificationForNewPostViaEMailByNotificationControllerN4(ApiTester $I)
    {
        $I->login($this->user['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        // Expect no registrated notifications
        $I->sendGet('api/notifications/threads');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseEquals('[]');

        // Register user with bell and email
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/follow/bell');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/follow/email');
        $I->seeResponseCodeIs(HttpCode::OK);

        // Register user with bell
        $I->sendPatch('api/notifications/threads', ['notifications' => [['id' => $this->thread['id'], 'email' => true]]]);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect registrated notification for E-Mail and Bell
        $I->sendGet('api/notifications/threads');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([[
           'id' => $this->thread['id'],
           'name' => $this->thread['name'],
           'email' => true,
           'region' => ['id' => $this->region['id']]]]);

        $thread = $I->sendGet('/api/forum/threads/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertTrue($j['subscriptionsStatus']['isBellSubscribed']);
        $I->assertTrue($j['subscriptionsStatus']['isMailSubscribed']);

        // Send post
        $I->login($this->user1['email']);
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/posts', ['body' => 'Test email']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect E-Mail receive
        $I->expectNumMails(1, 20);
        $mail = $I->getMails()[0];
        $I->assertStringContainsString($this->thread['name'], $mail->subject);

        // Expect receive of bell
        $bell = $this->expectBellForUser($I, $this->user, 'forum_post.one');
        $I->assertEquals($this->thread['name'], $bell['payload']['title']);
    }

    public function testSetForumNotificationForNewPostViaBellByNotificationControllerN3(ApiTester $I)
    {
        $I->login($this->user['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        // Expect no registrated notifications
        $I->sendGet('api/notifications/threads');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([]);

        // Register user with bell
        $I->sendPatch('api/notifications/threads', ['notifications' => [['id' => $this->thread['id'], 'bell' => true]]]);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect registrated notification for E-Mail and Bell
        $rsp = $I->sendGet('api/notifications/threads');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([['id' => $this->thread['id'], 'bell' => true, 'email' => false]]);

        $thread = $I->sendGet('/api/forum/threads/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertTrue($j['subscriptionsStatus']['isBellSubscribed']);
        $I->assertFalse($j['subscriptionsStatus']['isMailSubscribed']);

        // Send post
        $I->login($this->user1['email']);
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/posts', ['body' => 'Test email']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect E-Mail receive
        $I->expectNumMails(0, 20);

        // Expect receive of bell
        $bell = $this->expectBellForUser($I, $this->user);
    }

    public function testSetForumNotificationForNewPostViaBellByNotificationControllerN2(ApiTester $I)
    {
        $I->login($this->user['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        // Expect no registrated notifications
        $I->sendGet('api/notifications/threads');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([]);

        // Register user with bell and email
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/follow/bell');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/follow/email');
        $I->seeResponseCodeIs(HttpCode::OK);

        // Remove mail subscription
        $I->sendPatch('api/notifications/threads', ['notifications' => [['id' => $this->thread['id'], 'email' => false]]]);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect registrated notification for E-Mail and Bell
        $rsp = $I->sendGet('api/notifications/threads');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['id' => $this->thread['id'], 'email' => false]);

        $thread = $I->sendGet('/api/forum/threads/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertTrue($j['subscriptionsStatus']['isBellSubscribed']);
        $I->assertFalse($j['subscriptionsStatus']['isMailSubscribed']);

        // Send post
        $I->login($this->user1['email']);
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/posts', ['body' => 'Test email']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect E-Mail receive
        $I->expectNumMails(0, 20);

        // Expect receive of bell
        $bell = $this->expectBellForUser($I, $this->user, 'forum_post.one');
        $I->assertEquals($this->thread['name'], $bell['payload']['title']);
    }

    public function testSetForumNotificationForNewPostUnfollowByNotificationControllerN1(ApiTester $I)
    {
        $I->login($this->user['email']);

        // Register user with bell and email
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/follow/bell');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/follow/email');
        $I->seeResponseCodeIs(HttpCode::OK);

        // Unfollow thread by e-mail
        // Register user with bell
        $I->sendPatch('api/notifications/threads', ['notifications' => [['id' => $this->thread['id'], 'email' => false]]]);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect no registrated notifications
        $I->sendGet('api/notifications/threads');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([['id' => $this->thread['id'], 'email' => false, 'bell' => true]]);

        $thread = $I->sendGet('/api/forum/threads/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $j = json_decode($thread, true);
        $I->assertTrue($j['subscriptionsStatus']['isBellSubscribed']);
        $I->assertFalse($j['subscriptionsStatus']['isMailSubscribed']);

        // Send test mail
        $I->login($this->user1['email']);
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/posts', ['body' => 'Test email']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect no E-Mail receive
        $I->expectNumMails(0, 20);

        // Expect receive of bell
        $bell = $this->expectBellForUser($I, $this->user, 'forum_post.one');
        $I->assertEquals($this->thread['name'], $bell['payload']['title']);
    }

    public function testSetForumNotificationForMention(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');

        $I->login($this->user1['email']);

        // Unfollow thread by e-mail
        $I->sendDELETE('api/forum/threads/' . $this->thread['id'] . '/follow/email');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->sendDELETE('api/forum/threads/' . $this->thread['id'] . '/follow/bell'); // not working (Supports only E-Mail)
        $I->seeResponseCodeIs(HttpCode::OK);

        // default behavior of mention
        // Send test mail
        $I->login($this->user['email']);
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/posts', ['body' => '1 Test email @' . $this->user1['id']]);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect no E-Mail receive
        $I->expectNumMails(0, 20);

        // Expect receive of bell
        $this->expectBellForUser($I, $this->user1); // -> Broken still reported

        // Test mention enabled
        $I->clearTable('fs_bell');
        $I->clearTable('fs_foodsaver_has_bell');
        $I->login($this->user1['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/notifications', ['bellOnMention' => true]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_foodsaver_has_options', [
            'foodsaver_id' => $this->user1['id'],
            'option_type' => 4,
            'option_value' => false]);

        // Send test mail
        $I->login($this->user['email']);
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/posts', ['body' => '2 Test email @' . $this->user1['id']]);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect no E-Mail receive
        $I->expectNumMails(0, 20);

        // Expect receive of bell
        $this->expectBellForUser($I, $this->user1); // -> Broken still reported

        // Test mention disabled
        $I->clearTable('fs_bell');
        $I->clearTable('fs_foodsaver_has_bell');
        $I->login($this->user1['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/notifications', ['bellOnMention' => false]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_foodsaver_has_options', [
            'foodsaver_id' => $this->user1['id'],
            'option_type' => 4,
            'option_value' => true]);

        // Send test mail
        $I->login($this->user['email']);
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/posts', ['body' => '3 Test email @' . $this->user1['id']]);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Expect no E-Mail receive
        $I->expectNumMails(0, 20);

        // Expect receive of bell
        $this->expectNoBellForUser($I, $this->user1);
    }

    final public function deleteNonExistingForumPostIs404(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendDELETE('api/forum/posts/9999999');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseIsJson();
    }

    final public function deleteOwnPostSucceeds(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendDELETE('api/forum/posts/' . $this->thread['post']['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    final public function deleteForeignPostFails403(ApiTester $I): void
    {
        $foreigner = $I->createFoodsaver();
        $I->login($foreigner['email']);
        $I->sendDELETE('api/forum/posts/' . $this->thread['post']['id']);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseIsJson();
    }

    final public function MentionedSameUserMultiplyTimes(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $threadPath = 'api/forum/threads/' . $this->thread['id'];

        $body = 'Besprechung, @' . $this->user1['id'] .
            ' übernimmt du verifizieren @' . $this->user2['id'] . ' und @' . $this->user3['id'] . ' für @' . $this->user2['id'] .
            ' eine Einführungsabholung durchführen. ';

        $I->haveHttpHeader('Content-Type', 'application/json');
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
        $I->sendPOST('api/forum/threads/' . $this->thread['id'] . '/posts', [
            'body' => $body
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_theme_post', ['body' => $body]);
        $I->sendGET('api/forum/threads/' . $this->thread['id']);
        $I->seeResponseIsJson();
        $I->assertEquals(
            $body,
            $I->grabDataFromResponseByJsonPath('$.posts[1].body')[0]
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
        $I->sendDELETE('api/forum/threads/' . $inactiveThread['id']);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->dontSeeInDatabase('fs_theme', ['id' => $inactiveThread['id']]);
    }

    /**
     * @throws Exception
     */
    final public function canNotDeleteActiveThread(ApiTester $I): void
    {
        $I->login($this->ambassador['email']);
        $I->sendPatch('api/forum/threads/' . $this->thread['id'], [
            'isActive' => true
        ]);
        $I->sendDELETE('api/forum/threads/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    /**
     * @throws Exception
     */
    final public function canNotDeleteThreadWithoutPermissions(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendDelete('api/forum/threads/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    final public function canCloseThreads(ApiTester $I): void
    {
        $orga = $I->createOrga();
        $I->login($orga['email']);
        $I->seeResponseCodeIsSuccessful();
        $I->sendPatch('api/forum/threads/' . $this->thread['id'], [
            'status' => 1
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_theme', [
            'id' => $this->thread['id'],
            'status' => 1
        ]);

        $I->sendPatch('api/forum/threads/' . $this->thread['id'], [
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
        $I->sendPatch('api/forum/threads/' . $this->thread['id'], [
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
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/posts', [
            'body' => $this->faker->text(100)
        ]);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    final public function checkNotActivatedNotificationForCreatingThreadInModeratedForumByNormalUser(ApiTester $I): void
    {
        $moderatedRegion = $I->createRegion('ModeratedRegion', ['moderated' => true]);
        $I->addRegionMember($moderatedRegion['id'], $this->user['id']);
        $I->addRegionAdmin($moderatedRegion['id'], $this->ambassador['id']);
        $I->login($this->user['email']);
        $title = $this->createThread($I, $moderatedRegion['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_theme', ['foodsaver_id' => $this->user['id'], 'name' => $title, 'active' => 0]);

        $I->expectNumMails(1, 20);
        $mail = $I->getMails();
        $I->assertStringContainsString($this->ambassador['email'], $mail[0]->headers->to);
        $I->assertStringContainsString($title, $mail[0]->subject);

        $this->expectBellForUser($I, $this->ambassador, 'forum_not_activated_thread');
    }

    final public function checkThreadNotificationsForCreatingThreadInModeratedForumButActivatedByAdminLater(ApiTester $I): void
    {
        $moderatedRegion = $I->createRegion('ModeratedRegion', ['moderated' => true]);
        $I->addRegionMember($moderatedRegion['id'], $this->user['id']);
        $I->addRegionAdmin($moderatedRegion['id'], $this->ambassador['id']);

        $I->login($this->user['email']);
        $title = $this->createThread($I, $moderatedRegion['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $threadId = $I->grabFromDatabase('fs_theme', 'id', ['name' => $title]);

        $I->login($this->ambassador['email']);
        $this->expectBellForUser($I, $this->ambassador, 'forum_not_activated_thread');

        $I->login($this->ambassador['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/forum/threads/' . $threadId, ['isActive' => true]);

        $I->seeInDatabase('fs_theme', ['foodsaver_id' => $this->user['id'], 'name' => $title, 'active' => 1]);

        $this->expectNoBellForUser($I, $this->ambassador);

        // Actual no E-Mail or Bell notification is generated for the activated
        // [] Bells should be removed for not activated thread
    }

    final public function followerBellsCreatedOnlyOnActivation(ApiTester $I): void
    {
        $moderatedRegion = $I->createRegion('ModeratedRegionFollowers', ['moderated' => true]);
        $I->addRegionMember($moderatedRegion['id'], $this->user['id']);
        $I->addRegionMember($moderatedRegion['id'], $this->user1['id']);
        $I->addRegionAdmin($moderatedRegion['id'], $this->ambassador['id']);

        // Make user1 follow new threads in the forum
        $I->updateInDatabase('fs_foodsaver_has_bezirk', ['notify_on_all_new_threads' => 1], ['bezirk_id' => $moderatedRegion['id'], 'foodsaver_id' => $this->user1['id']]);

        $I->login($this->user['email']);
        $title = $this->createThread($I, $moderatedRegion['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $threadId = $I->grabFromDatabase('fs_theme', 'id', ['name' => $title]);

        // Before activation: follower should not receive a bell
        $this->expectNoBellForUser($I, $this->user1);

        // Activate thread as ambassador
        $I->login($this->ambassador['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/forum/threads/' . $threadId, ['isActive' => true]);
        $I->seeResponseCodeIs(HttpCode::OK);

        // After activation: follower should receive a new thread bell
        $bell = $this->expectBellForUser($I, $this->user1, 'new_forum_thread');
        $I->assertEquals($title, $bell['payload']['title']);
    }

    final public function followerBellsCreatedImmediatelyForNonModerated(ApiTester $I): void
    {
        $nonModeratedRegion = $I->createRegion('NonModeratedRegion', ['moderated' => false]);
        $I->addRegionMember($nonModeratedRegion['id'], $this->user['id']);
        $I->addRegionMember($nonModeratedRegion['id'], $this->user1['id']);
        $I->addRegionAdmin($nonModeratedRegion['id'], $this->ambassador['id']);

        // Make user1 follow new threads in the forum
        $I->updateInDatabase('fs_foodsaver_has_bezirk', ['notify_on_all_new_threads' => 1], ['bezirk_id' => $nonModeratedRegion['id'], 'foodsaver_id' => $this->user1['id']]);

        $I->login($this->user['email']);
        $title = $this->createThread($I, $nonModeratedRegion['id']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Immediately after creation (non-moderated) follower should receive a new thread bell
        $bell = $this->expectBellForUser($I, $this->user1, 'new_forum_thread');
        $I->assertEquals($title, $bell['payload']['title']);
    }

    final public function checkNotificationForThreadCreatedButIsActiveValueBehavior(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $moderatedRegion = $I->createRegion('ModeratedRegion', ['moderated' => true]);
        $I->addRegionMember($moderatedRegion['id'], $this->user['id']);
        $I->addRegionAdmin($moderatedRegion['id'], $this->ambassador['id']);
        $I->login($this->user['email']);
        $title = $this->createThread($I, $moderatedRegion['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $threadId = $I->grabFromDatabase('fs_theme', 'id', ['name' => $title]);

        $I->login($this->ambassador['email']);
        foreach ([false, 'aaa', 1, null] as $isActive) {
            $I->haveHttpHeader('Content-Type', 'application/json');
            $I->sendPatch('api/forum/threads/' . $threadId, ['isActive' => $isActive]);
        }
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/forum/threads/' . $threadId, []);
        $I->seeInDatabase('fs_theme', ['foodsaver_id' => $this->user['id'], 'name' => $title, 'active' => 0]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/forum/threads/' . $threadId, ['isActive' => true]);
        $I->seeInDatabase('fs_theme', ['foodsaver_id' => $this->user['id'], 'name' => $title, 'active' => 1]);
    }

    final public function checkAdminNotificationForNotModeratedRegionThreadCreationNoForceSendOfNotification(ApiTester $I): void
    {
        $region = $I->createRegion('ModeratedRegion', ['moderated' => false]);
        $I->addRegionMember($region['id'], $this->user['id'], true, false);
        $I->addRegionMember($region['id'], $this->user1['id'], true, false);
        $I->addRegionMember($region['id'], $this->ambassador['id'], true, true);
        $I->addRegionAdmin($region['id'], $this->ambassador['id']);

        $I->login($this->user['email']);
        $this->createThread($I, $region['id']);
        $I->seeResponseCodeIs(HttpCode::OK);

        $this->expectBellForUser($I, $this->ambassador, 'new_forum_thread');
        $this->expectNoBellForUser($I, $this->user1);

        $I->expectNumMails(0, 30);
    }

    final public function checkNonAdminNotificationForNotModeratedRegionThreadCreationNoForceSendOfNotification(ApiTester $I): void
    {
        $region = $I->createRegion('Region', ['moderated' => false]);
        $I->addRegionMember($region['id'], $this->user['id'], true, true);
        $I->addRegionMember($region['id'], $this->user1['id'], true, true);
        $I->addRegionMember($region['id'], $this->ambassador['id'], true, false);
        $I->addRegionAdmin($region['id'], $this->ambassador['id']);

        $I->login($this->user['email']);
        $this->createThread($I, $region['id']);

        $this->expectNoBellForUser($I, $this->ambassador);
        $this->expectNoBellForUser($I, $this->user);

        $this->expectBellForUser($I, $this->user1, 'new_forum_thread');

        $I->expectNumMails(0);
    }

    /**
     * Checks if only admins get a notification for the creation of threads in the admin forum
     * - The creator should not get an bell notification
     * - The other admins with enabled new forum thread notification should get a bell notification.
     */
    final public function checkNotificationForAdminThreadCreationNoForceSendOfNotificationForNonAdmins(ApiTester $I): void
    {
        $ambassador1 = $I->createAmbassador();
        $region = $I->createRegion('Region', ['moderated' => false]);
        $I->addRegionMember($region['id'], $this->user['id'], true, true);
        $I->addRegionMember($region['id'], $this->user1['id'], true, true);
        $I->addRegionMember($region['id'], $this->ambassador['id'], true, false);
        $I->addRegionAdmin($region['id'], $this->ambassador['id']);
        $I->addRegionMember($region['id'], $ambassador1['id'], true, true);
        $I->addRegionAdmin($region['id'], $ambassador1['id']);

        $I->login($this->ambassador['email']);
        $this->createThread($I, $region['id'], 1);
        $I->seeResponseCodeIs(HttpCode::OK);

        $this->expectNoBellForUser($I, $this->user);
        $this->expectNoBellForUser($I, $this->user1);

        $this->expectBellForUser($I, $ambassador1, 'new_forum_thread');

        $I->expectNumMails(0);
    }

    /**
     * Test that too big regions do not get an E-Mail for normal forum. Big regions are always handled like moderated
     * forums, and to not send E-Mails to users.
     */
    #[Examples(UnitType::FEDERAL_STATE)]
    #[Examples(UnitType::COUNTRY)]
    #[Examples(UnitType::BIG_CITY)]
    #[Examples(UnitType::CONTINENT)]
    final public function checkNoNotificationForNotModeratedRegionThreadCreationForceSendOfNotificationLocatedInBigRegion(ApiTester $I, Example $example): void
    {
        $region = $I->createRegion('Big region', ['moderated' => false, 'type' => $example[0]]);
        $I->addRegionMember($region['id'], $this->user['id'], true, false);
        $I->addRegionMember($region['id'], $this->user1['id'], true, false);
        $I->addRegionMember($region['id'], $this->ambassador['id'], true, true);
        $I->addRegionAdmin($region['id'], $this->ambassador['id']);

        $I->login($this->user['email']);
        $title = $this->createThread($I, $region['id'], 0, true);
        $I->seeResponseCodeIs(HttpCode::OK);
        $response = json_decode($I->grabResponse(), true);
        $threadId = $response['id'];

        $I->expectNumMails(1);
        $mails = $I->getMails();
        $I->assertStringContainsString($this->ambassador['email'], $mails[0]->to[0]->address);
        $I->assertStringContainsString($title, $mails[0]->subject);

        $I->deleteAllMails();

        // User get no E-Mail after activation for big regions
        $I->login($this->ambassador['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/forum/threads/' . $threadId, ['isActive' => true]);

        $I->expectNumMails(0);
    }

    /**
     * Test that small region do get an E-Mail for normal forum if the user enabled it.
     */
    #[Examples(UnitType::REGION)]
    #[Examples(UnitType::DISTRICT)]
    #[Examples(UnitType::CITY)]
    #[Examples(UnitType::PART_OF_TOWN)]
    #[Examples(UnitType::WORKING_GROUP)]
    final public function checkNotificationForNotModeratedForumThreadCreationSendEmailNotificationLocatedInSmallRegion(ApiTester $I, Example $example): void
    {
        $region = $I->createRegion('Small region', ['moderated' => false, 'type' => $example[0]]);
        $I->addRegionMember($region['id'], $this->user['id'], true, false, false);
        $I->addRegionMember($region['id'], $this->user1['id'], true, false, true);
        $I->addRegionMember($region['id'], $this->ambassador['id'], true, false, false);
        $I->addRegionAdmin($region['id'], $this->ambassador['id']);
        $I->login($this->user['email']);
        $title = $this->createThread($I, $region['id'], 0, true);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->expectNumMails(1, 30);
        $mails = $I->getMails();
        $I->assertStringContainsString($this->user1['email'], $mails[0]->to[0]->address);
        $I->assertStringContainsString($title, $mails[0]->subject);
    }

    /**
     * Test that any region size ambassador forum allows sending E-Mails to other admins. The admins get always E-Mail
     * and can not disable it.
     */
    #[Examples(UnitType::REGION)]
    #[Examples(UnitType::DISTRICT)]
    #[Examples(UnitType::CITY)]
    #[Examples(UnitType::PART_OF_TOWN)]
    #[Examples(UnitType::BIG_CITY)]
    #[Examples(UnitType::WORKING_GROUP)]
    #[Examples(UnitType::FEDERAL_STATE)]
    #[Examples(UnitType::COUNTRY)]
    #[Examples(UnitType::CONTINENT)]
    final public function createEMailNotificationForAdminWhenNewAdminForumThreadIsCreatedForAllAdminsNoFiltering(ApiTester $I, Example $example): void
    {
        $ambassador1 = $I->createAmbassador();
        $ambassador2 = $I->createAmbassador();
        $region = $I->createRegion('Big region', ['moderated' => false, 'type' => $example[0]]);
        $I->addRegionMember($region['id'], $this->user['id'], true, false, false);
        $I->addRegionMember($region['id'], $this->user1['id'], true, false, true);
        $I->addRegionMember($region['id'], $this->ambassador['id'], true, false, false);
        $I->addRegionMember($region['id'], $ambassador1['id'], true, false, false);
        $I->addRegionMember($region['id'], $ambassador2['id'], true, false, true);
        $I->addRegionAdmin($region['id'], $this->ambassador['id']);
        $I->addRegionAdmin($region['id'], $ambassador1['id']);
        $I->addRegionAdmin($region['id'], $ambassador2['id']);
        $I->login($this->ambassador['email']);
        $title = $this->createThread($I, $region['id'], 1, true);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->expectNumMails(3, 60);
        $mail = $I->getMails();
        $I->assertStringContainsString($title, $mail[0]->subject);
        $I->assertStringContainsString($title, $mail[1]->subject);
        $I->assertStringContainsString($title, $mail[2]->subject);
        // TODO author and all admins should receive the mail (this is different than in other cases)
        // TODO admin groups are not considered in implementation
        $receivers = [];
        $receivers[] = $this->checkEMailIsForAddress($I, $mail[0], [$this->ambassador['email'], $ambassador1['email'], $ambassador2['email']]);
        $receivers[] = $this->checkEMailIsForAddress($I, $mail[1], [$this->ambassador['email'], $ambassador1['email'], $ambassador2['email']]);
        $receivers[] = $this->checkEMailIsForAddress($I, $mail[2], [$this->ambassador['email'], $ambassador1['email'], $ambassador2['email']]);
        $I->assertCount(3, array_unique($receivers));

        // User get no E-Mails as expected
        // No region size check exists as expected
    }

    private function checkEMailIsForAddress(ApiTester $I, $mail, $addresses)
    {
        $findReceiver = array_filter($addresses, function ($value) use ($mail) { return $value == $mail->to[0]->address; });
        $I->assertCount(1, $findReceiver);

        return reset($findReceiver);
    }

    /**
     * Tests that only ambassador can create thread in admin forum all other end in forbidden.
     */
    #[Examples('member')]
    #[Examples('memberOfOtherRegion')]
    #[Examples('memberOfNoRegion')]
    #[Examples('unverifiedUser')]
    #[Examples('unverifiedUserInRegion')]
    final public function createAdminForumThreadByNotAdminEndsInForbidden(ApiTester $I, Example $example): void
    {
        $unverifiedUserInRegion = $I->createFoodsaver();
        $users = [
            'member' => $this->user,
            'memberOfOtherRegion' => $this->user1,
            'memberOfNoRegion' => $this->userWithoutMembershipInRegion,
            'unverifiedUser' => $this->unverifiedUser,
            'unverifiedUserInRegion' => $unverifiedUserInRegion];
        $region = $I->createRegion('Test region');
        $I->addRegionMember($region['id'], $this->user['id'], true, false, false);
        $I->addRegionMember($region['id'], $unverifiedUserInRegion['id'], true, false, false);
        $I->addRegionMember($region['id'], $this->ambassador['id'], true, false, false);
        $I->addRegionAdmin($region['id'], $this->ambassador['id']);
        $I->login($users[$example[0]]['email']);
        $title = $this->createThread($I, $region['id'], 1, true);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->dontSeeInDatabase('fs_theme', ['name' => $title]);
    }

    final public function checkThreadCreationByUnverifiedUserNeedsActivationByAdmin(ApiTester $I): void
    {
        $moderatedRegion = $I->createRegion('ModeratedRegion', ['moderated' => false]);
        $I->addRegionMember($moderatedRegion['id'], $this->unverifiedUser['id']);
        $I->addRegionMember($moderatedRegion['id'], $this->ambassador['id'], true, true);
        $I->addRegionAdmin($moderatedRegion['id'], $this->ambassador['id']);
        $I->login($this->unverifiedUser['email']);
        $title = $this->createThread($I, $moderatedRegion['id']);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeInDatabase('fs_theme', ['foodsaver_id' => $this->unverifiedUser['id'], 'name' => $title, 'active' => 0]);

        $I->expectNumMails(1, 60);
        $mail = $I->getMails();
        $I->assertStringContainsString($this->ambassador['email'], $mail[0]->headers->to);
        $I->assertStringContainsString($title, $mail[0]->subject);

        $this->expectBellForUser($I, $this->ambassador, 'forum_not_activated_thread');
    }

    /**
     * Test that admins can directly create thread in moderated forum without review by another admin.
     */
    final public function checkThreadCreationByAdminInModeratedForumNeedsNoActivationByAdmin(ApiTester $I): void
    {
        $authorAmbassador = $I->createAmbassador();
        $moderatedRegion = $I->createRegion('ModeratedRegion', ['moderated' => true]);
        $I->addRegionMember($moderatedRegion['id'], $authorAmbassador['id']);
        $I->addRegionMember($moderatedRegion['id'], $this->ambassador['id'], false, false); // No new thread notifications
        $I->addRegionAdmin($moderatedRegion['id'], $authorAmbassador['id']);
        $I->addRegionAdmin($moderatedRegion['id'], $this->ambassador['id']);
        $I->login($authorAmbassador['email']);
        $title = $this->createThread($I, $moderatedRegion['id']);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeInDatabase('fs_theme', ['foodsaver_id' => $authorAmbassador['id'], 'name' => $title, 'active' => 1]);

        $I->expectNumMails(0, 60);

        $this->expectNoBellForUser($I, $this->ambassador);
    }

    // Test user do not get E-Mail if last login is older then 6 months
    public function checkNoNotificationEMailForUsersWhichDidntLoginMoreThen6Month(ApiTester $I): void
    {
        $lastLoginDate = new DateTime();
        $lastLoginDate->modify('-6 months -1minutes');
        $lastLoginDateString = $lastLoginDate->format('Y-m-d H:i:s');
        $userWithLongInactiveTime = $I->createFoodsaver(null, ['last_login' => $lastLoginDateString]);
        $region = $I->createRegion('Small region', ['moderated' => false, 'type' => UnitType::CITY]);
        $I->addRegionMember($region['id'], $this->user['id'], true, false, false);
        $I->addRegionMember($region['id'], $userWithLongInactiveTime['id'], true, false, true);
        $I->addRegionMember($region['id'], $this->ambassador['id'], true, false, false);
        $I->addRegionAdmin($region['id'], $this->ambassador['id']);

        $I->login($this->user['email']);
        $title = $this->createThread($I, $region['id'], 0, true);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->expectNumMails(0, 30);
    }

    public function testModerationWorkgroupCanGetInformedToActivateAndCanActivateThread(ApiTester $I): void
    {
        $moderatedRegion = $I->createRegion('ModeratedRegion', ['moderated' => true]);
        $lastLoginDate = new DateTime();
        $lastLoginDate->modify('-2 months -1minutes');
        $lastLoginDateString = $lastLoginDate->format('Y-m-d H:i:s');

        // Test if a normal user is enough I think it needs to be a ambassador / or Working group admin
        $moderatorGroupUser = $I->createFoodsaver(null, ['last_login' => $lastLoginDateString]);

        $moderatorGroup = $I->createWorkingGroup('ForumModerators', ['parent_id' => $moderatedRegion['id']]);
        $I->haveInDatabase('fs_region_function', ['region_id' => $moderatorGroup['id'], 'function_id' => WorkgroupFunction::MODERATION, 'target_id' => $moderatedRegion['id']]);
        $I->addRegionMember($moderatorGroup['id'], $moderatorGroupUser['id']);
        // Why does the user need to be Working Group Admin and not only member of the group
        $I->addRegionAdmin($moderatorGroup['id'], $moderatorGroupUser['id']);

        $I->addRegionMember($moderatedRegion['id'], $this->user['id']);
        $I->addRegionMember($moderatedRegion['id'], $moderatorGroupUser['id']);
        $I->addRegionAdmin($moderatedRegion['id'], $this->ambassador['id']);

        $I->login($this->user['email']);
        $title = $this->createThread($I, $moderatedRegion['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $rsp = $I->grabResponse();
        $response = json_decode($rsp, true);
        $threadId = $response['id'];

        $this->expectBellForUser($I, $moderatorGroupUser, 'forum_not_activated_thread');

        $I->login($moderatorGroupUser['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/forum/threads/' . $threadId, ['isActive' => true]);

        $I->seeInDatabase('fs_theme', ['foodsaver_id' => $this->user['id'], 'name' => $title, 'active' => 1]);

        $this->expectNoBellForUser($I, $moderatorGroupUser);

        // Actual no E-Mail or Bell notification is generated for the activated
        // [] Bells should be removed for not activated thread
        // Do moderation working group members access to admin forum?
    }

    public function testForbiddenForNotLoginUser(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendGET('api/forum/threads/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
        $this->createThread($I, $this->region['id'], 0);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
        $this->createThread($I, $this->region['id'], 1);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
        $I->sendGET('api/forum/threads/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/follow/email');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
        $I->sendDelete('api/forum/threads/' . $this->thread['id'] . '/follow/email');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/follow/bell');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
        $I->sendDelete('api/forum/threads/' . $this->thread['id'] . '/follow/bell');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->sendPatch('api/forum/threads/' . $this->thread['id'], [
            'stickiness' => 1,
            'isActive' => true,
            'status' => 1,
            'title' => 'change'
        ]);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
        $I->sendDelete('api/forum/threads/' . $this->thread['id']);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->sendGET('api/regions/' . $this->region['id'] . '/forum/subscriptions');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
        $I->sendPut('api/regions/' . $this->region['id'] . '/forum/subscriptions?isFollowing=true');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
    }

    #[Examples('memberOfNoRegion')]
    #[Examples('unverifiedUser')]
    #[Examples('unverifiedUserInRegion')]
    #[Examples('ambassador')]
    #[Examples('member')]
    #[Examples('memberOfOtherRegion')]
    public function testNotExistBehaviour(ApiTester $I, Example $example): void
    {
        $unverifiedUserInRegion = $I->createFoodsaver();
        $users = [
            'ambassador' => $this->ambassador,
            'member' => $this->user,
            'memberOfOtherRegion' => $this->user1,
            'memberOfNoRegion' => $this->userWithoutMembershipInRegion,
            'unverifiedUser' => $this->unverifiedUser,
            'unverifiedUserInRegion' => $unverifiedUserInRegion];
        $I->login($users[$example[0]]['email']);

        // Fetch a non-existing thread
        $I->sendGET('api/forum/threads/' . $this->thread['id'] + 1);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);

        // Set or remove the follow status
        $I->sendPost('api/forum/threads/' . $this->thread['id'] + 1 . '/follow/email');
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->sendDelete('api/forum/threads/' . $this->thread['id'] + 1 . '/follow/email');
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->sendPost('api/forum/threads/' . $this->thread['id'] + 1 . '/follow/bell');
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->sendDelete('api/forum/threads/' . $this->thread['id'] + 1 . '/follow/bell');
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);

        // Edit the thread
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/forum/threads/' . $this->thread['id'] + 1, [
            'stickiness' => 1,
            'isActive' => true,
            'status' => 1,
            'title' => 'change'
        ]);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->sendDelete('api/forum/threads/' . $this->thread['id'] + 1);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND); // TODO FOrbidden test needed

        // Fetch and change the subscription to non-existing region
        $I->sendGET('api/regions/1234/forum/subscriptions');
        $I->seeResponseCodeIs(HttpCode::OK);  // TODO Defect get always ok but forum does not exist
        $I->sendPut('api/regions/1234/forum/subscriptions?isFollowing=true');
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);

        // Create a thread in that region
        foreach ([0, 1, 2] as $subforumId) {
            $this->createThread($I, 1234, $subforumId);
            $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        }
    }

    public function testFollowRegionForumActivity(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendGET('api/regions/' . $this->region['id'] . '/forum/subscriptions');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['isFollowing' => false]);

        $I->login($this->user1['email']);
        $this->createThread($I, $this->region['id'], 0);
        $this->faker->text(16);
        $this->createThread($I, $this->region['id']);
        $this->expectNoBellForUser($I, $this->user, 'new_forum_thread');

        $I->sendPut('api/regions/' . $this->region['id'] . '/forum/subscriptions?isFollowing=true');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGET('api/regions/' . $this->region['id'] . '/forum/subscriptions');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['isFollowing' => true]);

        $I->login($this->user1['email']);
        $this->createThread($I, $this->region['id']);
        $this->expectBellForUser($I, $this->user, 'new_forum_thread');
    }

    private function expectNoBellForUser(ApiTester $I, array $user): void
    {
        $I->login($user['email']);
        $I->sendGET('api/bells');
        $I->seeResponseCodeIs(HttpCode::OK);
        $bells = json_decode($I->grabResponse(), true);
        $I->assertCount(0, $bells);
    }

    private function expectBellForUser(ApiTester $I, array $user, ?string $messageKey = null): array
    {
        $I->login($user['email']);
        $I->sendGET('api/bells');
        $I->seeResponseCodeIs(HttpCode::OK);
        $bells = json_decode($I->grabResponse(), true);
        $I->assertCount(1, $bells);
        if ($messageKey) {
            $I->assertEquals($messageKey, $bells[0]['key']);
        }

        return $bells[0];
    }

    /**
     * Sends an API request to create a thread with random data.
     */
    private function createThread(ApiTester $I, int $regionId, int $subForumId = 0, bool $sendEmail = false): string
    {
        $title = $this->faker->text(16);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost("api/regions/$regionId/forum/threads?subforumId=$subForumId", [
            'title' => $title,
            'body' => $this->faker->text(100),
            'sendMail' => $sendEmail
        ]);

        return $title;
    }

    public function membershipGrantedFromOutsideIsSeenWithoutRelogin(ApiTester $I): void
    {
        $I->login($this->userWithoutMembershipInRegion['email']);

        // Prime the session cache with "not a member"
        $I->sendGET('api/regions/' . $this->region['id'] . '/forum/threads?subforumId=0');
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);

        // Someone else grants the membership: only the database changes, the session
        // cache still says "no member" (#2774 - e.g. an accepted group application)
        $I->addRegionMember($this->region['id'], $this->userWithoutMembershipInRegion['id']);

        $I->sendGET('api/regions/' . $this->region['id'] . '/forum/threads?subforumId=0');
        $I->seeResponseCodeIs(HttpCode::OK);
    }
}
