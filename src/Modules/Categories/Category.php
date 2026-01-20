<?php

namespace Foodsharing\Modules\Categories;

/**
 * Contains the properties of a category to be displayed in the category admin tool.
 */
class Category
{
    public int $id;
    public string $name;
    public int $usageCount;
    public ?int $subType;

    public static function create(int $id, string $name, int $usageCount, ?int $subType): self
    {
        $category = new self();
        $category->id = $id;
        $category->name = $name;
        $category->usageCount = $usageCount;
        $category->subType = $subType;

        return $category;
    }
}
