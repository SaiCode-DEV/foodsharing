<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode;
use Tests\Support\ApiTester;

/**
 * @group api-group-2
 */
class BusinessCardApiCest
{
    /**
     * The business card labels its holder as a Foodsaver, so an unverified foodsharer
     * must not be able to generate one — even when they belong to the region (#2749).
     */
    public function unverifiedFoodsharerMayNotGenerateBusinessCard(ApiTester $I): void
    {
        $foodsharer = $I->createFoodsharer();
        $region = $I->createRegion();
        $I->addRegionMember($region['id'], $foodsharer['id']);

        $I->login($foodsharer['email']);
        $I->sendGET('bcard?sub=makeCard&opt=fs:' . $region['id']);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function verifiedFoodsaverMayGenerateBusinessCard(ApiTester $I): void
    {
        $foodsaver = $I->createFoodsaver();
        $region = $I->createRegion();
        $I->addRegionMember($region['id'], $foodsaver['id']);

        $I->login($foodsaver['email']);
        $I->sendGET('bcard?sub=makeCard&opt=fs:' . $region['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
    }
}
