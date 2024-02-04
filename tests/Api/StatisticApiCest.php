<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode as Http;
use DateInterval;
use DateTime;
use Tests\Support\ApiTester;

use function json_decode;

class StatisticApiCest
{
    private $region;
    private $foodsaver;
    private $foodsaverNotInRegion;

    public function _before(ApiTester $I)
    {
        $birthday = new DateTime();
        $birthday = $birthday->sub(DateInterval::createFromDateString('19 year'));

        $this->region = $I->createRegion();
        $this->foodsaver = $I->createFoodsaver(
            extra_params: [
                'bezirk_id' => $this->region['id'],
                'geb_datum' => $birthday->format('Y-m-d'),
            ]
        );
        $this->foodsaverNotInRegion = $I->createFoodsaver(
            extra_params: [
                'geschlecht' => $this->foodsaver['geschlecht'],
                'geb_datum' => $this->foodsaver['geb_datum'],
            ]
        );
        $I->haveInDatabase(
            'fs_foodsaver_has_bezirk',
            ['foodsaver_id' => $this->foodsaverNotInRegion['id'], 'bezirk_id' => $this->region['id'], 'active' => 1]
        );
    }

    public function canListGenderStatistics(ApiTester $I): void
    {
        $I->sendGet(
            'api/statistics/regions/' . $this->region['id'] . '/gender'
        );
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();

        $I->seeResponseContainsJson(['gender' => $this->foodsaver['geschlecht'], 'numberOfGender' => 2]);
    }

    public function canListGenderInHomeRegionStatistics(ApiTester $I): void
    {
        $I->sendGet(
            'api/statistics/regions/' . $this->region['id'] . '/gender', ['homeRegion' => 'true']
        );
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();

        $I->seeResponseContainsJson(['gender' => $this->foodsaver['geschlecht'], 'numberOfGender' => 1]);
    }

    public function canListAgeBandStatistics(ApiTester $I): void
    {
        $I->sendGet(
            'api/statistics/regions/' . $this->region['id'] . '/age-band'
        );
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['ageBand' => '18-25', 'numberOfAgeBand' => 2]);
    }

    public function canListAgeBandInHomeRegionStatistics(ApiTester $I): void
    {
        $I->sendGet(
            'api/statistics/regions/' . $this->region['id'] . '/age-band', ['homeRegion' => 'true']
        );
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['ageBand' => '18-25', 'numberOfAgeBand' => 1]);
    }

    public function canListStatistic(ApiTester $I): void
    {
        $I->sendGet('api/statistics');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();

        $response = json_decode($I->grabResponse(), true);

        $I->seeResponseContainsJson([
            'generalStatistic' => [
                'fetchWeight' => 33829400.5,
                'fetchCount' => 2116647,
                'cooperationsCount' => 7031,
                'botCount' => 1004,
                'foodsaverCount' => 74600,
                'fairteilerCount' => 891,
                'totalBaskets' => 0,
                'avgWeeklyBaskets' => 0,
                'countAllFoodsaver' => 2,
                'countActiveFoodSharePoints' => 0,
                'avgDailyFetchCount' => 0,
            ],
            'regionsActivity' => [
                'pickupOverAllTime' => [
                    [
                        'name' => 'Arbeitsgruppen Überregional',
                        'fetchWeight' => 5176.0,
                        'fetchCount' => 208,
                    ],
                ],
                'pickupInDefinedPeriod' => [],
                'pickupCurrentMonth' => [],
            ],
            'foodsaverActivity' => [
                'pickupOverAllTime' => [
                    [
                        'name' => $response['foodsaverActivity']['pickupOverAllTime'][0]['name'],
                        'fetchWeight' => 0.0,
                        'fetchCount' => 0
                    ],
                    [
                        'name' => $response['foodsaverActivity']['pickupOverAllTime'][1]['name'],
                        'fetchWeight' => 0.0,
                        'fetchCount' => 0
                    ],
                ],
                'pickupInDefinedPeriod' => [],
                'pickupCurrentMonth' => []
            ],
        ]);
    }
}
