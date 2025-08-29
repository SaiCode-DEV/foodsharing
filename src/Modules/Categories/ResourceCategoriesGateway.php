<?php

namespace Foodsharing\Modules\Categories;

class ResourceCategoriesGateway extends AbstractCategoriesGateway
{
    protected function getCategoryTable(): string
    {
        return 'fs_resource_category';
    }

    protected function getUsageTable(): string
    {
        return 'fs_resource_has_category';
    }

    protected function getUsageColumn(): string
    {
        return 'category_id';
    }

    protected function getEntityIdColumn(): string
    {
        return 'resource_id';
    }

    protected function allowsMultipleCategories(): bool
    {
        return true;
    }
}
