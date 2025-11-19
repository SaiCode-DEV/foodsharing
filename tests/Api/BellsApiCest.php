<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode;
use Tests\Support\ApiTester;

/**
 * @group api-group-2
 */
class BellsApiCest
{
    private $user;
    private $bells;

    public function _before(ApiTester $I): void
    {
        $this->user = $I->createFoodsharer();

        $this->bells = [];
        foreach (range(0, 10) as $_) {
            $this->bells[] = $I->addBells([$this->user]);
        }
    }

    public function canRequestBells(ApiTester $I): void
    {
        $I->sendGET('api/bells');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->login($this->user['email']);
        $I->sendGET('api/bells');
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function canDeleteBell(ApiTester $I): void
    {
        $I->sendDELETE('api/bells', ['ids' => [$this->bells[0]]]);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->seeInDatabase('fs_foodsaver_has_bell', [
            'foodsaver_id' => $this->user['id'],
            'bell_id' => $this->bells[0]
        ]);

        $I->login($this->user['email']);
        $I->sendDELETE('api/bells', ['ids' => [$this->bells[0]]]);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->dontSeeInDatabase('fs_foodsaver_has_bell', [
            'foodsaver_id' => $this->user['id'],
            'bell_id' => $this->bells[0]
        ]);
    }

    public function canSetReadStatusOfBell(ApiTester $I): void
    {
        $bellIds = array_slice($this->bells, 0, 3);

        $I->sendPATCH('api/bells/readStatus?read=1', ['ids' => $bellIds]);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->login($this->user['email']);
        $I->sendPATCH('api/bells/readStatus?read=1', ['ids' => $bellIds]);
        $I->seeResponseCodeIs(HttpCode::OK);

        foreach ($bellIds as $id) {
            $I->seeInDatabase('fs_foodsaver_has_bell', [
                'foodsaver_id' => $this->user['id'],
                'bell_id' => $id,
                'seen' => 1
            ]);
        }

        foreach (array_diff($this->bells, $bellIds) as $id) {
            $I->seeInDatabase('fs_foodsaver_has_bell', [
                'foodsaver_id' => $this->user['id'],
                'bell_id' => $id,
                'seen' => 0
            ]);
        }

        $I->login($this->user['email']);
        $I->sendPATCH('api/bells/readStatus?read=0', ['ids' => $bellIds]);
        $I->seeResponseCodeIs(HttpCode::OK);

        foreach ($bellIds as $id) {
            $I->seeInDatabase('fs_foodsaver_has_bell', [
                'foodsaver_id' => $this->user['id'],
                'bell_id' => $id,
                'seen' => 0
            ]);
        }
    }
}
