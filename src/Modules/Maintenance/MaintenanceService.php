<?php

namespace Foodsharing\Modules\Maintenance;

use Carbon\Carbon;
use Foodsharing\Modules\Basket\BasketGateway;
use Foodsharing\Modules\Basket\BasketTransactions;
use Foodsharing\Modules\Bell\BellUpdateTrigger;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Foodsaver\FoodsaverTransactions;
use Foodsharing\Modules\Region\ForumTransactions;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Modules\Store\StoreMaintenanceTransactions;
use Foodsharing\Modules\Uploads\UploadsTransactions;
use Foodsharing\Utility\ConsoleHelper;
use Foodsharing\Utility\IMAPFolderCleanupHelper;

class MaintenanceService
{
    final public const int DELETE_DELAY_DAYS = 30;

    public function __construct(
        private readonly StoreGateway $storeGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly FoodsaverTransactions $foodsaverTransactions,
        private readonly MaintenanceGateway $maintenanceGateway,
        private readonly BellUpdateTrigger $bellUpdateTrigger,
        private readonly StoreMaintenanceTransactions $storeMaintenanceTransactions,
        private readonly UploadsTransactions $uploadsTransactions,
        private readonly IMAPFolderCleanupHelper $imapFolderCleanupHelper,
        private readonly ForumTransactions $forumTransactions,
        private readonly BasketGateway $basketGateway,
        private readonly BasketTransactions $basketTransactions,
    ) {
    }

    /**
     * Deletes users that have been inactive for more than 5 years.
     */
    public function deleteInactiveUsers(bool $dryRun = false, int $maximum = MAX_DELETE_OLD_ACCOUNTS_PER_DAY): void
    {
        if ($maximum < 0) {
            ConsoleHelper::error('The maximal number of accounts must be positive');

            return;
        }

        $arrayAccountsNotDeleted = [];
        $accountsDeleted = 0;
        ConsoleHelper::info('deleting users inactive > 5 years');
        $inactiveUsers = $this->foodsaverGateway->listInactiveUsers();
        if ($inactiveUsers) {
            ConsoleHelper::info('...checking ' . count($inactiveUsers) . ' accounts');
            foreach ($inactiveUsers as $fs) {
                if ($this->storeGateway->listStoreIds($fs)) {
                    $arrayAccountsNotDeleted[] = $fs;
                } else {
                    if (!$dryRun) {
                        $this->foodsaverTransactions->deleteFoodsaver($fs, null, 'Automatic inactivity deletion');
                    }
                    ++$accountsDeleted;
                }
                if ($accountsDeleted === $maximum) {
                    break;
                }
            }
            ConsoleHelper::info(count($arrayAccountsNotDeleted) . ' users where not deleted due to store memberships');
            ConsoleHelper::info('Number of Accounts deleted: ' . $accountsDeleted);
        } else {
            ConsoleHelper::info('no inactive users found');
        }
    }

    public function deleteImapFolderMails($deleteDelayDays = self::DELETE_DELAY_DAYS): void
    {
        ConsoleHelper::info('cleaning up IMAP folders...');
        foreach (IMAP as $imap) {
            $deleted = $this->imapFolderCleanupHelper->cleanupFolder($imap['host'], $imap['user'], $imap['password'], IMAP_FAILED_BOX, $deleteDelayDays);
            ConsoleHelper::info($deleted . ' E-Mails deleted from ' . $imap['host'] . ' ' . IMAP_FAILED_BOX);
            $deleted = $this->imapFolderCleanupHelper->cleanupFolder($imap['host'], $imap['user'], $imap['password'], BOUNCE_IMAP_UNPROCESSED_BOX, $deleteDelayDays);
            ConsoleHelper::info($deleted . ' E-Mails deleted from ' . $imap['host'] . ' ' . BOUNCE_IMAP_UNPROCESSED_BOX);
        }
        ConsoleHelper::success('All folders processed');
    }

    /**
     * Updates the region closure table, which lists all parents for each region.
     */
    public function rebuildRegionClosure(): void
    {
        ConsoleHelper::info('rebuilding region closure...');
        $this->maintenanceGateway->recreateClosure();
        ConsoleHelper::success('OK');
    }

    /**
     * Adds some store managers and ambassadors to specific groups.
     */
    public function updateSpecialGroupMemberships(): void
    {
        // name, source group, destination group
        $storeManagerGroups = [
            ['Hamburg bieb austausch', 31, 826],
            ['Berlin bieb austausch', 47, 1057],
            ['Zürich BIEB group', 108, 1313],
            ['Wien BIEB group', 13, 707],
            ['Graz BIEB group', 149, 1655],
            ['Dresden BIEB group', 91, 1348],
        ];
        foreach ($storeManagerGroups as $group) {
            ConsoleHelper::info('updating ' . $group[0]);
            $managers = $this->storeGateway->getStoreManagersOf($group[1]);
            $counts = $this->foodsaverGateway->updateGroupMembers($group[2], $managers, true);
            ConsoleHelper::info('+' . $counts['inserts'] . ', -' . $counts['deletions']);
        }

        $ambassadorGroups = [
            ['Europe Bot group', RegionIDs::EUROPE, RegionIDs::EUROPE_BOT_GROUP],
            ['Switzerland BOT group', RegionIDs::SWITZERLAND, RegionIDs::SWITZERLAND_BOT_GROUP],
            ['Austria BOT group', RegionIDs::AUSTRIA, RegionIDs::AUSTRIA_BOT_GROUP],
        ];
        foreach ($ambassadorGroups as $group) {
            ConsoleHelper::info('updating ' . $group[0]);
            $ambassadors = $this->foodsaverGateway->getRegionAmbassadorIds($group[1]);
            $counts = $this->foodsaverGateway->updateGroupMembers($group[2], $ambassadors, true);
            ConsoleHelper::info('+' . $counts['inserts'] . ', -' . $counts['deletions']);
        }

        $specialGroups = [
            // ['Welcome Team Admin group', WorkgroupFunction::WELCOME, RegionIDs::WELCOME_TEAM_ADMIN_GROUP],
            ['Voting Admin group', WorkgroupFunction::VOTING, RegionIDs::VOTING_ADMIN_GROUP],
            ['Election Admin group', WorkgroupFunction::ELECTION, RegionIDs::ELECTION_ADMIN_GROUP],
            // ['Foodsharepoint Team Admin group', WorkgroupFunction::FSP, RegionIDs::FSP_TEAM_ADMIN_GROUP],
            // ['Store Coordination Team Admin group', WorkgroupFunction::STORES_COORDINATION, RegionIDs::STORE_COORDINATION_TEAM_ADMIN_GROUP],
            // ['Report Team Admin group', WorkgroupFunction::REPORT, RegionIDs::REPORT_TEAM_ADMIN_GROUP],
            // ['Mediation Team Admin group', WorkgroupFunction::MEDIATION, RegionIDs::MEDIATION_TEAM_ADMIN_GROUP],
            // ['Arbitration Team Admin group', WorkgroupFunction::ARBITRATION, RegionIDs::ARBITRATION_TEAM_ADMIN_GROUP],
            // ['FSManagement Team Admin group', WorkgroupFunction::FSMANAGEMENT, RegionIDs::FSMANAGEMENT_TEAM_ADMIN_GROUP],
            // ['PR Team Admin group', WorkgroupFunction::PR, RegionIDs::PR_TEAM_ADMIN_GROUP],
            // ['Moderation Team Admin group', WorkgroupFunction::MODERATION, RegionIDs::MODERATION_TEAM_ADMIN_GROUP],
            ['Board Admin group', WorkgroupFunction::BOARD, RegionIDs::BOARD_ADMIN_GROUP],
        ];
        foreach ($specialGroups as $group) {
            ConsoleHelper::info('updating ' . $group[0]);
            $this->goalsAdminCommunicationGroups($group[1], $group[2]);
        }

        ConsoleHelper::info('updating orga Admin group');
        $orga = $this->foodsaverGateway->getOrgaTeamId();
        $counts = $this->foodsaverGateway->updateGroupMembers(RegionIDs::ORGA_COORDINATION_GROUP, array_column($orga, 'id'), true);
        ConsoleHelper::info('+' . $counts['inserts'] . ', -' . $counts['deletions']);
    }

    private function goalsAdminCommunicationGroups(int $workGroupFunction, int $regionIdAdminGroup): void
    {
        $teamAdmins = $this->foodsaverGateway->getWorkgroupFunctionAdminIds($workGroupFunction);
        $counts = $this->foodsaverGateway->updateGroupMembers($regionIdAdminGroup, $teamAdmins, true);
        ConsoleHelper::info('+' . $counts['inserts'] . ', -' . $counts['deletions']);
    }

    /**
     * Deactivates expired food baskets.
     */
    public function deactivateBaskets(): void
    {
        $basketIds = $this->maintenanceGateway->listOldBaskets();
        foreach ($basketIds as $basketId) {
            $this->basketTransactions->removeBasket($this->basketGateway->getBasket($basketId));
        }
        ConsoleHelper::info(count($basketIds) . ' old foodbaskets deactivated');
    }

    /**
     * Deletes all files that were uploaded after release "Laugenbrezel" (when usage types were introduced) and up
     * to two days ago, which do not have a usage type yet. If a file was uploaded but a usage type was not set, it
     * can be safely deleted. The offset of two days is used to make sure that there was enough time for the user to
     * set the file's usage.
     */
    public function deleteUnusedImages(): void
    {
        $fromDate = Carbon::parse('2024-05-08 00:00:00');
        $toDate = Carbon::now()->subDays(2);

        ConsoleHelper::info('deleting uploaded files without usage...');
        $uuids = $this->maintenanceGateway->listUploadsWithoutUsage($fromDate, $toDate);
        foreach ($uuids as $uuid) {
            $this->uploadsTransactions->deleteUploadedFile($uuid);
        }
        ConsoleHelper::success(sizeof($uuids) . ' files deleted');
    }

    /**
     * If a region is a master region, it means that all users which are members of regions hierarchical under this
     * region must also be in the master region. This function makes sure that they are.
     */
    public function masterBezirkUpdate(): void
    {
        ConsoleHelper::info('master bezirk update');
        $this->maintenanceGateway->masterRegionUpdate();
        ConsoleHelper::success('OK');
    }

    /**
     * Sends warning e-mails to store managers if there are free slots in their stores.
     */
    public function storeTriggerPickupWarnings(): void
    {
        try {
            $statistics = $this->storeMaintenanceTransactions->triggerFetchWarningNotification();
            ConsoleHelper::info('send ' . $statistics['warned foodsavers'] . ' warnings...');
            foreach ($statistics as $key => $stat) {
                ConsoleHelper::info(' - ' . $key . ': ' . $stat);
            }
            ConsoleHelper::success('OK');
        } catch (\Exception $ex) {
            ConsoleHelper::error($ex);
        }
    }

    /**
     * Removes questions and results from finished quiz sessions older than 2 weeks.
     */
    public function cleanOldQuizSessionData(): void
    {
        ConsoleHelper::info('reducing data from finished quiz sessions...');
        $count = $this->maintenanceGateway->cleanOldQuizSessionData();
        ConsoleHelper::success($count . ' sessions updated');
    }

    /**
     * Deletes test quiz sessions that are older than a day.
     */
    public function deleteTestQuizSessions(): void
    {
        ConsoleHelper::info('deleting test quiz sessions...');
        $count = $this->maintenanceGateway->deleteTestQuizSessions();
        ConsoleHelper::success($count . ' sessions deleted');
    }

    public function deleteHiddenForumPosts(): void
    {
        ConsoleHelper::info('deleting hidden forum posts...');
        $count = $this->maintenanceGateway->deleteHiddenForumPosts($this->forumTransactions);
        ConsoleHelper::success($count . ' posts deleted');
    }

    /**
     * Delete old requests for resetting the password.
     */
    public function deleteOldPassRequests(): void
    {
        ConsoleHelper::info('deleting old password reset requests...');
        $count = $this->maintenanceGateway->deleteOldPassRequests();
        ConsoleHelper::success($count . ' entries deleted');
    }

    public function deleteOldRegistrationAttempts(): void
    {
        ConsoleHelper::info('deleting old registration attempts...');
        $count = $this->maintenanceGateway->deleteOldRegistrationAttempts();
        ConsoleHelper::success($count . ' entries deleted');
    }

    public function triggerBellUpdates(): void
    {
        ConsoleHelper::info('Updating old bells...');
        $this->bellUpdateTrigger->triggerUpdate();
        ConsoleHelper::success('OK');
    }

    /**
     * Deletes expired and revoked OAuth tokens, authorization codes, and user consents.
     */
    public function deleteExpiredOAuthTokens(): void
    {
        ConsoleHelper::info('cleaning up expired OAuth tokens...');
        $count = $this->maintenanceGateway->deleteExpiredOAuthTokens();
        ConsoleHelper::success($count . ' OAuth entries deleted');
    }

    public function deleteExpiredMailChanges(): void
    {
        ConsoleHelper::info('cleaning up expired email-change requests...');
        $count = $this->maintenanceGateway->deleteExpiredMailChanges();
        ConsoleHelper::success($count . ' email-change requests deleted');
    }
}
