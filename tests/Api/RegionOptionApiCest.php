<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode as Http;
use Foodsharing\Modules\Core\DBConstants\Region\RegionOptionType;
use Tests\Support\ApiTester;

/**
 * Tests for the RegionOption api.
 * {"enableReportButton":false,"enableMediationButton":false,"regionPickupRuleActive":true,"regionPickupRuleTimespan":7,"regionPickupRuleLimit":4,"regionPickupRuleLimitDay":2,"regionPickupRuleInactive":12}.
 */
class RegionOptionApiCest
{
    private $userBot;
    private const string EMAIL = 'email';
    private const string ID = 'id';
    private $region;

    public function _before(ApiTester $I): void
    {
        $this->region = $I->createRegion(fillMailbox: false);
        $this->userBot = $I->createAmbassador();
        $I->addRegionAdmin($this->region['id'], $this->userBot['id']);

        // Initialise the regions options with random values
        $values = [
            RegionOptionType::ENABLE_REPORT_BUTTON => (rand(0, 1) == 1),
            RegionOptionType::ENABLE_MEDIATION_BUTTON => (rand(0, 1) == 1),
            RegionOptionType::REGION_PICKUP_RULE_ACTIVE => (rand(0, 1) == 1),
            RegionOptionType::REGION_PICKUP_RULE_TIMESPAN_DAYS => random_int(1, 31),
            RegionOptionType::REGION_PICKUP_RULE_LIMIT_NUMBER => random_int(1, 14),
            RegionOptionType::REGION_PICKUP_RULE_LIMIT_DAY_NUMBER => random_int(1, 100),
            RegionOptionType::REGION_PICKUP_RULE_INACTIVE_HOURS => array_rand([4, 8, 12, 16, 24, 36, 48, 60, 72]),
            RegionOptionType::ALLOW_HIDING_IN_FORUM => (rand(0, 1) == 1),
            RegionOptionType::REPORT_REASON_OPTIONS => (rand(0, 1) == 1),
            RegionOptionType::REPORT_REASON_OTHER => (rand(0, 1) == 1)
        ];
        foreach ($values as $type => $value) {
            $I->haveInDatabase('fs_region_options', [
                'region_id' => $this->region['id'],
                'option_type' => $type,
                'option_value' => $value
            ]);
        }
    }

    public function addRegionOption(ApiTester $I): void
    {
        $I->login($this->userBot[self::EMAIL]);
        $I->sendPOST('api/region/' . $this->region['id'] . '/options', ['enableReportButton' => true, 'selectedReportReasonOptions' => 1, 'enableReportReasonOther' => true, 'enableMediationButton' => true, 'regionPickupRuleActive' => true, 'regionPickupRuleTimespan' => 7, 'regionPickupRuleLimit' => 4, 'regionPickupRuleLimitDay' => 2, 'regionPickupRuleInactive' => 12]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeInDatabase('fs_region_options', [
            'region_id' => $this->region['id'],
            'option_type' => RegionOptionType::ENABLE_REPORT_BUTTON,
            'option_value' => '1'
        ]);
        $I->seeInDatabase('fs_region_options', [
            'region_id' => $this->region['id'],
            'option_type' => RegionOptionType::REPORT_REASON_OPTIONS,
            'option_value' => '1'
        ]);
        $I->seeInDatabase('fs_region_options', [
            'region_id' => $this->region['id'],
            'option_type' => RegionOptionType::REPORT_REASON_OTHER,
            'option_value' => '1'
        ]);
        $I->seeInDatabase('fs_region_options', [
            'region_id' => $this->region['id'],
            'option_type' => RegionOptionType::ENABLE_MEDIATION_BUTTON,
            'option_value' => '1'
        ]);
        $I->seeInDatabase('fs_region_options', [
            'region_id' => $this->region['id'],
            'option_type' => RegionOptionType::REGION_PICKUP_RULE_ACTIVE,
            'option_value' => '1'
        ]);
        $I->seeInDatabase('fs_region_options', [
            'region_id' => $this->region['id'],
            'option_type' => RegionOptionType::REGION_PICKUP_RULE_TIMESPAN_DAYS,
            'option_value' => '7'
        ]);
        $I->seeInDatabase('fs_region_options', [
            'region_id' => $this->region['id'],
            'option_type' => RegionOptionType::REGION_PICKUP_RULE_LIMIT_NUMBER,
            'option_value' => '4'
        ]);
        $I->seeInDatabase('fs_region_options', [
            'region_id' => $this->region['id'],
            'option_type' => RegionOptionType::REGION_PICKUP_RULE_LIMIT_DAY_NUMBER,
            'option_value' => '2'
        ]);
        $I->seeInDatabase('fs_region_options', [
            'region_id' => $this->region['id'],
            'option_type' => RegionOptionType::REGION_PICKUP_RULE_INACTIVE_HOURS,
            'option_value' => '12'
        ]);
    }

    public function canFetchRegionOptions(ApiTester $I): void
    {
        $I->login($this->userBot[self::EMAIL]);
        $I->sendGET('api/region/' . $this->region['id'] . '/options');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();

        $comparisonBool = [
            'isReportButtonEnabled' => RegionOptionType::ENABLE_REPORT_BUTTON,
            'isMediationButtonEnabled' => RegionOptionType::ENABLE_MEDIATION_BUTTON,
            'isRegionPickupRuleActive' => RegionOptionType::REGION_PICKUP_RULE_ACTIVE,
            'allowHidingInForum' => RegionOptionType::ALLOW_HIDING_IN_FORUM,
            'selectedReportReasonOptions' => RegionOptionType::REPORT_REASON_OPTIONS,
            'isReportReasonOtherEnabled' => RegionOptionType::REPORT_REASON_OTHER,
        ];
        $comparisonInt = [
            'regionPickupRuleTimespanDays' => RegionOptionType::REGION_PICKUP_RULE_TIMESPAN_DAYS,
            'regionPickupRuleLimitNumber' => RegionOptionType::REGION_PICKUP_RULE_LIMIT_NUMBER,
            'regionPickupRuleLimitDayNumber' => RegionOptionType::REGION_PICKUP_RULE_LIMIT_DAY_NUMBER,
            'regionPickupRuleInactiveHours' => RegionOptionType::REGION_PICKUP_RULE_INACTIVE_HOURS,
        ];

        foreach ($comparisonBool as $name => $type) {
            $I->assertEquals(
                $I->grabDataFromResponseByJsonPath($name)[0],
                boolval($I->grabFromDatabase('fs_region_options', 'option_value', [
                    'region_id' => $this->region['id'],
                    'option_type' => $type
                ]))
            );
        }
        foreach ($comparisonInt as $name => $type) {
            $I->assertEquals(
                $I->grabDataFromResponseByJsonPath($name)[0],
                intval($I->grabFromDatabase('fs_region_options', 'option_value', [
                    'region_id' => $this->region['id'],
                    'option_type' => $type
                ]))
            );
        }
    }
}
