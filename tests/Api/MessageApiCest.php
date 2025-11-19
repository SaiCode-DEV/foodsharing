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
}
