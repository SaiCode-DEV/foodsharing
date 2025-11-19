<?php

declare(strict_types=1);

namespace Api;

use Codeception\Util\HttpCode as Http;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Tests\Support\ApiTester;

/**
 * @group api-group-2
 */
class ResourceApiCest
{
    public function canGetListOfResourceCategories(ApiTester $I): void
    {
        $this->addResourceCategories($I);
        $I->sendGet('api/resources/categories');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseContainsJson([['name' => 'A'], ['name' => 'B'], ['name' => 'C']]); // Example categories
    }

    public function canOnlyGetResourcesForCorrectRegionTypes(ApiTester $I): void
    {
        [$user, $region] = $this->setupUserAndRegion($I);
        $expectedResponses = [
            UnitType::COUNTRY => Http::FORBIDDEN,
            UnitType::FEDERAL_STATE => Http::FORBIDDEN,
            UnitType::CITY => Http::OK,
            UnitType::WORKING_GROUP => Http::OK,
        ];

        foreach ($expectedResponses as $type => $responseCode) {
            $I->updateInDatabase('fs_bezirk', ['type' => $type], ['id' => $region['id']]);
            $I->sendGet('api/region/' . $region['id'] . '/resources');
            $I->seeResponseCodeIs($responseCode);
        }
    }

    public function canSeePrivateResourcesOnlyOfBuddys(ApiTester $I): void
    {
        [$user, $region] = $this->setupUserAndRegion($I);
        $buddy = $I->createFoodsaver();
        $I->addRegionMember($region['id'], $buddy['id']);

        $resourceId = $I->addResource($buddy['id'], 'resource', null, [], true, 3);
        $I->sendGet('api/region/' . $region['id'] . '/resources');
        $I->seeResponseCodeIs(Http::OK);
        $I->dontSeeResponseContainsJson([['id' => $resourceId]]);

        $I->addBuddy($user['id'], $buddy['id']);
        $I->sendGet('api/region/' . $region['id'] . '/resources');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseContainsJson([['id' => $resourceId, 'isPrivate' => true]]);
    }

    public function canGetResourcesForRegion(ApiTester $I): void
    {
        [$user, $region] = $this->setupUserAndRegion($I);
        $categoryIds = $this->addResourceCategories($I);
        $I->addResource($user['id'], 'resource1', 'description', [$categoryIds[0], $categoryIds[1]], false, 4);
        $I->sendGet('api/region/' . $region['id'] . '/resources');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseContainsJson([[
            'name' => 'resource1',
            'description' => 'description',
            'categories' => [$categoryIds[0], $categoryIds[1]],
            'isPrivate' => false,
            'openness' => 4,
            'isHomeRegion' => false,
            'isPrivate' => false,
            'images' => [],
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
            ],
        ]]);
    }

    public function canAddResources(ApiTester $I): void
    {
        $user = $this->setupUser($I);
        $categoryIds = $this->addResourceCategories($I);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/resources', [
            'name' => 'resource1',
            'description' => 'description',
            'categories' => [$categoryIds[0], $categoryIds[1]],
            'isPrivate' => false,
            'openness' => 4,
            'images' => [],
        ]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseContainsJson([
            'name' => 'resource1',
            'description' => 'description',
            'categories' => [$categoryIds[0], $categoryIds[1]],
            'isPrivate' => false,
            'openness' => 4,
            'images' => [],
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
            ],
        ]);
        $I->seeInDatabase('fs_resource', [
            'foodsaver_id' => $user['id'],
            'name' => 'resource1',
            'description' => 'description',
            'is_private' => false,
            'openness' => 4,
        ]);
        $resourceId = $I->grabFromDatabase('fs_resource', 'id', ['foodsaver_id' => $user['id'], 'name' => 'resource1']);
        $I->seeInDatabase('fs_resource_has_category', [
            'resource_id' => $resourceId,
            'category_id' => $categoryIds[0],
        ]);
        $I->seeInDatabase('fs_resource_has_category', [
            'resource_id' => $resourceId,
            'category_id' => $categoryIds[1],
        ]);
    }

    public function canNotAddTooManyResources(ApiTester $I): void
    {
        $this->setupUser($I);
        for ($i = 1; $i <= 11; ++$i) {
            $I->haveHttpHeader('Content-Type', 'application/json');
            $I->sendPost('api/resources', [
                'name' => 'resource1',
                'description' => 'description',
                'categories' => [],
                'isPrivate' => false,
                'openness' => 4,
                'images' => [],
            ]);
            if ($i < 11) {
                $I->seeResponseCodeIs(Http::OK);
            } else {
                $I->seeResponseCodeIs(Http::FORBIDDEN);
            }
        }
    }

    public function canDeleteOwnResource(ApiTester $I): void
    {
        $user = $this->setupUser($I);
        $categoryIds = $this->addResourceCategories($I);
        $resourceId = $I->addResource($user['id'], 'resource1', null, $categoryIds, false, 3);
        $I->sendDelete('api/resources/' . $resourceId);
        $I->seeResponseCodeIs(Http::OK);
        $I->dontSeeInDatabase('fs_resource', ['id' => $resourceId]);
        $I->dontSeeInDatabase('fs_resource_has_category', ['resource_id' => $resourceId]);
    }

    public function canNotDeleteOthersResource(ApiTester $I): void
    {
        $user = $this->setupUser($I);
        $otherUser = $I->createFoodsaver();
        $resourceId = $I->addResource($otherUser['id'], 'resource', null, [], false, 3);
        $I->sendDelete('api/resources/' . $resourceId);
        $I->seeResponseCodeIs(Http::FORBIDDEN);
        $I->seeInDatabase('fs_resource', ['id' => $resourceId]);
    }

    public function canEditOwnResource(ApiTester $I): void
    {
        $user = $this->setupUser($I);
        $categoryIds = $this->addResourceCategories($I);
        $resourceId = $I->addResource($user['id'], 'resource1', 'description', [$categoryIds[0], $categoryIds[1]], false, 4);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/resources/' . $resourceId, [
            'name' => 'updated name',
            'description' => 'updated description',
            'categories' => [$categoryIds[0], $categoryIds[2]],
            'isPrivate' => true,
            'openness' => 5,
            'images' => [],
        ]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseContainsJson([
            'name' => 'updated name',
            'description' => 'updated description',
            'categories' => [$categoryIds[0], $categoryIds[2]],
            'isPrivate' => true,
            'openness' => 5,
        ]);
        $I->seeInDatabase('fs_resource', [
            'id' => $resourceId,
            'foodsaver_id' => $user['id'],
            'name' => 'updated name',
            'description' => 'updated description',
            'is_private' => true,
            'openness' => 5,
        ]);
        $I->seeInDatabase('fs_resource_has_category', ['resource_id' => $resourceId, 'category_id' => $categoryIds[0]]);
        $I->seeInDatabase('fs_resource_has_category', ['resource_id' => $resourceId, 'category_id' => $categoryIds[2]]);
        $I->dontSeeInDatabase('fs_resource_has_category', ['resource_id' => $resourceId, 'category_id' => $categoryIds[1]]);
    }

    public function canNotEditOthersResource(ApiTester $I): void
    {
        $this->setupUser($I);
        $otherUser = $I->createFoodsaver();
        $resourceId = $I->addResource($otherUser['id'], 'resource', null, [], false, 3);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/resources/' . $resourceId, [
            'name' => 'updated name',
            'description' => 'updated description',
            'categories' => [],
            'isPrivate' => true,
            'openness' => 5,
            'images' => [],
        ]);
        $I->seeResponseCodeIs(Http::FORBIDDEN);
        $I->seeInDatabase('fs_resource', ['id' => $resourceId, 'foodsaver_id' => $otherUser['id']]);
    }

    public function canFavoriteAndUnfavoriteResources(ApiTester $I): void
    {
        [$user, $region] = $this->setupUserAndRegion($I);
        $resourceId = $I->addResource($user['id'], 'resource1', 'description', [], false, 4);
        $I->sendPost('api/resources/' . $resourceId . '/favorite');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeInDatabase('fs_foodsaver_has_favorite_resource', ['resource_id' => $resourceId, 'foodsaver_id' => $user['id']]);

        $I->sendGet('api/region/' . $region['id'] . '/resources');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseContainsJson([[
            'id' => $resourceId,
            'isFavorite' => true,
        ]]);

        $I->sendDelete('api/resources/' . $resourceId . '/favorite');
        $I->seeResponseCodeIs(Http::OK);
        $I->dontSeeInDatabase('fs_foodsaver_has_favorite_resource', ['resource_id' => $resourceId, 'foodsaver_id' => $user['id']]);
        $I->sendGet('api/region/' . $region['id'] . '/resources');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseContainsJson([[
            'id' => $resourceId,
            'isFavorite' => false,
        ]]);
    }

    private function setupUserAndRegion(ApiTester $I): array
    {
        $user = $this->setupUser($I);
        $region = $I->createRegion(null, ['type' => UnitType::CITY], false);
        $I->addRegionMember($region['id'], $user['id']);

        return [$user, $region];
    }

    private function setupUser(ApiTester $I): array
    {
        $user = $I->createFoodsaver();
        $I->login($user['email']);

        return $user;
    }

    private function addResourceCategories(ApiTester $I): array
    {
        $ids = [];
        foreach (['A', 'B', 'C'] as $categoryName) {
            $ids[] = $I->addResourceCategory($categoryName);
        }

        return $ids;
    }
}
