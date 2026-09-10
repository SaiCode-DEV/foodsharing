<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode;
use Faker\Factory;
use Tests\Support\ApiTester;

/**
 * @group api-group-2
 */
class MessageApiCest
{
    private $user;
    private $faker;

    public function _before(ApiTester $I): void
    {
        $this->user = $I->createFoodsaver();
        $this->faker = Factory::create('de_DE');
    }

    public function getAllConversations(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendGET('api/conversations');
        $I->seeResponseIsJson();
    }

    public function getSingleConversation(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendGET('api/conversations/1');
        $I->seeResponseIsJson();
    }

    public function canFetchConversationWithDeletedUser(ApiTester $I): void
    {
        $deletedUser = $I->createFoodsaver(null, [
            'verified' => 0,
            'rolle' => 0,
            'plz' => null,
            'stadt' => null,
            'lat' => null,
            'lon' => null,
            'photo' => null,
            'email' => null,
            'password' => null,
            'name' => null,
            'nachname' => null,
            'anschrift' => null,
            'telefon' => null,
            'handy' => null,
            'geb_datum' => null,
            'deleted_at' => $this->faker->dateTime($max = '-1 week')->format('Y-m-d\TH:i:s'),
        ]);
        $conv = $I->createConversation([$this->user['id'], $deletedUser['id']]);

        $I->login($this->user['email']);
        $I->sendGET('api/conversations/' . $conv['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
    }

    public function resendWithSameClientKeyIsIdempotent(ApiTester $I): void
    {
        $other = $I->createFoodsaver();
        $conv = $I->createConversation([$this->user['id'], $other['id']]);
        $I->login($this->user['email']);
        $clientKey = $this->faker->regexify('[0-9a-f]{12}');

        $I->sendPOST('api/conversations/' . $conv['id'] . '/messages', ['body' => 'hello', 'clientKey' => $clientKey]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $firstId = $I->grabDataFromResponseByJsonPath('$.id')[0];

        // A retry with the same key (e.g. after a lost response) must return the same
        // message and must not store a second row.
        $I->sendPOST('api/conversations/' . $conv['id'] . '/messages', ['body' => 'hello', 'clientKey' => $clientKey]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $secondId = $I->grabDataFromResponseByJsonPath('$.id')[0];

        $I->assertEquals($firstId, $secondId);
        $I->seeNumRecords(1, 'fs_msg', ['conversation_id' => $conv['id'], 'client_key' => $clientKey]);
    }

    public function differentClientKeysCreateSeparateMessages(ApiTester $I): void
    {
        $other = $I->createFoodsaver();
        $conv = $I->createConversation([$this->user['id'], $other['id']]);
        $I->login($this->user['email']);

        $I->sendPOST('api/conversations/' . $conv['id'] . '/messages', ['body' => 'same text', 'clientKey' => $this->faker->regexify('[0-9a-f]{12}')]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->sendPOST('api/conversations/' . $conv['id'] . '/messages', ['body' => 'same text', 'clientKey' => $this->faker->regexify('[0-9a-f]{12}')]);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeNumRecords(2, 'fs_msg', ['conversation_id' => $conv['id'], 'body' => 'same text']);
    }

    public function sendingWithoutClientKeyStillWorks(ApiTester $I): void
    {
        $other = $I->createFoodsaver();
        $conv = $I->createConversation([$this->user['id'], $other['id']]);
        $I->login($this->user['email']);

        $I->sendPOST('api/conversations/' . $conv['id'] . '/messages', ['body' => 'no key here']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeNumRecords(1, 'fs_msg', ['conversation_id' => $conv['id'], 'body' => 'no key here']);
    }

    public function sameClientKeyFromAnotherSenderIsNotSwallowed(ApiTester $I): void
    {
        $other = $I->createFoodsaver();
        $conv = $I->createConversation([$this->user['id'], $other['id']]);
        $clientKey = $this->faker->regexify('[0-9a-f]{12}');

        $I->login($this->user['email']);
        $I->sendPOST('api/conversations/' . $conv['id'] . '/messages', ['body' => 'from me', 'clientKey' => $clientKey]);
        $I->seeResponseCodeIs(HttpCode::OK);

        // The key is unique per sender: the same key from another account must
        // store a second message, not return the first sender's message.
        $I->login($other['email']);
        $I->sendPOST('api/conversations/' . $conv['id'] . '/messages', ['body' => 'from the other', 'clientKey' => $clientKey]);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeNumRecords(2, 'fs_msg', ['conversation_id' => $conv['id'], 'client_key' => $clientKey]);
        $I->seeNumRecords(1, 'fs_msg', ['conversation_id' => $conv['id'], 'foodsaver_id' => $other['id'], 'body' => 'from the other']);
    }
}
