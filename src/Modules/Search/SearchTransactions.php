<?php

namespace Foodsharing\Modules\Search;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Search\DTO\MixedSearchResult;
use Foodsharing\Permissions\SearchPermissions;

class SearchTransactions
{
    public function __construct(
        private readonly SearchGateway $searchGateway,
        private readonly Session $session,
        private readonly SearchPermissions $searchPermissions
    ) {
    }

    /**
     * Searches for regions, stores, foodsavers, food share points and working groups.
     *
     * @param string $query the search query
     * @param bool $global whether global search results are desired
     */
    public function search(string $query, bool $global): MixedSearchResult
    {
        // TODO: Search by Email for IT-Support Group and ORGA
        // $this->searchPermissions->maySearchByEmailAddress()

        // TODO: remove timing measurement before release
        $result = new MixedSearchResult();
        $result->timings = [];

        $start = microtime(true);
        $foodsaverId = $this->session->id();
        $maySearchGlobal = $this->searchPermissions->maySearchGlobal();
        $searchGlobal = $global && $maySearchGlobal;
        $searchAllWorkingGroups = $this->searchPermissions->maySearchAllWorkingGroups();
        $includeInactiveStores = $this->session->mayRole(Role::STORE_MANAGER);
        $result->timings['permissions'] = microtime(true) - $start;

        $start = microtime(true);
        $result->regions = $this->searchGateway->searchRegions($query, $foodsaverId);
        $result->timings['regions'] = microtime(true) - $start;
        $start = microtime(true);
        $result->workingGroups = $this->searchGateway->searchWorkingGroups($query, $foodsaverId, $searchAllWorkingGroups);
        $result->timings['groups'] = microtime(true) - $start;
        $start = microtime(true);
        $result->stores = $this->searchGateway->searchStores($query, $foodsaverId, $includeInactiveStores, $searchGlobal);
        $result->timings['stores'] = microtime(true) - $start;
        $start = microtime(true);
        $result->foodSharePoints = $this->searchGateway->searchFoodSharePoints($query, $foodsaverId, $searchGlobal);
        $result->timings['fsp'] = microtime(true) - $start;
        $start = microtime(true);
        $result->chats = $this->searchGateway->searchChats($query, $foodsaverId);
        $result->timings['chats'] = microtime(true) - $start;
        $start = microtime(true);
        $result->threads = $this->searchGateway->searchThreads($query, $foodsaverId);
        $result->timings['threads'] = microtime(true) - $start;
        $start = microtime(true);
        $result->users = $this->searchGateway->searchUsers($query, $foodsaverId, $searchGlobal, $this->searchPermissions->maySearchByEmailAddress());
        $result->timings['users'] = microtime(true) - $start;

        return $result;
    }

    /**
     * Assembles an index for quickly searching the users  for regions, stores, foodsavers, food share points and working groups.
     */
    public function searchIndex(): MixedSearchResult
    {
        $foodsaverId = $this->session->id();
        $result = new MixedSearchResult();
        $result->regions = $this->searchGateway->getRegionsForSearchIndex($foodsaverId);
        $result->workingGroups = $this->searchGateway->getWorkingGroupsForSearchIndex($foodsaverId);
        $result->stores = $this->searchGateway->getStoresForSearchIndex($foodsaverId);
        $result->foodSharePoints = $this->searchGateway->getFoodSharePointsForSearchIndex($foodsaverId);
        $result->chats = $this->searchGateway->getChatsForSearchIndex($foodsaverId);
        $result->threads = $this->searchGateway->getThreadsForSearchIndex($foodsaverId);
        $result->users = $this->searchGateway->getUsersForSearchIndex($foodsaverId);

        return $result;
    }
}
