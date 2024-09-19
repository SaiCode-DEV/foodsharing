<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode as Http;
use Faker\Factory;
use Faker\Generator;
use Tests\Support\ApiTester;

/**
 * Tests for the food share point api.
 */
class FoodSharePointApiCest
{
    private Generator $faker;
    private $user;
    private $userAmbassador;
    private $region;

    private const EMAIL = 'email';
    private const API_FSPS = 'api/foodSharePoints';
    private const ID = 'id';
    private const TEST_PICTURE = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAAAAAA6fptVAAAACklEQVR4nGNiAAAABgADNjd8qAAAAABJRU5ErkJggg==';

    public function _before(ApiTester $I): void
    {
        $this->faker = Factory::create('de_DE');

        $this->user = $I->createFoodsaver();
        $this->region = $I->createRegion(fillMailbox: false);
        $I->addRegionMember($this->region['id'], $this->user['id']);

        $this->userAmbassador = $I->createAmbassador();
        $I->addRegionMember($this->region['id'], $this->userAmbassador['id']);
        $I->addRegionAdmin($this->region['id'], $this->userAmbassador['id']);
    }

    public function getFoodSharePoint(ApiTester $I): void
    {
        $fsp = $I->createFoodSharePoint($this->user[self::ID]);

        $I->login($this->user[self::EMAIL]);
        $I->sendGET(self::API_FSPS . '/' . $fsp[self::ID]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
    }

    public function listNearbyFoodSharePoints(ApiTester $I): void
    {
        $I->createFoodSharePoint($this->user[self::ID]);

        $I->login($this->user[self::EMAIL]);
        $I->sendGET(self::API_FSPS . '/nearby?distance=30');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();

        $I->sendGET(self::API_FSPS . '/nearby?lat=50&lon=9&distance=30');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();

        $I->sendGET(self::API_FSPS . '/nearby?lat=50&lon=9&distance=51');
        $I->seeResponseCodeIs(Http::BAD_REQUEST);
        $I->seeResponseIsJson();
    }

    public function canListFoodSharePointsInRegion(ApiTester $I)
    {
        $I->login($this->user[self::EMAIL]);
        $I->sendGET('api/regions/' . $this->region['id'] . '/foodSharePoints');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
    }

    public function canNotListFoodSharePointsInRegionWithoutLogin(ApiTester $I)
    {
        $I->sendGET('api/regions/' . $this->region['id'] . '/foodSharePoints');
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);
    }

    public function canNotAddFoodSharePointWithoutLogin(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('api/regions/' . $this->region['id'] . '/foodSharePoints');
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);
    }

    public function canNotAddFoodSharePointWithoutBeingARegionMember(ApiTester $I): void
    {
        $fsp = $this->createRandomFoodSharePoint();

        $user2 = $I->createFoodsaver();
        $I->login($user2['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('api/regions/' . $this->region['id'] . '/foodSharePoints', $fsp);
        $I->seeResponseCodeIs(Http::FORBIDDEN);
    }

    public function canSuggestFoodSharePoint(ApiTester $I): void
    {
        $fsp = $this->createRandomFoodSharePoint();

        $I->login($this->user['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('api/regions/' . $this->region['id'] . '/foodSharePoints', $fsp);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();

        $I->seeResponseContainsJson(['isAdded' => false]);
        $id = $I->grabDataFromResponseByJsonPath('id')[0];
        $I->seeInDatabase('fs_fairteiler', [
            'id' => $id,
            'status' => 0
        ]);
    }

    public function canAddFoodSharePoint(ApiTester $I): void
    {
        $fsp = $this->createRandomFoodSharePoint();

        $I->login($this->userAmbassador['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('api/regions/' . $this->region['id'] . '/foodSharePoints', $fsp);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();

        $I->seeResponseContainsJson(['isAdded' => true]);
        $id = $I->grabDataFromResponseByJsonPath('id')[0];
        $I->seeInDatabase('fs_fairteiler', [
            'id' => $id,
            'status' => 1
        ]);
    }

    private function createRandomFoodSharePoint(): array
    {
        return [
            'regionId' => $this->region['id'],
            'name' => $this->faker->city(),
            'description' => $this->faker->text(200),
            'picture' => '',
            'address' => $this->faker->address(),
            'postalCode' => $this->faker->postcode(),
            'city' => $this->faker->city(),
            'location' => [
                'lat' => $this->faker->latitude(55, 46),
                'lon' => $this->faker->longitude(4, 16),
            ],
        ];
    }
}
