<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Example;
use Codeception\Util\HttpCode;
use Tests\Support\ApiTester;

/**
 * @group api-group-2
 */
class LocaleApiCest
{
    private $user;

    public function _before(ApiTester $I): void
    {
        $this->user = $I->createFoodsharer();
    }

    public function onlyHaveLocaleWhenLoggedIn(ApiTester $I): void
    {
        $I->sendGET('api/locale');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->sendPOST('api/locale', ['locale' => 'de']);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
    }

    public function haveDefaultLocale(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendGET('api/locale');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson([
            'locale' => 'de'
        ]);
    }

    /**
     * @example["de"]
     * @example["en"]
     * @example["fr"]
     * @example["it"]
     * @example["nb_NO"]
     */
    public function canSetExistingLocale(ApiTester $I, Example $example): void
    {
        $I->login($this->user['email']);
        $I->sendPOST('api/locale', ['locale' => $example[0]]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson([
            'locale' => $example[0]
        ]);
    }

    public function canNotSetEmptyLocale(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendPOST('api/locale', ['locale' => '']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson([
            'locale' => 'de'
        ]);
    }
}
