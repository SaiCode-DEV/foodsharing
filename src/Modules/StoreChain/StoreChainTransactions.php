<?php

namespace Foodsharing\Modules\StoreChain;

use Exception;
use Foodsharing\Modules\Achievement\AchievementGateway;
use Foodsharing\Modules\Core\DBConstants\Achievement\AchievementIDs;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\Pagination;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Region\ForumGateway;
use Foodsharing\Modules\StoreChain\DTO\StoreChainData;
use Foodsharing\Modules\StoreChain\DTO\StoreChainForChainList;

class StoreChainTransactions
{
    public function __construct(
        private readonly StoreChainGateway $storeChainGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly ForumGateway $forumGateway,
        private readonly AchievementGateway $achievementGateway,
    ) {
    }

    /**
     * @return StoreChainForChainList[]
     *
     * @throws Exception
     */
    public function getStoreChains(?int $id = null, Pagination $pagination = new Pagination()): array
    {
        $results = $this->storeChainGateway->getStoreChains($id, $pagination);

        return $results;
    }

    /**
     * @throws Exception
     */
    public function addStoreChain(StoreChainData $storeChainData): int
    {
        $this->throwExceptionIfKeyAccountManagerIsInvalid($storeChainData->kams);
        $this->throwExceptionIfForumInvalid($storeChainData->forumThread);

        return $this->storeChainGateway->addStoreChain($storeChainData);
    }

    public function updateStoreChain(int $chainId, StoreChainData $storeChainData, bool $updateKams): void
    {
        $this->throwExceptionIfForumInvalid($storeChainData->forumThread);
        $this->storeChainGateway->updateStoreChain($chainId, $storeChainData);

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
