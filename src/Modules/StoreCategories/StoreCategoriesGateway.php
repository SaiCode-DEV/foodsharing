<?php

namespace Foodsharing\Modules\StoreCategories;

use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;
use Foodsharing\Modules\Store\DTO\CommonLabel;

class StoreCategoriesGateway extends BaseGateway
{
    public function __construct(
        Database $db,
    ) {
        parent::__construct($db);
    }

    /**
     * @return bool if the category with that id does exist
     */
    public function existStoreCategory(int $id): bool
    {
        return $this->db->exists('fs_betrieb_kategorie', ['id' => $id]);
    }

    /**
     * @return ?CommonLabel the store category with the specified id or null if the category does not exist
     */
    public function getStoreCategory(int $id): ?CommonLabel
    {
        try {
            $name = $this->db->fetchValueByCriteria('fs_betrieb_kategorie', 'name', ['id' => $id]);

            return empty($name) ? null : new CommonLabel($id, $name);
        } catch (DatabaseNoValueFoundException $e) {
            return null;
        }
    }

    /**
     * @return CommonLabel[] all existing store categories
     */
    public function getStoreCategories(): array
    {
        $entries = $this->db->fetchAllByCriteria('fs_betrieb_kategorie', ['id', 'name']);

        return array_map(fn ($entry) => new CommonLabel($entry['id'], $entry['name']), $entries);
    }

    /**
     * Adds a new store category and returns its id.
     */
    public function addStoreCategory(CommonLabel $category): int
    {
        return $this->db->insert('fs_betrieb_kategorie', ['name' => $category->name]);
    }

    /**
     * Updates all properties of a store category.
     */
    public function updateStoreCategory(CommonLabel $category): void
    {
        $this->db->update('fs_betrieb_kategorie', ['name' => $category->name], ['id' => $category->id]);
    }

    /**
     * Deletes the store category with the specified id, if it exists.
     */
    public function deleteStoreCategory(int $id): void
    {
        $this->db->delete('fs_betrieb_kategorie', ['id' => $id]);
    }
}
