<?php

declare(strict_types=1);

namespace Tests\Api;

use Faker\Factory;
use Faker\Generator;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\ApiTester;

/**
 * @group api-group-2
 */
class EmailVerificationApiCest
{
    private Generator $faker;
    private array $verifiedUser;
    private array $unverifiedUser;

    public function _before(ApiTester $I): void
    {
        $this->faker = Factory::create('de_DE');

        $this->verifiedUser = $I->createFoodsaver(null, ['active' => 1]);
        $this->unverifiedUser = $I->createFoodsaver(null, ['active' => 0]);
    }

    public function doReceiveEmailIfNotYetVerified(ApiTester $I): void
    {
        $I->sendPut('api/email-verification', ['address' => $this->unverifiedUser['email']]);
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->expectNumMails(1, 5);
        $email = $I->getMails()[0];
        $I->assertRegExp('/http:\/\/.*login\?sub=activate/', $email->html, 'email should contain an activation link');
    }

    public function doNotReceiveEmailIfAlreadyVerified(ApiTester $I): void
    {
        $I->sendPut('api/email-verification', ['address' => $this->verifiedUser['email']]);
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->expectNumMails(0, 5);
    }

    public function doNotReceiveEmailIfAddressIsNotRegistered(ApiTester $I): void
    {
        $I->sendPut('api/email-verification', ['address' => $this->faker->email()]);
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->expectNumMails(0, 5);
    }
}
