<?php

namespace Foodsharing\Modules\Maintenance;

use Foodsharing\Modules\Bell\BellUpdateTrigger;
use Foodsharing\Modules\Console\ConsoleControl;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Group\GroupGateway;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Modules\Store\StoreMaintenanceTransactions;
use Foodsharing\Utility\IMAPFolderCleanupHelper;

class MaintenanceControl extends ConsoleControl
{
    final public const DELETE_DELAY_DAYS = 30;

    public function __construct(
        private readonly StoreGateway $storeGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly MaintenanceGateway $maintenanceGateway,
        private readonly BellUpdateTrigger $bellUpdateTrigger,
        private readonly GroupGateway $groupGateway,
        private readonly StoreMaintenanceTransactions $storeMaintenanceTransactions,
        private readonly IMAPFolderCleanupHelper $imapFolderCleanupHelper
    ) {
        parent::__construct();
    }

    public function warnings()
    {
        $this->storeTriggerPickupWarnings();
    }

    public function daily()
    {
        /*
         * warn store manager if there are no fetching people
         */
        $this->storeTriggerPickupWarnings();

        /*
         * fill memcache with info about users if they want information mails etc.
         */
        $this->memcacheUserInfo();

        /*
         * delete unused images
         */
        $this->deleteImages();

        /*
         * deactivate too old food baskets
         */
        $this->deactivateBaskets();

        /*
         * Update Bezirk closure table
         *
         * it gets crashed by some updates sometimes, workaround: Rebuild every day
         */
        $this->rebuildRegionClosure();

        /*
         * Master Bezirk Update
         *
         * we have master bezirk that mean any user hierarchical under this bezirk have to be also in master self
         */
        $this->masterBezirkUpdate();

        /*
         * Delete old blocked ips
         */
        $this->deleteOldIpBlocks();

        /*
         * There may be some groups where people should automatically be added
         * (e.g. Hamburgs BIEB group)
         */
        $this->updateSpecialGroupMemberships();

        /*
         * sleeping users, where the time period of sleepiness ended
         */
        $this->wakeupSleepingUsers();

        /*
        * put users to sleep whose sleeping period begins
        */
        $this->putUsersToSleep();

        /*
         * updates outdated bells with passed expiration date
         */
        $this->bellUpdateTrigger->triggerUpdate();

        /*
         * removing questions from finished quiz sessions
         */
        $this->updateFinishedQuizSessions();

        /*
         * Remove failed and unprocessed E-Mais form IMAP folder
         */
        if (getenv('FS_ENV') !== 'dev') {
            $this->deleteImapFolderMails();
        }
    }

    public function rebuildRegionClosure()
    {
        self::info('rebuilding region closure...');
        $this->groupGateway->recreateClosure();
        self::success('OK');
    }

    private function updateSpecialGroupMemberships()
    {
        self::info('updating HH bieb austausch');
        $hh_biebs = $this->storeGateway->getStoreManagersOf(31);
        $hh_biebs[] = 3166;   // Gerard Roscoe
        $counts = $this->foodsaverGateway->updateGroupMembers(826, $hh_biebs, true);
        self::info('+' . $counts['inserts'] . ', -' . $counts['deletions']);

        self::info('updating Europe Bot group');
        $bots = $this->foodsaverGateway->getRegionAmbassadorIds(RegionIDs::EUROPE);
        $counts = $this->foodsaverGateway->updateGroupMembers(RegionIDs::EUROPE_BOT_GROUP, $bots, true);
        self::info('+' . $counts['inserts'] . ', -' . $counts['deletions']);

        self::info('updating berlin bieb austausch');
        $berlin_biebs = $this->storeGateway->getStoreManagersOf(47);
        $counts = $this->foodsaverGateway->updateGroupMembers(1057, $berlin_biebs, true);
        self::info('+' . $counts['inserts'] . ', -' . $counts['deletions']);

        self::info('updating Switzerland BOT group');
        $chBots = $this->foodsaverGateway->getRegionAmbassadorIds(RegionIDs::SWITZERLAND);
        $counts = $this->foodsaverGateway->updateGroupMembers(RegionIDs::SWITZERLAND_BOT_GROUP, $chBots, true);
        self::info('+' . $counts['inserts'] . ', -' . $counts['deletions']);

        self::info('updating Austria BOT group');
        $aBots = $this->foodsaverGateway->getRegionAmbassadorIds(RegionIDs::AUSTRIA);
        $counts = $this->foodsaverGateway->updateGroupMembers(RegionIDs::AUSTRIA_BOT_GROUP, $aBots, true);
        self::info('+' . $counts['inserts'] . ', -' . $counts['deletions']);

        self::info('updating Zürich BIEB group');
        $zuerich_biebs = $this->storeGateway->getStoreManagersOf(108);
        $counts = $this->foodsaverGateway->updateGroupMembers(1313, $zuerich_biebs, true);
        self::info('+' . $counts['inserts'] . ', -' . $counts['deletions']);

        self::info('updating Wien BIEB group');
        $wien_biebs = $this->storeGateway->getStoreManagersOf(13);
        $counts = $this->foodsaverGateway->updateGroupMembers(707, $wien_biebs, true);
        self::info('+' . $counts['inserts'] . ', -' . $counts['deletions']);

        self::info('updating Graz BIEB group');
        $graz_biebs = $this->storeGateway->getStoreManagersOf(149);
        $counts = $this->foodsaverGateway->updateGroupMembers(1655, $graz_biebs, true);
        self::info('+' . $counts['inserts'] . ', -' . $counts['deletions']);

        /*
                self::info('updating Welcome Team Admin group');
                $this->goalsAdminCommunicationGroups(WorkgroupFunction::WELCOME, RegionIDs::WELCOME_TEAM_ADMIN_GROUP);
        */
        self::info('updating Voting Admin group');
        $this->goalsAdminCommunicationGroups(WorkgroupFunction::VOTING, RegionIDs::VOTING_ADMIN_GROUP);

        self::info('updating Election Admin group');
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
        self::info('updating Board Admin group');
        $this->goalsAdminCommunicationGroups(WorkgroupFunction::BOARD, RegionIDs::BOARD_ADMIN_GROUP);

        self::info('updating orga Admin group');
        $orga = $this->foodsaverGateway->getOrgaTeamId();
        $counts = $this->foodsaverGateway->updateGroupMembers(RegionIDs::ORGA_COORDINATION_GROUP, array_column($orga, 'id'), true);
        self::info('+' . $counts['inserts'] . ', -' . $counts['deletions']);
    }

    private function goalsAdminCommunicationGroups(int $workGroupFunction, int $regionIdAdminGroup)
    {
        $teamAdmins = $this->foodsaverGateway->getWorkgroupFunctionAdminIds($workGroupFunction);
        $counts = $this->foodsaverGateway->updateGroupMembers($regionIdAdminGroup, $teamAdmins, true);
        self::info('+' . $counts['inserts'] . ', -' . $counts['deletions']);
    }

    private function deactivateBaskets()
    {
        $count = $this->maintenanceGateway->deactivateOldBaskets();
        self::info($count . ' old foodbaskets deactivated');
    }

    private function deleteImages()
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

    private function memcacheUserInfo()
    {
        $admins = $this->foodsaverGateway->getAllWorkGroupAmbassadorIds();
        if (!$admins) {
            $admins = [];
        }
        $this->mem->set('all_global_group_admins', serialize($admins));
    }

    private function masterBezirkUpdate()
    {
        self::info('master bezirk update');
        $this->maintenanceGateway->masterRegionUpdate();
        self::success('OK');
    }

    public function storeTriggerPickupWarnings()
    {
        try {
            $statistics = $this->storeMaintenanceTransactions->triggerFetchWarningNotification();
            self::info('send ' . $statistics['count_warned_foodsavers'] . ' warnings...');
            foreach ($statistics as $key => $stat) {
                self::info(' - ' . $key . ': ' . $stat);
            }
            self::success('OK');
        } catch (\Exception $ex) {
            self::error($ex);
        }
    }

    private function wakeupSleepingUsers()
    {
        self::info('wake up sleeping users...');
        $count = $this->maintenanceGateway->wakeupSleepingUsers();
        self::success($count . ' users woken up');
    }

    private function putUsersToSleep()
    {
        self::info('put to sleep users...');
        $count = $this->maintenanceGateway->putUsersToSleep();
        self::success($count . ' users put to sleep');
    }

    private function deleteOldIpBlocks()
    {
        self::info('deleting old blocked IPs...');
        $count = $this->maintenanceGateway->deleteOldIpBlocks();
        self::success($count . ' entries deleted');
    }

    private function updateFinishedQuizSessions()
    {
        self::info('removing questions from finished quiz sessions...');
        $count = $this->maintenanceGateway->updateFinishedQuizSessions();
        self::success($count . ' sessions updated');
    }

    public function deleteImapFolderMails($deleteDelayDays = self::DELETE_DELAY_DAYS)
    {
        self::info('cleaning up IMAP folders...');
        foreach (IMAP as $imap) {
            $deleted = $this->imapFolderCleanupHelper->cleanupFolder($imap['host'], $imap['user'], $imap['password'], IMAP_FAILED_BOX, $deleteDelayDays);
            self::info($deleted . ' E-Mails deleted from ' . $imap['host'] . ' ' . IMAP_FAILED_BOX);
        }
        self::success('All folders processed');
    }
}
