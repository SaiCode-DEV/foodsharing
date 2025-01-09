<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode as Http;
use Faker\Factory;
use Tests\Support\ApiTester;

/**
 * Tests for the user api.
 */
class BananaApiCest
{
    private $user;
    private $user2;
    private $user3;
    private $faker;

    private const string EMAIL = 'email';
    private const string API_USER = 'api/user';
    private const string ID = 'id';

    public function _before(ApiTester $I): void
    {
        $this->user = $I->createFoodsaver();
        $this->user2 = $I->createFoodsaver();
        $this->user3 = $I->createFoodsaver();

        $this->faker = Factory::create('de_DE');
    }

    public function canGiveBanana(ApiTester $I): void
    {
        $testUser = $I->createFoodsaver();
        $I->login($this->user[self::EMAIL]);
        $message = $this->createRandomText(100, 150);
        $I->sendPUT(self::API_USER . '/' . $testUser['id'] . '/banana', ['message' => $message]);
        $I->seeResponseContainsJson([
            'message' => $message,
            'user' => ['id' => $this->user['id']],
        ]);

        // Check for Bell as well
        $bellIdentifier = 'banana-' . $testUser[self::ID] . '-' . $this->user[self::ID];
        $I->seeInDatabase('fs_bell', ['identifier' => $bellIdentifier]);
        $bellId = $I->grabFromDatabase('fs_bell', 'id', ['identifier' => $bellIdentifier]);
        $I->seeInDatabase('fs_foodsaver_has_bell', [
            'foodsaver_id' => $testUser[self::ID],
            'bell_id' => $bellId,
        ]);

        $I->seeResponseCodeIs(Http::OK);
    }

    public function canNotGiveBananaWithShortMessage(ApiTester $I): void
    {
        $testUser = $I->createFoodsaver();
        $I->login($this->user[self::EMAIL]);
        $I->sendPUT(self::API_USER . '/' . $testUser['id'] . '/banana', ['message' => $this->faker->text(50)]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);
    }

    public function canNotGiveBananaTwice(ApiTester $I): void
    {
        $testUser = $I->createFoodsaver();
        $I->login($this->user[self::EMAIL]);
        $I->sendPUT(self::API_USER . '/' . $testUser['id'] . '/banana', ['message' => $this->createRandomText(100, 150)]);
        $I->seeResponseCodeIs(Http::OK);
        $I->sendPUT(self::API_USER . '/' . $testUser['id'] . '/banana', ['message' => $this->createRandomText(100, 150)]);
        $I->seeResponseCodeIs(Http::FORBIDDEN);
    }

    public function canNotGiveBananaToMyself(ApiTester $I): void
    {
        $I->login($this->user[self::EMAIL]);
        $I->sendPUT(self::API_USER . '/' . $this->user['id'] . '/banana', ['message' => $this->createRandomText(100, 150)]);
        $I->seeResponseCodeIs(Http::FORBIDDEN);
    }

    public function canDeleteOwnBanana(ApiTester $I): void
    {
        $I->giveBanana($this->user2['id'], $this->user['id'], 'message');
        $I->login($this->user[self::EMAIL]);
        $I->sendDelete(self::API_USER . '/' . $this->user['id'] . '/banana/' . $this->user2['id']);
        $I->seeResponseCodeIs(Http::OK);
    }

    public function canDeleteGivenBanana(ApiTester $I): void
    {
        $I->giveBanana($this->user2['id'], $this->user['id'], 'message');
        $I->login($this->user2[self::EMAIL]);
        $I->sendDelete(self::API_USER . '/' . $this->user['id'] . '/banana/' . $this->user2['id']);
        $I->seeResponseCodeIs(Http::OK);
    }

    public function cantDeleteOtherBanana(ApiTester $I): void
    {
        $I->giveBanana($this->user2['id'], $this->user3['id'], 'message');
        $I->login($this->user[self::EMAIL]);
        $I->sendDelete(self::API_USER . '/' . $this->user3['id'] . '/banana/' . $this->user2['id']);
        $I->seeResponseCodeIs(Http::FORBIDDEN);
    }

    public function canLoadBananaMetadata(ApiTester $I): void
    {
        $I->giveBanana($this->user['id'], $this->user2['id'], 'message');
        $I->login($this->user[self::EMAIL]);
        $I->sendGet(self::API_USER . '/' . $this->user2['id'] . '/banana/meta');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseContainsJson([
            'receivedCount' => 1,
            'mayGiveBanana' => false,
            'mayDeleteBananas' => false,
        ]);
    }

    public function canLoadReceivedBananas(ApiTester $I): void
    {
        $I->giveBanana($this->user['id'], $this->user2['id'], 'message');
        $I->login($this->user[self::EMAIL]);
        $I->sendGet(self::API_USER . '/' . $this->user2['id'] . '/banana/received');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseContainsJson([[
            'message' => 'message',
            'user' => ['id' => $this->user['id']],
        ]]);
    }

    public function canLoadSentBananas(ApiTester $I): void
    {
        $I->giveBanana($this->user['id'], $this->user2['id'], 'message');
        $I->login($this->user[self::EMAIL]);
        $I->sendGet(self::API_USER . '/' . $this->user['id'] . '/banana/sent');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseContainsJson([[
            'message' => 'message',
            'user' => ['id' => $this->user2['id']],
        ]]);
    }

    private function createRandomText(int $minLength, int $maxLength): string
    {
        $text = $this->faker->text($maxLength);
        while (strlen((string)$text) < $minLength) {
            $text .= ' ' . $this->faker->text(($maxLength + $minLength) / 2 - strlen((string)$text));
        }

        return $text;
    }
}
