<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Modules\Store\DTO\CommonLabel;
use Foodsharing\Modules\StoreCategories\StoreCategoriesGateway;
use Tests\Support\UnitTester;

class StoreCategoriesGatewayTest extends Unit
{
    private const EXISTING_CATEGORIES = [1, 2, 3];
    protected UnitTester $tester;
    private StoreCategoriesGateway $gateway;

    private readonly array $store;
    private readonly array $foodsaver;
    private readonly array $region;

    final public function _before(): void
    {
        $this->gateway = $this->tester->get(StoreCategoriesGateway::class);

        $this->tester->clearTable('fs_betrieb_kategorie');
        foreach (self::EXISTING_CATEGORIES as $id) {
            $this->tester->haveInDatabase('fs_betrieb_kategorie', ['id' => $id, 'name' => 'Category ' . $id]);
        }
    }

    public function testExistCategory(): void
    {
        $this->assertTrue($this->gateway->existStoreCategory(self::EXISTING_CATEGORIES[0]));
        $this->assertFalse($this->gateway->existStoreCategory(9999));
    }

    public function testGetCategory(): void
    {
        $id = self::EXISTING_CATEGORIES[0];
        $this->assertEquals(new CommonLabel($id, 'Category ' . $id), $this->gateway->getStoreCategory($id));
        $this->assertNull($this->gateway->getStoreCategory(9999));
    }

    public function testGetCategories(): void
    {
        $categories = $this->gateway->getStoreCategories();
        $this->assertIsArray($categories);
        $this->assertEquals(sizeof(self::EXISTING_CATEGORIES), sizeof($categories));
        foreach (self::EXISTING_CATEGORIES as $id) {
            $this->assertNotEmpty(array_filter($categories,
                fn ($category) => $category->id == $id && $category->name == 'Category ' . $id)
            );
        }
    }

    public function testGetCategoriesWithNumbers(): void
    {
        $categories = $this->gateway->getStoreCategoriesWithStoreNumbers();
        $this->assertIsArray($categories);
        $this->assertEquals(sizeof(self::EXISTING_CATEGORIES), sizeof($categories));
        foreach (self::EXISTING_CATEGORIES as $id) {
            $this->assertNotEmpty(array_filter($categories,
                fn ($category) => $category->id == $id && $category->name == 'Category ' . $id)
            );
        }
    }

    public function testAddCategories(): void
    {
        // adding a new category should create a new id
        $newCategory = new CommonLabel(self::EXISTING_CATEGORIES[0], 'Test');
        $newId = $this->gateway->addStoreCategory($newCategory);
        $this->assertNotEquals($newId, $newCategory->id);
        $this->tester->seeInDatabase('fs_betrieb_kategorie', ['id' => $newId, 'name' => $newCategory->name]);
    }

    public function testUpdateCategories(): void
    {
        // updating an existing category
        $id = self::EXISTING_CATEGORIES[random_int(0, sizeof(self::EXISTING_CATEGORIES) - 1)];
        $this->tester->seeInDatabase('fs_betrieb_kategorie', ['id' => $id, 'name' => 'Category ' . $id]);

        $updatedCategory = new CommonLabel($id, 'Test');
        $this->gateway->updateStoreCategory($updatedCategory);
        $this->tester->dontSeeInDatabase('fs_betrieb_kategorie', ['id' => $id, 'name' => 'Category ' . $id]);
        $this->tester->seeInDatabase('fs_betrieb_kategorie', ['id' => $id, 'name' => $updatedCategory->name]);

        // updating a non-existent category should not do anything
        $id = 9999;
        $this->tester->dontSeeInDatabase('fs_betrieb_kategorie', ['id' => $id]);
        $updatedCategory = new CommonLabel($id, 'Test');
        $this->gateway->updateStoreCategory($updatedCategory);
        $this->tester->dontSeeInDatabase('fs_betrieb_kategorie', ['id' => $id]);
    }

    public function testDeleteCategories(): void
    {
        // deleting an existing category
        $id = self::EXISTING_CATEGORIES[random_int(0, sizeof(self::EXISTING_CATEGORIES) - 1)];
        $this->tester->seeInDatabase('fs_betrieb_kategorie', ['id' => $id]);

        $this->gateway->deleteStoreCategory($id);
        $this->tester->dontSeeInDatabase('fs_betrieb_kategorie', ['id' => $id]);
        $this->assertNull($this->gateway->getStoreCategory($id));
        $this->assertNotContains(new CommonLabel($id, 'Category ' . $id), $this->gateway->getStoreCategories());

        // deleting a non-existent category should not do anything
        $id = 9999;
        $this->tester->dontSeeInDatabase('fs_betrieb_kategorie', ['id' => $id]);
        $this->gateway->deleteStoreCategory($id);
        $this->tester->dontSeeInDatabase('fs_betrieb_kategorie', ['id' => $id]);
    }
}
