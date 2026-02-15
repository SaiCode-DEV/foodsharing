<?php

declare(strict_types=1);

namespace Tests\Functional;

use Tests\Support\FunctionalTester;

class LoginApiFuncCest
{
    public function checkLogin(FunctionalTester $I): void
    {
        $pass = 'pw';
        $user = $I->createFoodsaver($pass);
        $I->sendPOST('api/login', [
            'email' => $user['email'],
            'password' => $pass
            ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'id' => $user['id'],
            'name' => $user['name']
        ]);
    }

    public function loginFailsWrongUserPassword(FunctionalTester $I): void
    {
        $user['email'] = 'thissurelydoesnotexist@example.com';
        $pass = '123';
        $I->sendPOST('api/login', [
            'email' => $user['email'],
            'password' => $pass
        ]);
        $I->seeResponseCodeIs(401);
    }

    public function loginFailsWrongPasswordForExistingUser(FunctionalTester $I): void
    {
        $pass = 'pw';
        $user = $I->createFoodsaver($pass);
        $I->sendPOST('api/login', [
            'email' => $user['email'],
            'password' => 'asdf'
        ]);
        $I->seeResponseCodeIs(401);
    }
}
