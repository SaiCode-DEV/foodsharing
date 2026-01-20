<?php

namespace Foodsharing\Modules\Categories;

use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Store\DTO\CategoryWithType;
use Foodsharing\Modules\Store\DTO\CommonLabel;

abstract class AbstractCategoriesGateway extends BaseGateway
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    /**
     * @return string the name of the table that stores category definitions
     */
    abstract protected function getCategoryTable(): string;

    /**
     * @return string the name of the table that references categories
     */
    abstract protected function getUsageTable(): string;

    /**
     * @return string the name of the column in the usage table that points to a category
     */
    abstract protected function getUsageColumn(): string;

    /**
     * @return string the name of the column in the usage table that identifies the entity
     */
    abstract protected function getEntityIdColumn(): string;

    /**
     * @return ?string the name of the column in the category table that identifies the entity type,
     *                 or null if there is no such column
     */
    abstract protected function getEntityTypeColumn(): ?string;

    /**
     * Whether an entity can be linked to multiple categories (m:n relation).
     *
     * - Return false if each entity has at most one category (1:n).
     * - Return true if entities can have multiple categories, and therefore
     *   duplicate entries may need to be cleaned up during a merge.
     *
     * @return bool true if the entity can have multiple categories, false otherwise
     */
    abstract protected function allowsMultipleCategories(): bool;

    /**
     * @return Category[]
     */
    public function getCategoriesWithUsageCounts(): array
    {
        $extra = $this->getEntityTypeColumn() ? ", c.{$this->getEntityTypeColumn()}" : '';
        $categories = $this->db->fetchAll("SELECT
                c.id, c.name, COUNT(u.{$this->getUsageColumn()}) AS count{$extra}
            FROM {$this->getCategoryTable()} c
            LEFT OUTER JOIN {$this->getUsageTable()} u ON c.id = u.{$this->getUsageColumn()}
            GROUP BY c.id");

        return array_map(fn ($row) => Category::create(
            $row['id'],
            $row['name'],
            $row['count'],
            $this->getEntityTypeColumn() ? $row[$this->getEntityTypeColumn()] : null
        ), $categories);
    }

    /**
     * @return CommonLabel[]|CategoryWithType[]
     */
    public function getCategories(): array
    {
        $extra = $this->getEntityTypeColumn() ? ", {$this->getEntityTypeColumn()}" : '';
        $categories = $this->db->fetchAll("SELECT id, name{$extra} FROM {$this->getCategoryTable()} ORDER BY name");

        if ($this->getEntityTypeColumn() !== null) {
            // convert DB integer type to string name for API consumers
            foreach ($categories as &$row) {
                if (isset($row[$this->getEntityTypeColumn()])) {
                    $row['type'] = $row[$this->getEntityTypeColumn()];
                }
            }
            unset($row);

            return array_map(fn ($row) => CategoryWithType::createFromArray($row), $categories);
        }

        return array_map(fn ($row) => CommonLabel::createFromArray($row), $categories);
    }

    public function addCategory(CommonLabel $category): int
    {
        if ($this->getEntityTypeColumn() !== null) {
            $subType = $category instanceof CategoryWithType ? $category->subType : null;

            return $this->db->insert($this->getCategoryTable(), ['name' => $category->name, $this->getEntityTypeColumn() => $subType]);
        }

        return $this->db->insert($this->getCategoryTable(), ['name' => $category->name]);
    }

    public function updateCategory(CommonLabel $category): void
    {
        if ($this->getEntityTypeColumn() !== null) {
            $subType = $category instanceof CategoryWithType ? $category->subType : null;
            $this->db->update($this->getCategoryTable(), ['name' => $category->name, $this->getEntityTypeColumn() => $subType], ['id' => $category->id]);

            return;
        }
        $this->db->update($this->getCategoryTable(), ['name' => $category->name], ['id' => $category->id]);
    }

    public function deleteCategory(int $id): void
    {
        $this->db->delete($this->getCategoryTable(), ['id' => $id]);
    }

    public function categoryExists(int $id): bool
    {
        return $this->db->exists($this->getCategoryTable(), ['id' => $id]);
    }

    /**
     * Merge two categories into one.
     *
     * All usages of the source category are reassigned to the target category,
     * and the source category is deleted. If the relation allows multiple
     * categories per entity (m:n), any duplicate assignments that would result
     * from the merge (i.e. the same entity already linked to both categories)
     * are removed first.
     *
     * @param int $targetId ID of the category that should remain
     * @param int $sourceId ID of the category that will be merged into $targetId
     *
     * @return int The number of duplicate usage rows that were deleted
     *             before reassigning. Will be 0 if no duplicates existed or
     *             if the relation is 1:n (only one category per entity).
     */
    public function mergeCategories(int $targetId, int $sourceId): int
    {
        $duplicates = 0;

        if ($this->allowsMultipleCategories()) {
            $duplicates = $this->db->execute("DELETE source
                FROM {$this->getUsageTable()} source
                JOIN {$this->getUsageTable()} target
                  ON source.{$this->getEntityIdColumn()} = target.{$this->getEntityIdColumn()}
                WHERE source.{$this->getUsageColumn()} = ?
                  AND target.{$this->getUsageColumn()} = ?
            ", [$sourceId, $targetId])->rowCount();
        }

        $this->db->update(
            $this->getUsageTable(),
            [$this->getUsageColumn() => $targetId],
            [$this->getUsageColumn() => $sourceId]
        );

        $this->deleteCategory($sourceId);

        return $duplicates;
    }
}
