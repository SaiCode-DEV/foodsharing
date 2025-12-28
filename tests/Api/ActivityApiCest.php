<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Tests\Support\ApiTester;

/**
 * @group api-group-1
 */
class ActivityApiCest
{
    private $user;

    public function _before(ApiTester $I): void
    {
        $this->user = $I->createFoodsharer();
    }

    public function canGetFilterDashboardActivities(ApiTester $I): void
    {
        $I->sendGet('api/activities/filters');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->login($this->user['email']);
        $I->sendGet('api/activities/filters');
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function setFilterGetsWrongData(ApiTester $I): void
    {
        $I->sendGet('api/activities/filters');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->login($this->user['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/activities/filters', []);
        $I->seeResponseCodeIs(HttpCode::UNPROCESSABLE_ENTITY);
    }

    public function canSetFilterDashboardActivities(ApiTester $I): void
    {
        $I->sendGet('api/activities/filters');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $filter = [
            'excluded' => [
                [
                    'id' => RegionIDs::STORE_CHAIN_GROUP,
                    'index' => 'bezirk',
                ],
            ],
        ];
        $I->login($this->user['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/activities/filters', $filter);
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function canGetActivityUpdatesAction(ApiTester $I): void
    {
        $I->sendGet('api/activities/updates');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->login($this->user['email']);
        $I->sendGet('api/activities/updates');
        $I->seeResponseCodeIs(HttpCode::OK);
    }
}
