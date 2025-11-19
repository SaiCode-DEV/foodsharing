<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode;
use Tests\Support\ApiTester;

/**
 * @group api-group-2
 */
class NewsletterApiCest
{
    private $user;
    private $userOrga;

    public function _before(ApiTester $I): void
    {
        $this->user = $I->createFoodsharer();
        $this->userOrga = $I->createOrga();
    }

    public function foodsaverMayNotTestNewsletter(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendPOST('api/newsletter/test', [
            'address' => 'test@abcdef.com',
            'subject' => 'Subject',
            'message' => 'Message'
        ]);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function invalidEmailAddressIsRejected(ApiTester $I): void
    {
        $I->login($this->userOrga['email']);
        $I->sendPOST('api/newsletter/test', [
            'address' => 'test',
            'subject' => 'Subject',
            'message' => 'Message'
        ]);
        $I->seeResponseCodeIs(HttpCode::UNPROCESSABLE_ENTITY);
    }

    public function validEmailAddressIsAccepted(ApiTester $I): void
    {
        $I->login($this->userOrga['email']);
        $I->sendPOST('api/newsletter/test', [
            'address' => 'test@abcdef.com',
            'subject' => 'Subject',
            'message' => 'Message'
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);
    }
}
