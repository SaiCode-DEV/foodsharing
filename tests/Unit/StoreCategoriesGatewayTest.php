<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Modules\Categories\StoreCategoriesGateway;
use Foodsharing\Modules\Core\DBConstants\CategoryType;
use Foodsharing\Modules\Store\DTO\CategoryWithType;
use Tests\Support\UnitTester;

class StoreCategoriesGatewayTest extends Unit
{
    private const array EXISTING_CATEGORIES = [
        [
            'id' => 1,
            'type' => 0,
        ],
        [
            'id' => 2,
            'type' => 1,
        ],
        [
            'id' => 3,
            'type' => 2,
        ]
    ];
    protected UnitTester $tester;
    private StoreCategoriesGateway $gateway;

    final public function _before(): void
    {
        $this->gateway = $this->tester->get(StoreCategoriesGateway::class);

        $this->tester->clearTable('fs_betrieb_kategorie');
        foreach (self::EXISTING_CATEGORIES as $entry) {
            $this->tester->haveInDatabase('fs_betrieb_kategorie', ['id' => $entry['id'], 'name' => 'Category ' . $entry['id'], 'type' => $entry['type']]);
        }
    }

    public function testExistCategory(): void
    {
        $this->assertTrue($this->gateway->categoryExists(self::EXISTING_CATEGORIES[0]['id']));
        $this->assertFalse($this->gateway->categoryExists(9999));
    }

    public function testGetCategories(): void
    {
        $categories = $this->gateway->getCategoriesWithUsageCounts();
        $this->assertIsArray($categories);
        $this->assertEquals(sizeof(self::EXISTING_CATEGORIES), sizeof($categories));
        foreach (self::EXISTING_CATEGORIES as $entry) {
            $this->assertNotEmpty(array_filter($categories,
                fn ($category) => $category->id == $entry['id'] && $category->name == 'Category ' . $entry['id']
                    && $category->subType == $entry['type'])
            );
        }
    }

    public function testGetCategoriesWithUsageCounts(): void
    {
        $categories = $this->gateway->getCategoriesWithUsageCounts(CategoryType::STORE);
        $this->assertIsArray($categories);
        $this->assertEquals(sizeof(self::EXISTING_CATEGORIES), sizeof($categories));
        foreach (self::EXISTING_CATEGORIES as $entry) {
            $this->assertNotEmpty(array_filter($categories,
                fn ($category) => $category->id == $entry['id'] && $category->name == 'Category ' . $entry['id']
                    && $category->subType == $entry['type'])
            );
        }
    }

    public function testAddCategories(): void
    {
        // adding a new category should create a new id
        $newCategory = new CategoryWithType(self::EXISTING_CATEGORIES[0]['id'], 'Test', self::EXISTING_CATEGORIES[0]['type']);
        $newId = $this->gateway->addCategory($newCategory);
        $this->assertNotEquals($newId, $newCategory->id);
        $this->tester->seeInDatabase('fs_betrieb_kategorie', ['id' => $newId, 'name' => $newCategory->name, 'type' => 'pickup']);
    }

    public function testUpdateCategories(): void
    {
        // updating an existing category
        $choice = random_int(0, sizeof(self::EXISTING_CATEGORIES) - 1);
        $id = self::EXISTING_CATEGORIES[$choice]['id'];
        $type = self::EXISTING_CATEGORIES[$choice]['type'];
        $this->tester->seeInDatabase('fs_betrieb_kategorie', ['id' => $id, 'name' => 'Category ' . $id, 'type' => $type]);

        $updatedCategory = new CategoryWithType($id, 'Test', 1);
        $this->gateway->updateCategory($updatedCategory);
        $this->tester->dontSeeInDatabase('fs_betrieb_kategorie', ['id' => $id, 'name' => 'Category ' . $id]);
        $this->tester->seeInDatabase('fs_betrieb_kategorie', ['id' => $id, 'name' => $updatedCategory->name, 'type' => 1]);

        // updating a non-existent category should not do anything
        $id = 9999;
        $this->tester->dontSeeInDatabase('fs_betrieb_kategorie', ['id' => $id]);
        $updatedCategory = new CategoryWithType($id, 'Test');
        $this->gateway->updateCategory($updatedCategory);
        $this->tester->dontSeeInDatabase('fs_betrieb_kategorie', ['id' => $id]);
    }

    public function testDeleteCategories(): void
    {
        // deleting an existing category
        $id = self::EXISTING_CATEGORIES[random_int(0, sizeof(self::EXISTING_CATEGORIES) - 1)]['id'];
        $this->tester->seeInDatabase('fs_betrieb_kategorie', ['id' => $id]);

        $this->gateway->deleteCategory($id);
        $this->tester->dontSeeInDatabase('fs_betrieb_kategorie', ['id' => $id]);
        $this->assertFalse($this->gateway->categoryExists($id));
        $this->assertNotContains(new CategoryWithType($id, 'Category ' . $id), $this->gateway->getCategoriesWithUsageCounts(CategoryType::STORE));

        // deleting a non-existent category should not do anything
        $id = 9999;
        $this->tester->dontSeeInDatabase('fs_betrieb_kategorie', ['id' => $id]);
        $this->gateway->deleteCategory($id);
        $this->tester->dontSeeInDatabase('fs_betrieb_kategorie', ['id' => $id]);
    }
}
