<?php

namespace Foodsharing\Modules\Categories;

class StoreCategoriesGateway extends AbstractCategoriesGateway
{
    protected function getCategoryTable(): string
    {
        return 'fs_betrieb_kategorie';
    }

    protected function getUsageTable(): string
    {
        return 'fs_betrieb';
    }

    protected function getUsageColumn(): string
    {
        return 'betrieb_kategorie_id';
    }

    protected function getEntityIdColumn(): string
    {
        return 'id';
    }

    protected function allowsMultipleCategories(): bool
    {
        return false;
    }
}
