<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Tests\Support\ApiTester;

class ReportApiCest
{
    private $parentRegion;
    private $region;
    private $reportGroup;
    private $reportGroupAdmin;
    private $arbitrationGroup;
    private $arbitrationGroupAdmin;
    private $subRegion;
    private $foodsaver;
    private $subRegionFoodsaver;
    private $foodsharer;

    public function _before(ApiTester $I): void
    {
        //Create regions
        $this->parentRegion = $I->createRegion(fillMailbox: false);
        $this->region = $I->createRegion(null, ['parent_id' => $this->parentRegion['id']], false);
        $this->subRegion = $I->createRegion(null, ['parent_id' => $this->region['id']], false);

        // Create Workgroup and Workgroup Function for report
        $this->reportGroup = $I->createWorkingGroup('Meldungsbearbeitung', ['parent_id' => $this->region['id']]);
        $I->haveInDatabase('fs_region_function', ['region_id' => $this->reportGroup['id'], 'function_id' => WorkgroupFunction::REPORT, 'target_id' => $this->region['id']]);

        //create report admins and assign to report workgroup
        $this->reportGroupAdmin = $I->createStoreCoordinator(null, ['bezirk_id' => $this->region['id']]);
        $I->addRegionMember($this->reportGroup['id'], $this->reportGroupAdmin['id']);
        $I->addRegionAdmin($this->reportGroup['id'], $this->reportGroupAdmin['id']);

        // same for arbitration workgroup
        $this->arbitrationGroup = $I->createWorkingGroup('Schiedsstelle', ['parent_id' => $this->region['id']]);
        $I->haveInDatabase('fs_region_function', ['region_id' => $this->arbitrationGroup['id'], 'function_id' => WorkgroupFunction::ARBITRATION, 'target_id' => $this->region['id']]);
        $this->arbitrationGroupAdmin = $I->createStoreCoordinator(null, ['bezirk_id' => $this->region['id']]);
        $I->addRegionMember($this->arbitrationGroup['id'], $this->arbitrationGroupAdmin['id']);
        $I->addRegionAdmin($this->arbitrationGroup['id'], $this->arbitrationGroupAdmin['id']);

        $this->foodsaver = $I->createFoodsaver(null, ['bezirk_id' => $this->region['id']]);
        $this->subRegionFoodsaver = $I->createFoodsaver(null, ['bezirk_id' => $this->subRegion['id']]);
        $this->foodsharer = $I->createFoodsharer();
    }

    public function seeReportAboutFoodsaverInRegion(ApiTester $I): void
    {
        $I->login($this->reportGroupAdmin['email']);
        $I->addReport($this->foodsaver['id'], $this->foodsaver['id']);
        $I->sendGET($I->apiReportListForRegion($this->region['id']));
        $I->seeResponseCodeIs(HttpCode::OK);
        codecept_debug($I->seeResponseCodeIs(HttpCode::OK));
        codecept_debug($I->seeResponseContainsJson(['fs_id' => $this->foodsaver['id'], 'rp_id' => $this->foodsaver['id']]));
    }

    public function cantSeeReportAboutFoodsaverInSubRegion(ApiTester $I): void
    {
        $I->login($this->reportGroupAdmin['email']);
        $I->addReport($this->foodsharer['id'], $this->subRegionFoodsaver['id']);
        $I->sendGET($I->apiReportListForRegion($this->region['id']));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->cantSeeResponseContainsJson(['fs_id' => $this->subRegionFoodsaver['id'], 'rp_id' => $this->foodsharer['id']]);
    }

    public function dontSeeReportAboutSelf(ApiTester $I): void
    {
        $I->login($this->reportGroupAdmin['email']);
        $I->addReport($this->foodsharer['id'], $this->reportGroupAdmin['id']);
        $I->addReport($this->subRegionFoodsaver['id'], $this->reportGroupAdmin['id']);
        $I->sendGET($I->apiReportListForRegion($this->region['id']));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeResponseContainsJson(['fs_id' => $this->reportGroupAdmin['id']]);
    }

    public function dontSeeReportAboutFoodsharerReporterNotInRegion(ApiTester $I): void
    {
        $I->login($this->reportGroupAdmin['email']);
        $I->addReport($this->reportGroupAdmin['id'], $this->foodsharer['id']);
        $I->sendGET($I->apiReportListForRegion($this->region['id']));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeResponseContainsJson(['fs_id' => $this->foodsharer['id']]);
    }

    public function ArbitrationAdminSeesReportAboutReportAdmin(ApiTester $I): void
    {
        $I->login($this->arbitrationGroupAdmin['email']);
        $I->addReport($this->foodsharer['id'], $this->reportGroupAdmin['id']);
        $I->sendGET($I->apiReportListForRegion($this->region['id']));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['fs_id' => $this->reportGroupAdmin['id'], 'rp_id' => $this->foodsharer['id']]);
    }

    public function foodsaverCannotAccessReports(ApiTester $I): void
    {
        $I->login($this->foodsaver['email']);
        $I->sendGET($I->apiReportListForRegion($this->region['id']));
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function foodsharerCannotAccessReports(ApiTester $I): void
    {
        $I->login($this->foodsharer['email']);
        $I->sendGET($I->apiReportListForRegion($this->region['id']));
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }
}
