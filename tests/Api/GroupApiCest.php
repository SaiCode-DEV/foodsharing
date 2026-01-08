<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode;
use Tests\Support\ApiTester;

/**
 * @group api-group-2
 */
class GroupApiCest
{
    private $region;

    public function _before(ApiTester $I): void
    {
        $this->region = $I->createRegion();
    }

    public function deleteGroupFailsForAmbassador(ApiTester $I): void
    {
        $ambassador = $I->createAmbassador();
        $I->login($ambassador['email']);
        $I->sendDELETE("api/regions/{$this->region['id']}");
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeInDatabase('fs_bezirk', ['id' => $this->region['id']]);
    }

    public function deleteGroupWorksForOrga(ApiTester $I): void
    {
        $orga = $I->createOrga();
        $I->login($orga['email']);
        $I->sendDELETE("api/regions/{$this->region['id']}");
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeInDatabase('fs_bezirk', ['id' => $this->region['id']]);
    }
}
