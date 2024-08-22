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
    private const EMAIL = 'email';
    private const ID = 'id';
    private $region;

    public function _before(ApiTester $I): void
    {
        $this->region = $I->createRegion(fillMailbox: false);
        $this->userBot = $I->createAmbassador();
        $I->addRegionAdmin($this->region['id'], $this->userBot['id']);
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
}
