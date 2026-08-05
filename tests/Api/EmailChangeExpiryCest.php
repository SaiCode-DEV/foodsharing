<?php

declare(strict_types=1);

namespace Tests\Api;

use Tests\Support\ApiTester;

/**
 * @group api-group-1
 */
class EmailChangeExpiryCest
{
    public function expiredEmailChangeRequestIsRejected(ApiTester $I): void
    {
        // #2653: pending address changes used to be confirmable forever
        $user = $I->createFoodsaver();
        $I->haveInDatabase('fs_mailchange', [
            'foodsaver_id' => $user['id'],
            'newmail' => 'expired-new@example.com',
            'time' => date('Y-m-d H:i:s', strtotime('-8 days')),
            'token' => 'expiredtoken1234',
        ]);

        $I->sendGET('/user/current/settings/email/verify?token=expiredtoken1234');
        $I->seeInDatabase('fs_foodsaver', ['id' => $user['id'], 'email' => $user['email']]);
    }

    public function freshEmailChangeRequestStillWorks(ApiTester $I): void
    {
        $user = $I->createFoodsaver();
        $I->haveInDatabase('fs_mailchange', [
            'foodsaver_id' => $user['id'],
            'newmail' => 'fresh-new@example.com',
            'time' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'token' => 'freshtoken12345',
        ]);

        $I->sendGET('/user/current/settings/email/verify?token=freshtoken12345');
        $I->seeInDatabase('fs_foodsaver', ['id' => $user['id'], 'email' => 'fresh-new@example.com']);
    }
}
