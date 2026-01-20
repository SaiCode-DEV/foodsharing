<?php

declare(strict_types=1);

namespace Api;

use Codeception\Util\HttpCode as Http;
use Faker\Factory;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Store\DTO\CategoryWithType;
use Tests\Support\ApiTester;

/**
 * @group api-group-1
 */
class CategoriesApiCest
{
    private $user;
    private $userAdmin;
    private $faker;

    public function _before(ApiTester $I): void
    {
        $this->user = $I->createFoodsaver();
        $this->userAdmin = $I->createStoreCoordinator();
        $I->createWorkingGroup('Produktteam', ['id' => RegionIDs::PRODUCT_TEAM]);
        $I->addRegionMember(RegionIDs::PRODUCT_TEAM, $this->userAdmin['id']);
        $I->addRegionAdmin(RegionIDs::PRODUCT_TEAM, $this->userAdmin['id']);
        $this->faker = Factory::create('de_DE');
        $I->createStoreCategories();
    }

    public function canFetchStoreCategoriesWhenLoggedIn(ApiTester $I): void
    {
        $I->sendGet('api/categories/store');
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);

        $I->login($this->user['email']);
        $I->sendGet('api/categories/store');
        $I->seeResponseCodeIs(Http::OK);

        $category = $this->getRandomCategoryFromDatabase($I);
        $I->seeResponseContainsJson([['id' => $category->id, 'name' => $category->name]]);
    }

    public function canAddStoreCategoryAsAdmin(ApiTester $I): void
    {
        $newCategory = $this->createRandomCategory();

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/categories/store', $newCategory);
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);
        $I->dontSeeInDatabase('fs_betrieb_kategorie', ['name' => $newCategory['name'], 'type' => $newCategory['subType']]);

        $I->login($this->userAdmin['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/categories/store', $newCategory);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseContainsJson($newCategory);
        $id = $I->grabDataFromResponseByJsonPath('id')[0];

        $I->seeInDatabase('fs_betrieb_kategorie', ['id' => $id, 'name' => $newCategory['name'], 'type' => $newCategory['subType']]);
    }

    public function canNotAddStoreCategoryAsUser(ApiTester $I): void
    {
        $newCategory = $this->createRandomCategory();

        $I->login($this->user['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/categories/store', $newCategory);
        $I->seeResponseCodeIs(Http::FORBIDDEN);
        $I->dontSeeInDatabase('fs_betrieb_kategorie', ['name' => $newCategory['name'], 'type' => $newCategory['subType']]);
    }

    public function canEditStoreCategoryAsAdmin(ApiTester $I): void
    {
        $category = $this->getRandomCategoryFromDatabase($I);
        $newProperties = $this->createRandomCategory();

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/categories/store/' . $category->id, $newProperties);
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);
        $I->seeInDatabase('fs_betrieb_kategorie', ['id' => $category->id, 'name' => $category->name, 'type' => $category->subType]);
        $I->dontSeeInDatabase('fs_betrieb_kategorie', ['id' => $category->id, 'name' => $newProperties['name'], 'type' => $newProperties['subType']]);

        $I->login($this->userAdmin['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/categories/store/' . $category->id, $newProperties);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb_kategorie', ['id' => $category->id, 'name' => $newProperties['name'], 'type' => $newProperties['subType']]);
    }

    public function canNotEditStoreCategoryAsUser(ApiTester $I): void
    {
        $category = $this->getRandomCategoryFromDatabase($I);
        $newProperties = $this->createRandomCategory();

        $I->login($this->user['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/categories/store/' . $category->id, $newProperties);
        $I->seeResponseCodeIs(Http::FORBIDDEN);
        $I->seeInDatabase('fs_betrieb_kategorie', ['id' => $category->id, 'name' => $category->name]);
    }

    public function canNotEditNonExistentStoreCategory(ApiTester $I): void
    {
        $categoryId = 999999;
        $newProperties = $this->createRandomCategory();

        $I->login($this->userAdmin['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/categories/store/' . $categoryId, $newProperties);
        $I->seeResponseCodeIs(Http::NOT_FOUND);
        $I->dontSeeInDatabase('fs_betrieb_kategorie', ['id' => $categoryId]);
    }

    public function canDeleteStoreCategoryAsAdmin(ApiTester $I): void
    {
        $category = $this->getRandomCategoryFromDatabase($I);

        $I->sendDelete('api/categories/store/' . $category->id);
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);
        $I->seeInDatabase('fs_betrieb_kategorie', ['id' => $category->id]);

        $I->login($this->userAdmin['email']);
        $I->sendDelete('api/categories/store/' . $category->id);
        $I->seeResponseCodeIs(Http::OK);
        $I->dontSeeInDatabase('fs_betrieb_kategorie', ['id' => $category->id]);
    }

    public function canMergeStoreCategories(ApiTester $I): void
    {
        $category1 = $this->getRandomCategoryFromDatabase($I);
        do {
            $category2 = $this->getRandomCategoryFromDatabase($I);
        } while ($category1->id === $category2->id);

        $I->login($this->userAdmin['email']);
        $I->sendPost('api/categories/store/' . $category1->id . '/merges/' . $category2->id);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson(0);

        // Check that the second category was merged into the first
        $I->dontSeeInDatabase('fs_betrieb_kategorie', ['id' => $category2->id]);
        $I->seeInDatabase('fs_betrieb_kategorie', ['id' => $category1->id]);
    }

    private function createRandomCategory(): array
    {
        return [
            'name' => $this->faker->realTextBetween(5, 20),
            'subType' => random_int(0, 2)
        ];
    }

    private function getRandomCategoryFromDatabase(ApiTester $I): CategoryWithType
    {
        $entries = $I->grabEntriesFromDatabase('fs_betrieb_kategorie');
        $entry = $entries[random_int(0, sizeof($entries) - 1)];

        return new CategoryWithType($entry['id'], $entry['name'], $entry['type']);
    }
}
