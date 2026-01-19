<?php

namespace Foodsharing\Modules\Configuration;

use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;
use Foodsharing\Modules\Core\DBConstants\Configuration\ConfigurationCategory;
use Foodsharing\Modules\Core\DBConstants\Configuration\ConfigurationKey;
use UnexpectedValueException;

/**
 * The configuration module allows storing general key-value pairs. Entries can have an optional category for filtering.
 */
class ConfigurationGateway extends BaseGateway
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    /**
     * Returns the value for a specific key.
     *
     * @throws DatabaseNoValueFoundException if the key does not exist
     * @see ConfigurationKey
     */
    public function getEntry(string $key): string
    {
        return $this->db->fetchValueByCriteria('configuration', 'value', ['key' => $key]);
    }

    /**
     * Returns all entries of a category. The result is an empty array if the category does not exist. Setting the
     * category to null returns all entries that do not have a category.
     *
     * @return array<string,string> key-value pairs
     * @see ConfigurationCategory
     */
    public function getEntries(?int $category): array
    {
        $entries = $this->db->fetchAllByCriteria('configuration', ['key', 'value'], ['category' => $category]);

        return array_column($entries, 'value', 'key');
    }

    /**
     * Writes a key-value pair. If the key already exist, the existing value will be overwritten. The category can be
     * null which means that the key does not have a category. Using -1 for the category means that the existing
     * category will not be overwritten. If the key does not yet exist, the category -1 is the same as null.
     */
    public function addOrUpdateEntry(string $key, string $value, ?int $category = -1): void
    {
        $entry = [
            'key' => $key,
            'value' => $value,
        ];
        if ($category !== -1) {
            $entry['category'] = $category;
        }
        $this->db->insertOrUpdate('configuration', $entry);
    }

    /**
     * Writes several key-value pairs. Any key that does not yet exist will be inserted with its value. For any key that
     * already exist, the value will be overwritten. The behavior of null and -1 for the category is the same as in
     * addOrUpdateEntry.
     */
    public function addOrUpdateEntries(array $entries, ?int $category = -1): void
    {
        $rows = [];
        foreach ($entries as $key => $value) {
            $row = [
                'key' => $key,
                'value' => $value,
            ];
            if ($category !== -1) {
                $row['category'] = $category;
            }
            $rows[] = $row;
        }

        $this->db->insertOrUpdateMultiple('configuration', $rows);
    }

    /**
     * Returns the key's category. The result can be null if the key does not have a category.
     *
     * @throws DatabaseNoValueFoundException if the key does not exist
     * @see ConfigurationCategory
     */
    public function getCategory(string $key): ?int
    {
        $category = $this->db->fetchValueByCriteria('configuration', 'category', ['key' => $key]);

        return is_null($category) ? null : (int)$category;
    }

    /**
     * Updates the category of one of more existing keys. If the new category is null, it will be stored as null in the
     * database, i.e. the keys will not have a category anymore.
     *
     * @throws DatabaseNoValueFoundException if at least one the keys does not exist
     * @throws UnexpectedValueException if the category is not a positive integer or null
     * @see ConfigurationCategory
     */
    public function setCategory(?int $category = null, string ...$keys): void
    {
        if ($this->db->count('configuration', ['key' => $keys]) < count($keys)) {
            throw new DatabaseNoValueFoundException();
        }
        if (!is_null($category) && $category < 0) {
            throw new UnexpectedValueException('category must be a positive integer or null');
        }
        $this->db->update('configuration', ['category' => $category], ['key' => $keys]);
    }
}
