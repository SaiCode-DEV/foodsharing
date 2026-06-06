<?php

namespace Foodsharing\Modules\Categories;

class GroupCategoriesGateway extends AbstractCategoriesGateway
{
    protected function getCategoryTable(): string
    {
        return 'fs_group_category';
    }

    protected function getUsageTable(): string
    {
        return 'fs_bezirk';
    }

    protected function getUsageColumn(): string
    {
        return 'category_id';
    }

    protected function getEntityIdColumn(): string
    {
        return 'group_id';
    }

    protected function getEntityTypeColumn(): ?string
    {
        return null;
    }

    protected function allowsMultipleCategories(): bool
    {
        return false;
    }
}
