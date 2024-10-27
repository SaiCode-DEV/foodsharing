<?php

namespace Foodsharing\Modules\StoreChain;

use Exception;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\Pagination;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\Region\ForumGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\StoreChain\DTO\PatchStoreChain;
use Foodsharing\Modules\StoreChain\DTO\StoreChain;
use Foodsharing\Modules\StoreChain\DTO\StoreChainForChainList;

class StoreChainTransactions
{
    public function __construct(
        private readonly StoreChainGateway $storeChainGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly ForumGateway $forumGateway,
        private readonly RegionGateway $regionGateway
    ) {
    }

    /**
     * @return StoreChainForChainList[]
     *
     * @throws Exception
     */
    public function getStoreChains(?int $id = null, bool $details = true, Pagination $pagination = new Pagination()): array
    {
        $results = $this->storeChainGateway->getStoreChains($id, $pagination);
        if (!$details) {
            foreach ($results as &$item) {
                $item->storeCount = null;
                $item->chain->estimatedStoreCount = null;
                $item->chain->forumThread = null;
                $item->chain->notes = null;
                $item->chain->regionId = null;
            }
        }

        return $results;
    }

    /**
     * @throws Exception
     */
    public function addStoreChain(StoreChain $storeData): int
    {
        $this->throwExceptionIfKeyAccountManagerIsInvalid($storeData->kams);
        $this->throwExceptionIfForumInvalid($storeData->forumThread);

        return $this->storeChainGateway->addStoreChain($storeData);
    }

    public function updateStoreChain(int $chainId, PatchStoreChain $storeModel, bool $updateKams): bool
    {
        if (!$chainId) {
            throw new StoreChainTransactionException(StoreChainTransactionException::INVALID_STORECHAIN_ID);
        }

        $changed = false;
        $params = $this->storeChainGateway->getStoreChains($chainId)[0]->chain;
        $params->id = $chainId;
        if (!is_null($storeModel->name)) {
            if (empty(trim(strip_tags($storeModel->name)))) {
                throw new StoreChainTransactionException(StoreChainTransactionException::EMPTY_NAME);
            }
            $params->name = $storeModel->name;
            $changed = true;
        }

        if (!is_null($storeModel->status)) {
            $status = StoreChainStatus::tryFrom($storeModel->status);
            if (!$status instanceof StoreChainStatus) {
                throw new StoreChainTransactionException(StoreChainTransactionException::INVALID_STATUS);
            }
            $params->status = $status;
            $changed = true;
        }
        if (!is_null($storeModel->headquartersZip)) {
            if (empty(trim(strip_tags($storeModel->headquartersZip)))) {
                throw new StoreChainTransactionException(StoreChainTransactionException::EMPTY_ZIP);
            }
            $params->headquartersZip = $storeModel->headquartersZip;
            $changed = true;
        } else {
            if (empty(trim(strip_tags($params->headquartersZip)))) {
                throw new StoreChainTransactionException(StoreChainTransactionException::EMPTY_ZIP);
            }
        }
        if (!is_null($storeModel->headquartersCity)) {
            if (empty(trim(strip_tags($storeModel->headquartersCity)))) {
                throw new StoreChainTransactionException(StoreChainTransactionException::EMPTY_CITY);
            }
            $params->headquartersCity = $storeModel->headquartersCity;
            $changed = true;
        } else {
            if (empty(trim(strip_tags($params->headquartersCity)))) {
                throw new StoreChainTransactionException(StoreChainTransactionException::EMPTY_CITY);
            }
        }
        if (!is_null($storeModel->headquartersCountry)) {
            if (empty(trim(strip_tags($storeModel->headquartersCountry)))) {
                throw new StoreChainTransactionException(StoreChainTransactionException::EMPTY_COUNTRY);
            }
            $params->headquartersCountry = $storeModel->headquartersCountry;
            $changed = true;
        } else {
            if (empty(trim(strip_tags($params->headquartersCountry)))) {
                throw new StoreChainTransactionException(StoreChainTransactionException::EMPTY_COUNTRY);
            }
        }
        if (!empty($storeModel->allowPress)) {
            $params->allowPress = $storeModel->allowPress;
            $changed = true;
        }
        if (!empty($storeModel->forumThread)) {
            $this->throwExceptionIfForumInvalid($storeModel->forumThread);
            $params->forumThread = $storeModel->forumThread;
            $changed = true;
        } else {
            $this->throwExceptionIfForumInvalid($params->forumThread);
        }
        if (!is_null($storeModel->notes)) {
            $params->notes = $storeModel->notes;
            $changed = true;
        }
        if (!is_null($storeModel->commonStoreInformation)) {
            $params->commonStoreInformation = $storeModel->commonStoreInformation;
            $changed = true;
        }
        if (!is_null($storeModel->kams)) {
            $params->kams = array_map(fn ($kam) => new Profile(['id' => $kam]), $storeModel->kams);
            $this->throwExceptionIfKeyAccountManagerIsInvalid($params->kams);
            $changed = true;
        } else {
            $this->throwExceptionIfKeyAccountManagerIsInvalid($params->kams);
        }

        if (!empty($storeModel->estimatedStoreCount)) {
            $params->estimatedStoreCount = $storeModel->estimatedStoreCount;
            $changed = true;
        }

        if ($changed) {
            $this->storeChainGateway->updateStoreChain($params, $updateKams);

            return true;
        } else {
            return false;
        }
    }

    private function throwExceptionIfKeyAccountManagerIsInvalid($kams)
    {
        $ids = array_map(fn ($item) => $item->id, $kams);
        if (!$this->foodsaverGateway->foodsaversExist($ids)) {
            throw new StoreChainTransactionException(StoreChainTransactionException::KEY_ACCOUNT_MANAGER_ID_NOT_EXISTS);
        }

        foreach ($ids as $id) {
            if (!$this->regionGateway->hasMember($id, RegionIDs::STORE_CHAIN_GROUP)) {
                throw new StoreChainTransactionException(StoreChainTransactionException::KEY_ACCOUNT_MANAGER_ID_NOT_IN_GROUP);
            }
        }
    }

    private function throwExceptionIfForumInvalid(int $threadId)
    {
        $forumResult = $this->forumGateway->getForumsForThread($threadId);
        if (empty($forumResult)) {
            throw new StoreChainTransactionException(StoreChainTransactionException::THREAD_ID_NOT_EXISTS);
        }

        if ($forumResult[0]['forumId'] != RegionIDs::STORE_CHAIN_GROUP) {
            throw new StoreChainTransactionException(StoreChainTransactionException::WRONG_FORUM);
        }
    }
}
