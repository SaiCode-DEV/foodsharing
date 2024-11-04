<?php

declare(strict_types=1);

namespace Api;

use Codeception\Util\HttpCode as Http;
use Faker\Factory;
use Foodsharing\Modules\Store\DTO\CommonLabel;
use Tests\Support\ApiTester;

class StoreCategoriesApiCest
{
    private $user;
    private $userOrga;
    private $faker;

    public function _before(ApiTester $I): void
    {
        $this->user = $I->createFoodsaver();
        $this->userOrga = $I->createOrga();
        $this->faker = Factory::create('de_DE');
        $I->createStoreCategories();
    }

    public function canFetchStoreCategoriesWhenLoggedIn(ApiTester $I): void
    {
        $I->sendGet('api/storecategories');
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);

        $I->login($this->user['email']);
        $I->sendGet('api/storecategories');
        $I->seeResponseCodeIs(Http::OK);

        $category = $this->getRandomCategoryFromDatabase($I);
        $I->seeResponseContainsJson([['id' => $category->id, 'name' => $category->name]]);
    }

    public function canAddStoreCategoryAsOrga(ApiTester $I): void
    {
        $newCategory = $this->createRandomCategory();

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/storecategories', $newCategory);
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);
        $I->dontSeeInDatabase('fs_betrieb_kategorie', ['name' => $newCategory['name']]);

        $I->login($this->userOrga['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/storecategories', $newCategory);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseContainsJson($newCategory);
        $id = $I->grabDataFromResponseByJsonPath('id')[0];

        $I->seeInDatabase('fs_betrieb_kategorie', ['id' => $id, 'name' => $newCategory['name']]);
    }

    public function canNotAddStoreCategoryAsUser(ApiTester $I): void
    {
        $newCategory = $this->createRandomCategory();

        $I->login($this->user['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/storecategories', $newCategory);
        $I->seeResponseCodeIs(Http::FORBIDDEN);
        $I->dontSeeInDatabase('fs_betrieb_kategorie', ['name' => $newCategory['name']]);
    }

    public function canEditStoreCategoryAsOrga(ApiTester $I): void
    {
        $category = $this->getRandomCategoryFromDatabase($I);
        $newProperties = $this->createRandomCategory();

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/storecategories/' . $category->id, $newProperties);
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);
        $I->seeInDatabase('fs_betrieb_kategorie', ['id' => $category->id, 'name' => $category->name]);

        $I->login($this->userOrga['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/storecategories/' . $category->id, $newProperties);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb_kategorie', ['id' => $category->id, 'name' => $newProperties['name']]);
    }

    public function canNotEditStoreCategoryAsUser(ApiTester $I): void
    {
        $category = $this->getRandomCategoryFromDatabase($I);
        $newProperties = $this->createRandomCategory();

        $I->login($this->user['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/storecategories/' . $category->id, $newProperties);
        $I->seeResponseCodeIs(Http::FORBIDDEN);
        $I->seeInDatabase('fs_betrieb_kategorie', ['id' => $category->id, 'name' => $category->name]);
    }

    public function canNotEditNonExistentStoreCategory(ApiTester $I): void
    {
        $categoryId = 999999;
        $newProperties = $this->createRandomCategory();

        $I->login($this->userOrga['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/storecategories/' . $categoryId, $newProperties);
        $I->seeResponseCodeIs(Http::NOT_FOUND);
        $I->dontSeeInDatabase('fs_betrieb_kategorie', ['id' => $categoryId]);
    }

    private function createRandomCategory(): array
    {
        return [
            'name' => $this->faker->realTextBetween(5, 20)
        ];
    }

    private function getRandomCategoryFromDatabase(ApiTester $I): CommonLabel
    {
        $entries = $I->grabEntriesFromDatabase('fs_betrieb_kategorie');
        $entry = $entries[random_int(0, sizeof($entries) - 1)];

        return new CommonLabel($entry['id'], $entry['name']);
    }
}
