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
     *
     * TODO: this could use cleaner code and should not contain hard-coded user IDs
     */
    public function updateSpecialGroupMemberships(): void
    {
        ConsoleHelper::info('updating HH bieb austausch');
        $hh_biebs = $this->storeGateway->getStoreManagersOf(31);
        $hh_biebs[] = 3166;   // Gerard Roscoe
        $counts = $this->foodsaverGateway->updateGroupMembers(826, $hh_biebs, true);
        ConsoleHelper::info('+' . $counts['inserts'] . ', -' . $counts['deletions']);

        ConsoleHelper::info('updating Europe Bot group');
        $bots = $this->foodsaverGateway->getRegionAmbassadorIds(RegionIDs::EUROPE);
        $counts = $this->foodsaverGateway->updateGroupMembers(RegionIDs::EUROPE_BOT_GROUP, $bots, true);
        ConsoleHelper::info('+' . $counts['inserts'] . ', -' . $counts['deletions']);

        ConsoleHelper::info('updating berlin bieb austausch');
        $berlin_biebs = $this->storeGateway->getStoreManagersOf(47);
        $counts = $this->foodsaverGateway->updateGroupMembers(1057, $berlin_biebs, true);
        ConsoleHelper::info('+' . $counts['inserts'] . ', -' . $counts['deletions']);

        ConsoleHelper::info('updating Switzerland BOT group');
        $chBots = $this->foodsaverGateway->getRegionAmbassadorIds(RegionIDs::SWITZERLAND);
        $counts = $this->foodsaverGateway->updateGroupMembers(RegionIDs::SWITZERLAND_BOT_GROUP, $chBots, true);
        ConsoleHelper::info('+' . $counts['inserts'] . ', -' . $counts['deletions']);

        ConsoleHelper::info('updating Austria BOT group');
        $aBots = $this->foodsaverGateway->getRegionAmbassadorIds(RegionIDs::AUSTRIA);
        $counts = $this->foodsaverGateway->updateGroupMembers(RegionIDs::AUSTRIA_BOT_GROUP, $aBots, true);
        ConsoleHelper::info('+' . $counts['inserts'] . ', -' . $counts['deletions']);

        ConsoleHelper::info('updating Zürich BIEB group');
        $zuerich_biebs = $this->storeGateway->getStoreManagersOf(108);
        $counts = $this->foodsaverGateway->updateGroupMembers(1313, $zuerich_biebs, true);
        ConsoleHelper::info('+' . $counts['inserts'] . ', -' . $counts['deletions']);

        ConsoleHelper::info('updating Wien BIEB group');
        $wien_biebs = $this->storeGateway->getStoreManagersOf(13);
        $counts = $this->foodsaverGateway->updateGroupMembers(707, $wien_biebs, true);
        ConsoleHelper::info('+' . $counts['inserts'] . ', -' . $counts['deletions']);

        ConsoleHelper::info('updating Graz BIEB group');
        $graz_biebs = $this->storeGateway->getStoreManagersOf(149);
        $counts = $this->foodsaverGateway->updateGroupMembers(1655, $graz_biebs, true);
        ConsoleHelper::info('+' . $counts['inserts'] . ', -' . $counts['deletions']);

        ConsoleHelper::info('updating Dresden BIEB group');
        $dresden_biebs = $this->storeGateway->getStoreManagersOf(91);
        $counts = $this->foodsaverGateway->updateGroupMembers(1348, $dresden_biebs, true);
        ConsoleHelper::info('+' . $counts['inserts'] . ', -' . $counts['deletions']);

        /*
                self::info('updating Welcome Team Admin group');
                $this->goalsAdminCommunicationGroups(WorkgroupFunction::WELCOME, RegionIDs::WELCOME_TEAM_ADMIN_GROUP);
        */
        ConsoleHelper::info('updating Voting Admin group');
        $this->goalsAdminCommunicationGroups(WorkgroupFunction::VOTING, RegionIDs::VOTING_ADMIN_GROUP);

        ConsoleHelper::info('updating Election Admin group');
        $this->goalsAdminCommunicationGroups(WorkgroupFunction::ELECTION, RegionIDs::ELECTION_ADMIN_GROUP);

        /*		self::info('updating Foodsharepoint Team Admin group');
                $this->goalsAdminCommunicationGroups(WorkgroupFunction::FSP, RegionIDs::FSP_TEAM_ADMIN_GROUP);

                self::info('updating Store Coordination Team Admin group');
                $this->goalsAdminCommunicationGroups(WorkgroupFunction::STORES_COORDINATION, RegionIDs::STORE_COORDINATION_TEAM_ADMIN_GROUP);

                self::info('updating Report Team Admin group');
                $this->goalsAdminCommunicationGroups(WorkgroupFunction::REPORT, RegionIDs::REPORT_TEAM_ADMIN_GROUP);

                self::info('updating Mediation Team Admin group');
                $this->goalsAdminCommunicationGroups(WorkgroupFunction::MEDIATION, RegionIDs::MEDIATION_TEAM_ADMIN_GROUP);

                self::info('updating Arbitration Team Admin group');
                $this->goalsAdminCommunicationGroups(WorkgroupFunction::ARBITRATION, RegionIDs::ARBITRATION_TEAM_ADMIN_GROUP);

                self::info('updating FSManagement Team Admin group');
                $this->goalsAdminCommunicationGroups(WorkgroupFunction::FSMANAGEMENT, RegionIDs::FSMANAGEMENT_TEAM_ADMIN_GROUP);

                self::info('updating PR Team Admin group');
                $this->goalsAdminCommunicationGroups(WorkgroupFunction::PR, RegionIDs::PR_TEAM_ADMIN_GROUP);

                self::info('updating Moderation Team Admin group');
                $this->goalsAdminCommunicationGroups(WorkgroupFunction::MODERATION, RegionIDs::MODERATION_TEAM_ADMIN_GROUP);
        */
        ConsoleHelper::info('updating Board Admin group');
        $this->goalsAdminCommunicationGroups(WorkgroupFunction::BOARD, RegionIDs::BOARD_ADMIN_GROUP);

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

    public function deleteImages(): void
    {
        @unlink('images/.jpg');
        @unlink('images/.png');

        /* foodsaver photos */
        if ($foodsaver = $this->maintenanceGateway->listUsersWithPhoto()) {
            $update = [];
            foreach ($foodsaver as $fs) {
                if (!str_starts_with((string)$fs['photo'], '/api/uploads')) {
                    if (!file_exists('images/' . $fs['photo'])) {
                        $update[] = $fs['id'];
                    }
                }
            }
            if (!empty($update)) {
                $this->maintenanceGateway->unsetUserPhotos($update);
            }
        }
        $check = [];
        if ($foodsaver = $this->maintenanceGateway->listUsersWithPhoto()) {
            foreach ($foodsaver as $fs) {
                if (!str_starts_with('/api/uploads', (string)$fs['photo'])) {
                    $check[$fs['photo']] = $fs['id'];
                }
            }
            $dir = opendir('./images');
            $count = 0;
            while (($file = readdir($dir)) !== false) {
                if (strlen($file) > 3 && !is_dir('./images/' . $file)) {
                    $cfile = $file;
                    if (str_contains($file, '_')) {
                        $cfile = explode('_', $file);
                        $cfile = end($cfile);
                    }
                    if (!isset($check[$cfile])) {
                        ++$count;
                        @unlink('./images/' . $file);
                        @unlink('./images/130_q_' . $file);
                        @unlink('./images/50_q_' . $file);
                        @unlink('./images/med_q_' . $file);
                        @unlink('./images/mini_q_' . $file);
                        @unlink('./images/thumb_' . $file);
                        @unlink('./images/thumb_crop_' . $file);
                        @unlink('./images/q_' . $file);
                    }
                }
            }
        }
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

    public function deleteOldIpBlocks(): void
    {
        ConsoleHelper::info('deleting old blocked IPs...');
        $count = $this->maintenanceGateway->deleteOldIpBlocks();
        ConsoleHelper::success($count . ' entries deleted');
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
}
