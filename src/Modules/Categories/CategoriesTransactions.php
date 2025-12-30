<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Categories;

use Foodsharing\Modules\Core\DBConstants\CategoryType;
use Foodsharing\Modules\Store\StoreTransactions;

class CategoriesTransactions
{
    public function __construct(
        private readonly StoreTransactions $storeTransactions,
        private readonly StoreCategoriesGateway $storeCategoriesGateway,
        private readonly ResourceCategoriesGateway $resourceCategoriesGateway,
    ) {
    }

    public function handleTypeSpecificSideEffects(CategoryType $type): void
    {
        if ($type === CategoryType::STORE) {
            $this->storeTransactions->invalidateCachedStoreMetadata();
        }
    }

    public function getCategoriesGateway(CategoryType $type): AbstractCategoriesGateway
    {
        return match ($type) {
            CategoryType::STORE => $this->storeCategoriesGateway,
            CategoryType::RESOURCE => $this->resourceCategoriesGateway,
        };
    }
}
