<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Example;
use Codeception\Util\HttpCode;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\UserOptionType;
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

        $I->sendPUT('api/locale?locale=de');
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
        $I->sendPUT('api/locale?locale=' . $example[0]);
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function canNotSetEmptyLocale(ApiTester $I): void
    {
        $I->haveInDatabase('fs_foodsaver_has_options', [
            'foodsaver_id' => $this->user['id'],
            'option_type' => UserOptionType::LOCALE->value,
            'option_value' => 'en',
        ]);

        $I->login($this->user['email']);
        $I->sendPUT('api/locale?locale=');
        $I->seeResponseCodeIs(HttpCode::OK);

        $locale = $I->grabFromDatabase('fs_foodsaver_has_options', 'option_value', [
            'foodsaver_id' => $this->user['id'],
            'option_type' => UserOptionType::LOCALE->value,
        ]);
        $I->assertEquals('de', $locale);
    }

    public function canFetchLocales(ApiTester $I): void
    {
        $I->sendGET('api/locales');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->canSeeResponseContainsJson([
            'languageCode' => 'de',
        ]);
    }
}
