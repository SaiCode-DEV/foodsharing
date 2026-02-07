<?php

namespace Foodsharing\Modules\StoreChain;

use Exception;
use Foodsharing\Modules\Achievement\AchievementGateway;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\DBConstants\Achievement\AchievementIDs;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Region\ForumGateway;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Modules\Store\StoreTransactions;
use Foodsharing\Modules\StoreChain\DTO\StoreChainData;

class StoreChainTransactions
{
    public function __construct(
        private readonly StoreChainGateway $storeChainGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly ForumGateway $forumGateway,
        private readonly AchievementGateway $achievementGateway,
        private readonly StoreTransactions $storeTransactions,
        private readonly BellGateway $bellGateway,
        private readonly StoreGateway $storeGateway,
    ) {
    }

    /**
     * @throws Exception
     */
    public function addStoreChain(StoreChainData $storeChainData): int
    {
        $this->throwExceptionIfKeyAccountManagerIsInvalid($storeChainData->kams);
        $this->throwExceptionIfForumInvalid($storeChainData->forumThread);
        $this->storeTransactions->invalidateCachedStoreMetadata();

        return $this->storeChainGateway->addStoreChain($storeChainData);
    }

    public function updateStoreChain(int $chainId, StoreChainData $storeChainData, bool $updateKams): void
    {
        $this->throwExceptionIfForumInvalid($storeChainData->forumThread);
        $currentCommonStoreInformation = $this->storeChainGateway->getChainInformationForStore($chainId)['common_store_information'];
        if ($currentCommonStoreInformation !== $storeChainData->commonStoreInformation) {
            $stores = $this->storeGateway->findAllStoresOfStoreChain($chainId);
            // for each store, get the store managers and send them a bell about the change
            foreach ($stores as $store) {
                $storeManagers = $this->storeGateway->getStoreManagers($store->id);
                if (empty($storeManagers)) {
                    continue;
                }

                $bell = Bell::create(
                    'chain_info_updated_title',
                    'chain_info_updated',
                    'fas fa-chain',
                    ['href' => '/store/' . $store->id],
                    ['chain' => $storeChainData->name, 'store' => $store->name],
                    BellType::createIdentifier(BellType::CHAIN_INFO_UPDATED, $chainId, $store->id),
                );
                $this->bellGateway->delBellsByIdentifier($bell->identifier);
                $this->bellGateway->addBellForUsers($storeManagers, $bell);
            }
        }
        $this->storeChainGateway->updateStoreChain($chainId, $storeChainData);
        $this->storeTransactions->invalidateCachedStoreMetadata();

        if ($updateKams && !is_null($storeChainData->kams)) {
            $this->throwExceptionIfKeyAccountManagerIsInvalid($storeChainData->kams);
            $this->storeChainGateway->updateAllKeyAccountManagers($chainId, $storeChainData->kams);
        }
    }

    /**
     * @param int[] $kamIds
     */
    private function throwExceptionIfKeyAccountManagerIsInvalid(array $kamIds): void
    {
        if (!$this->foodsaverGateway->foodsaversExist($kamIds)) {
            throw new StoreChainTransactionException(StoreChainTransactionException::KEY_ACCOUNT_MANAGER_ID_NOT_EXISTS);
        }

        foreach ($kamIds as $id) {
            if (!$this->achievementGateway->hasAchievement($id, AchievementIDs::KAM_CERTIFICATE)) {
                throw new StoreChainTransactionException(StoreChainTransactionException::KEY_ACCOUNT_MANAGER_MISSING_ACHIEVEMENT);
            }
        }
    }

    private function throwExceptionIfForumInvalid(int $threadId)
    {
        $forumResult = $this->forumGateway->getForumsForThread($threadId);
        if (empty($forumResult)) {
            throw new StoreChainTransactionException(StoreChainTransactionException::THREAD_ID_NOT_EXISTS);
        }

        if (!RegionIDs::isChainsGroup($forumResult[0]['forumId'])) {
            throw new StoreChainTransactionException(StoreChainTransactionException::WRONG_FORUM);
        }
    }
}
