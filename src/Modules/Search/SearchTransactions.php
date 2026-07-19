<?php

namespace Foodsharing\Modules\Search;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Development\FeatureToggles\DependencyInjection\FeatureToggleChecker;
use Foodsharing\Modules\Development\FeatureToggles\Enums\FeatureToggleDefinitions;
use Foodsharing\Modules\Mailbox\MailboxGateway;
use Foodsharing\Modules\Search\DTO\MixedSearchResult;
use Foodsharing\Modules\Search\DTO\ThreadSearchResult;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Permissions\ForumPermissions;
use Foodsharing\Permissions\SearchPermissions;

class SearchTransactions
{
    public function __construct(
        private readonly SearchGateway $searchGateway,
        private readonly MailboxGateway $mailboxGateway,
        private readonly Session $session,
        private readonly SearchPermissions $searchPermissions,
        private readonly ForumPermissions $forumPermissions,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
        private readonly FeatureToggleChecker $featureToggleChecker,
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
        $result = new MixedSearchResult();
        $result->timings = [];

        $start = microtime(true);
        $foodsaverId = $this->session->id();
        $maySearchGlobal = $this->searchPermissions->maySearchGlobal();
        $searchGlobal = $global && $maySearchGlobal;
        $searchAllWorkingGroups = $this->searchPermissions->maySearchAllWorkingGroups();
        $searchAllFoodSharePoints = $searchGlobal || $this->searchPermissions->maySearchAllFoodSharePoints();
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
        $result->foodSharePoints = $this->searchGateway->searchFoodSharePoints($query, $foodsaverId, $searchAllFoodSharePoints);
        $result->timings['fsp'] = microtime(true) - $start;
        $start = microtime(true);
        $result->chats = $this->searchGateway->searchChats($query, $foodsaverId);
        $result->timings['chats'] = microtime(true) - $start;
        $start = microtime(true);
        $threads = $this->searchGateway->searchThreads($query, $foodsaverId);
        if ($this->featureToggleChecker->isFeatureToggleActive(FeatureToggleDefinitions::FORUM_FULL_TEXT_SEARCH->value)) {
            $threads_by_body = $this->searchGateway->searchThreads($query, $foodsaverId, searchBody: true);
            $threads = array_merge($threads, $threads_by_body);
        }
        $result->threads = array_values(array_unique($threads, SORT_REGULAR));
        $result->timings['threads'] = microtime(true) - $start;
        $start = microtime(true);
        $result->users = $this->searchGateway->searchUsers($query, $foodsaverId, $searchGlobal, $this->searchPermissions->maySearchByEmailAddress());
        $result->timings['users'] = microtime(true) - $start;

        if ($this->featureToggleChecker->isFeatureToggleActive(FeatureToggleDefinitions::MAIL_SEARCH->value)) {
            $start = microtime(true);
            $mailboxes = $this->mailboxGateway->getBoxes($this->currentUserUnits->isAdminFor(null), $foodsaverId);
            $mailboxIds = array_map(fn ($mailbox) => $mailbox->id, $mailboxes);
            $result->mails = $this->searchGateway->searchMails($query, $mailboxIds);
            $result->timings['mails'] = microtime(true) - $start;
        }

        $start = microtime(true);
        $result->events = $this->searchGateway->searchEvents($query, $foodsaverId, $searchGlobal);
        $result->timings['events'] = microtime(true) - $start;
        $start = microtime(true);
        $result->polls = $this->searchGateway->searchPolls($query, $foodsaverId, $searchGlobal);
        $result->timings['polls'] = microtime(true) - $start;

        return $result;
    }

    /**
     * Assembles an index for quickly searching the users  for regions, stores, foodsavers, food share points and working groups.
     */
    public function searchIndex(): MixedSearchResult
    {
        $foodsaverId = $this->session->id();
        $mailboxes = $this->mailboxGateway->getBoxes($this->currentUserUnits->isAdminFor(null), $foodsaverId);
        $mailboxIds = array_map(fn ($mailbox) => $mailbox->id, $mailboxes);

        $result = new MixedSearchResult();
        $result->regions = $this->searchGateway->getRegionsForSearchIndex($foodsaverId);
        $result->workingGroups = $this->searchGateway->getWorkingGroupsForSearchIndex($foodsaverId);
        $result->stores = $this->searchGateway->getStoresForSearchIndex($foodsaverId);
        $result->foodSharePoints = $this->searchGateway->getFoodSharePointsForSearchIndex($foodsaverId);
        $result->chats = $this->searchGateway->getChatsForSearchIndex($foodsaverId);
        $result->threads = $this->searchGateway->getThreadsForSearchIndex($foodsaverId);
        $result->users = $this->searchGateway->getUsersForSearchIndex($foodsaverId);
        if ($this->featureToggleChecker->isFeatureToggleActive(FeatureToggleDefinitions::MAIL_SEARCH->value)) {
            $result->mails = $this->searchGateway->getMailsForSearchIndex($mailboxIds);
        }
        $result->events = $this->searchGateway->getEventsForSearchIndex($foodsaverId);
        $result->polls = $this->searchGateway->getPollsForSearchIndex($foodsaverId);

        return $result;
    }

    /**
     * @return ThreadSearchResult[]
     */
    public function searchThreads(string $query, int $regionId = 0, int $subforumId = 0, bool $searchBody = false): array
    {
        if ($searchBody && !$this->featureToggleChecker->isFeatureToggleActive(FeatureToggleDefinitions::FORUM_FULL_TEXT_SEARCH->value)) {
            $searchBody = false;
        }
        $disableRegionCheck = $this->forumPermissions->maySearchEveryForum();

        return $this->searchGateway->searchThreads($query, $this->session->id(), $regionId, $subforumId, $disableRegionCheck, $searchBody);
    }
}
