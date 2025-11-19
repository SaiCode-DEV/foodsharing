<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode;
use Tests\Support\ApiTester;

/**
 * @group api-group-2
 */
class ChangeSleepmodeCest
{
    //https://foodsharing.de/user/current/settings?sub=sleeping
    public function pageDisplaysWithNullValues(ApiTester $I): void
    {
        $user = $I->createFoodsaver(null, ['sleep_from' => null, 'sleep_status' => 1]);
        $I->login($user['email']);
        $I->sendGET('/user/current/settings?sleeping');
        $I->seeResponseCodeIs(HttpCode::OK);
    }
}
