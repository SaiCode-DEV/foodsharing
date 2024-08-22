<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode as Http;
use Tests\Support\ApiTester;

/**
 * Tests for the food share point api.
 */
class FoodSharePointApiCest
{
    private $user;
    private $region;

    private const EMAIL = 'email';
    private const API_FSPS = 'api/foodSharePoints';
    private const ID = 'id';
    private const TEST_PICTURE = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAAAAAA6fptVAAAACklEQVR4nGNiAAAABgADNjd8qAAAAABJRU5ErkJggg==';

    public function _before(ApiTester $I): void
    {
        $this->user = $I->createFoodsaver();
        $this->region = $I->createRegion(fillMailbox: false);
        $I->addRegionMember($this->region['id'], $this->user['id']);
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
}
