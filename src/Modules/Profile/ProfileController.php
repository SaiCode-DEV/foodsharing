<?php

namespace Foodsharing\Modules\Profile;

use Carbon\Carbon;
use Exception;
use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Lib\WebSocketConnection;
use Foodsharing\Modules\Achievement\AchievementGateway;
use Foodsharing\Modules\Basket\BasketGateway;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\RegionOptionType;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Group\GroupGateway;
use Foodsharing\Modules\Mailbox\MailboxGateway;
use Foodsharing\Modules\Mails\MailsGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Modules\StoreChain\StoreChainGateway;
use Foodsharing\Permissions\AchievementPermissions;
use Foodsharing\Permissions\ProfilePermissions;
use Foodsharing\Permissions\ReportPermissions;
use Foodsharing\Permissions\StoreChainPermissions;
use Foodsharing\Permissions\StorePermissions;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class ProfileController extends FoodsharingController
{
    public function __construct(
        private readonly MailsGateway $mailsGateway,
        private readonly RegionGateway $regionGateway,
        private readonly ProfileGateway $profileGateway,
        private readonly BasketGateway $basketGateway,
        private readonly MailboxGateway $mailboxGateway,
        private readonly ReportPermissions $reportPermissions,
        private readonly ProfilePermissions $profilePermissions,
        private readonly GroupFunctionGateway $groupFunctionGateway,
        private readonly StoreGateway $storeGateway,
        private readonly GroupGateway $groupGateway,
        private readonly StorePermissions $storePermissions,
        private readonly AchievementPermissions $achievementPermissions,
        private readonly AchievementGateway $achievementGateway,
        private readonly WebSocketConnection $webSocketConnection,
        private readonly StoreChainGateway $storeChainGateway,
        private readonly StoreChainPermissions $storeChainPermissions,
    ) {
        parent::__construct();
    }

    #[Route('/profile', name: 'profile_fallback')]
    #[Route('/profile/{userId}', name: 'profile_id', requirements: ['userId' => Requirement::DIGITS])]
    #[Route('/profile/{userId}/notes', name: 'profile_notes_fallback', requirements: ['userId' => Requirement::DIGITS])]
    public function oldRouteFallback(?int $userId): Response
    {
        if (empty($userId)) {
            $userId = $this->session->id();
        }

        return $this->redirectToRoute('user_profile', ['userId' => $userId]);
    }

    #[Route('/profile/{userId}/public', name: 'profile_id_public', requirements: ['userId' => Requirement::DIGITS])]
    public function oldRoutePublicFallback(int $userId): Response
    {
        return $this->redirectToRoute('user_profile_public', ['userId' => $userId]);
    }

    /**
     * @throws Exception
     */
    #[Route('/user/{userId}/profile', name: 'user_profile', requirements: ['userId' => Requirement::DIGITS])]
    public function index(?int $userId): Response
    {
        if (!$this->session->mayRole()) {
            return $this->redirectToRoute('user_profile_public', ['userId' => $userId]);
        }

        $maySeeStores = $this->profilePermissions->maySeeStores($userId);
        $userStores = $this->profileGateway->listStoresOfFoodsaver($userId);
        $userArray = $this->createUserArray($userId);
        $this->pageHelper->addTitle($userArray['name']);
        $params = $this->convertDataToObject($userStores, $userArray, $maySeeStores);

        $profilePage = $this->prepareVueComponent('vue-profile', 'Profile', $params);
        $this->pageHelper->addContent($profilePage);

        return $this->renderGlobal();
    }

    #[Route('/user/{userId}/profile/public', name: 'user_profile_public', requirements: ['userId' => Requirement::DIGITS])]
    public function profilePublic(int $userId): Response
    {
        $userArray = $this->createUserArray($userId);

        $isVerified = $userArray['verified'] ?? 0;
        $initials = mb_substr($userArray['name'] ?? '?', 0, 1) . '.';
        $regionId = $userArray['bezirk_id'] ?? null;
        $regionName = ($regionId === null) ? '?' : $this->regionGateway->getRegionName($regionId);

        $profilePage = $this->prepareVueComponent('profile-public', 'PublicProfile', [
            'canPickUp' => $isVerified > 0,
            'fromRegion' => $regionName,
            'fsId' => $userArray['id'] ?? '',
            'initials' => $initials,
        ]);
        $this->pageHelper->addContent($profilePage);

        return $this->renderGlobal();
    }

    private function createUserArray(int $userId): array
    {
        $viewerId = $this->session->id();
        $userArray = $this->profileGateway->getProfileDetails($userId);

        $isRemoved = (!$userArray) || isset($userArray['deleted_at']);
        if ($isRemoved) {
            $this->flashMessageHelper->error($this->translator->trans('profile.notFound'));
            $this->routeHelper->goPageAndExit('dashboard');
        }

        if ($this->reportPermissions->mayHandleReports()) {
            $userArray['violation_count'] = $this->profileGateway->getViolationCount($userId);
            $userArray['note_count'] = $this->profileGateway->getNotesCount($userId);
        }
        $userArray['botschafter'] = $this->profileGateway->getUserAdminGroups($userId, false);
        $userArray['orga'] = $this->profileGateway->getUserAdminGroups($userId, true);
        $userArray['foodsaver'] = $this->profileGateway->getOrderedRegionListForUser($userId);
        if ($viewerId) {
            $userArray['working_groups'] = $this->profileGateway->getCommonWorkingGroups($userId, $viewerId);
        }

        if ($viewerId) {
            $userArray['buddy'] = $this->profileGateway->buddyStatus($userId, $viewerId);
        }
        if ($this->profilePermissions->maySeeBounceWarning($userId)) {
            $emailIsBouncing = $this->mailsGateway->emailIsBouncing($userArray['email']);
            $userArray['emailIsBouncing'] = $emailIsBouncing;
            if ($this->profilePermissions->mayRemoveFromBounceList($userId)) {
                $userArray['emailBounceCategories'] = $this->mailsGateway->getBounces($userArray['email']);
            }
        }
        $userArray['basketCount'] = $this->basketGateway->getAmountOfFoodBaskets($userId);
        if ((int)$userArray['mailbox_id'] > 0 && $this->profilePermissions->maySeeEmailAddress($userId)) {
            $mailbox = $this->mailboxGateway->getMailboxname($userArray['mailbox_id']) . '@' . PLATFORM_MAILBOX_HOST;
            $userArray['mailbox'] = $mailbox;
        }

        return $userArray;
    }

    private function getProfileCommitmentsStat(int $fsId): array
    {
        $maySeeCommitmentsStat = $this->profilePermissions->maySeeCommitmentsStat($fsId);
        $profileCommitmentsStat[0]['respActStores'] = $maySeeCommitmentsStat ? $this->profileGateway->getResponsibleActiveStoresCount($fsId) : 0;
        $pos = 0;
        for ($i = 2; $i >= -2; --$i) {
            $date = Carbon::now()->addWeeks($i);
            $profileCommitmentsStat[$pos]['beginWeek'] = $date->startOfWeek()->format('d.m.y');
            $profileCommitmentsStat[$pos]['endWeek'] = $date->endOfWeek()->format('d.m.y');
            $profileCommitmentsStat[$pos]['week'] = $date->isoWeek();
            $profileCommitmentsStat[$pos]['data'] = $maySeeCommitmentsStat ? $this->profileGateway->getPickupsStat($fsId, $i) : [];
            $profileCommitmentsStat[$pos]['eventsCreated'] = $maySeeCommitmentsStat ? $this->profileGateway->getEventsCreatedCount($fsId, $i) : 0;
            $profileCommitmentsStat[$pos]['eventsParticipated'] = $maySeeCommitmentsStat ? $this->profileGateway->getEventsParticipatedCount($fsId, $i) : [];
            $profileCommitmentsStat[$pos]['baskets']['offered'] = $maySeeCommitmentsStat ? $this->profileGateway->getBasketsOfferedStat($fsId, $i) : [];
            if ($i <= 0) {
                $profileCommitmentsStat[$pos]['securePickupWeek'] = $maySeeCommitmentsStat ? $this->profileGateway->getSecuredPickupsCount($fsId, $i) : 0;
                $profileCommitmentsStat[$pos]['baskets']['shared'] = $maySeeCommitmentsStat ? $this->profileGateway->getBasketsShared($fsId, $i) : 0;
            }
            ++$pos;
        }

        return [
            'maySeeCommitmentsStat' => $maySeeCommitmentsStat,
            'data' => $profileCommitmentsStat
        ];
    }

    /**
     * @throws Exception
     */
    private function convertDataToObject(array $userStores, $userArray, $maySeeStores): array
    {
        return [
            'menu' => $this->getProfileMenu($userStores, $userArray, $maySeeStores),
            'statistics' => $this->renderStatistics($userArray),
            'ambassadorRegions' => $userArray['botschafter'] ?: [],
            'foodSaverRegions' => $userArray['foodsaver'] ?: [],
            'homeDistrictHistory' => (object)$this->getHomeDistrictHistory($userArray['id']),
            'aboutMeIntern' => $userArray['about_me_intern'] ?? '',
            'workingGroupsAdmins' => $userArray['orga'] ?: [],
            'kamPositions' => $this->getKamData($userArray['id']),
            'workingGroups' => $userArray['working_groups'] ?? [],
            'sleepingInformation' => $this->getSleepingHatInformation($userArray),
            'profileInfos' => $this->getProfileInfos($userArray),
            'profileCommitmentsStat' => $this->getProfileCommitmentsStat($userArray['id']),
            'bounceWarning' => (object)$this->getBounceWarning($userArray),
            'pickupsSection' => $this->getPickupsSection($userArray['id']),
            'maySeeUserNotes' => $this->profilePermissions->maySeeUserNotes($userArray['id']),
            'noteCount' => $userArray['note_count'] ?? 0,
            'stores' => $maySeeStores ? $userStores : [],
            'awardedAchievements' => $this->getAchievementsData($userArray['id']),
        ];
    }

    /**
     * @throws Exception
     */
    private function getProfileMenu(array $userStores, array $userArray, bool $maySeeStores): array
    {
        $fsId = $userArray['id'];
        $regionId = $userArray['bezirk_id'];
        $mayAdmin = $this->profilePermissions->mayAdministrateUserProfile($fsId, $regionId);
        $maySeeHistory = $this->profilePermissions->maySeeHistory($fsId);

        // what is the viewer allowed to do in this profile?
        if (!empty($regionId) && $userArray['rolle'] > Role::FOODSHARER->value) {
            // MediationRequest
            $regionOptions = $this->regionGateway->getAllRegionOptions($regionId);
            if ($regionOptions[RegionOptionType::ENABLE_MEDIATION_BUTTON] ?? false) {
                $mediationGroupEmail = $this->renderMediationRequest($userArray);
            }

            // ReportRequest
            $isReportButtonEnabled = boolval($regionOptions[RegionOptionType::ENABLE_REPORT_BUTTON] ?? false);

            if ($isReportButtonEnabled) {
                // if the current user is not allowed to see all stores of the profile, the report dialog will only show stores in which both users are
                if ($maySeeStores) {
                    $reportStores = $userStores;
                } else {
                    $myStores = $this->storeGateway->listMyStores($this->session->id());
                    $myStoreIds = array_column($myStores, 'id');
                    $reportStores = array_filter($userStores, fn ($store) => in_array($store['id'], $myStoreIds));
                }

                $storeListOptions = [['value' => null, 'text' => $this->translator->trans('profile.choosestore')]];
                foreach ($reportStores as $store) {
                    $storeListOptions[] = ['value' => $store['id'], 'text' => $store['name']];
                }
                $isReportedIdReportAdmin = $this->groupFunctionGateway->isRegionFunctionGroupAdmin(
                    $regionId,
                    WorkgroupFunction::REPORT,
                    $userArray['id']
                );
                $isReporterIdReportAdmin = $this->groupFunctionGateway->isRegionFunctionGroupAdmin(
                    $regionId,
                    WorkgroupFunction::REPORT,
                    $this->session->id()
                );
                $isReportedIdArbitrationAdmin = $this->groupFunctionGateway->isRegionFunctionGroupAdmin(
                    $regionId,
                    WorkgroupFunction::ARBITRATION,
                    $userArray['id']
                );
                $isReporterIdArbitrationAdmin = $this->groupFunctionGateway->isRegionFunctionGroupAdmin(
                    $regionId,
                    WorkgroupFunction::ARBITRATION,
                    $this->session->id()
                );

                $reportGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId(
                    $regionId,
                    WorkgroupFunction::REPORT
                );
                $hasReportGroup = $reportGroupId !== null;
                $reporterHasReportGroup = $hasReportGroup;

                if ($hasReportGroup) {
                    $mailboxNameReportRequest = $this->groupGateway->getGroupMailName($reportGroupId);
                }

                $arbitrationGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId(
                    $regionId,
                    WorkgroupFunction::ARBITRATION
                );
                $hasArbitrationGroup = $arbitrationGroupId !== null;

                if ($arbitrationGroupId !== null) {
                    $mailboxNameArbitrationRequest = $this->groupGateway->getGroupMailName($arbitrationGroupId);
                }

                if (!$this->currentUserUnits->getCurrentRegionId()) {
                    $reporterHasReportGroup = false;
                } elseif ($regionId != $this->currentUserUnits->getCurrentRegionId()) {
                    $reporterHasReportGroup = $this->groupFunctionGateway->existRegionFunctionGroup(
                        $this->currentUserUnits->getCurrentRegionId(),
                        WorkgroupFunction::REPORT
                    );
                }

                $buttonNameReportRequest = $this->translator->trans('profile.reportRequest');

                $reasonOptionOther = boolval($regionOptions[RegionOptionType::REPORT_REASON_OTHER] ?? 1);
                $reasonOptionSettings = intval($regionOptions[RegionOptionType::REPORT_REASON_OPTIONS] ?? 1);
            }
        }

        return [
            'isOnline' => $this->webSocketConnection->isUserOnline($fsId),
            'foodSaverName' => $userArray['name'],
            'photo' => $userArray['photo'],
            'fsId' => $userArray['id'],
            'fsIdSession' => $this->session->id(),
            'isSleeping' => $userArray['is_sleeping'],
            'initialBuddyType' => $userArray['buddy'],
            'mayAdmin' => $mayAdmin,
            'mayHistory' => $maySeeHistory,
            'violationCount' => $userArray['violation_count'] ?? 0,
            'mayViolation' => $this->reportPermissions->mayHandleReports(),
            'maySeeStores' => $maySeeStores,
            'hasLocalMediationGroup' => $this->groupFunctionGateway->existRegionFunctionGroup($regionId, WorkgroupFunction::MEDIATION),
            'mediationGroupEmail' => $mediationGroupEmail ?? '',
            'storeListOptions' => $storeListOptions ?? [],
            'isReportedIdReportAdmin' => $isReportedIdReportAdmin ?? false,
            'hasReportGroup' => $hasReportGroup ?? false,
            'hasArbitrationGroup' => $hasArbitrationGroup ?? false,
            'isReporterIdReportAdmin' => $isReporterIdReportAdmin ?? false,
            'isReporterIdArbitrationAdmin' => $isReporterIdArbitrationAdmin ?? false,
            'isReportedIdArbitrationAdmin' => $isReportedIdArbitrationAdmin ?? false,
            'isReportButtonEnabled' => $isReportButtonEnabled ?? false,
            'reasonOptionOther' => $reasonOptionOther ?? false,
            'reasonOptionSettings' => $reasonOptionSettings ?? 1,
            'reporterHasReportGroup' => $reporterHasReportGroup ?? false,
            'mailboxNameReportRequest' => $mailboxNameReportRequest ?? '',
            'mailboxNameArbitrationRequest' => $mailboxNameArbitrationRequest ?? '',
            'buttonNameReportRequest' => $buttonNameReportRequest ?? $this->translator->trans('profile.report.oldReportButton'),
            'maySeeQuizSessions' => $this->profilePermissions->maySeeQuizSessions()
        ];
    }

    /**
     * @throws Exception
     */
    private function renderMediationRequest(array $userArray): string
    {
        if (($userArray['rolle'] < Role::FOODSAVER->value) || ($userArray['id'] === $this->session->id())) {
            return '';
        }
        $regionId = $userArray['bezirk_id'];

        $mailboxName = '';
        $mediationGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId($regionId, WorkgroupFunction::MEDIATION);
        if ($mediationGroupId !== null) {
            $mailboxName = $this->groupGateway->getGroupMailName($mediationGroupId) ?? '';
        }

        return $mailboxName;
    }

    private function getHomeDistrictHistory(int $userId): array
    {
        $history = [];

        if ($this->profilePermissions->maySeeHistory($userId)) {
            $entry = $this->profileGateway->getHomeRegionHistory($userId);
            if (!empty($entry)) {
                $history['changerId'] = $entry['changer_id'];
                $history['changerFullName'] = $entry['changer_full_name'];
                $history['date'] = $entry['date'];
                $history['previousRegionId'] = intval($entry['old_region']);
                $history['previousRegionName'] = $entry['old_region_name'];
            }
        }

        return $history;
    }

    private function renderStatistics($userArray): array
    {
        $statistics = [
            'fetchWeight' => $userArray['stat_fetchweight'],
            'fetchCount' => $userArray['stat_fetchcount'],
            'basketCount' => $userArray['basketCount'],
            'buddyCount' => $userArray['stat_buddycount'],
        ];

        if ($this->session->mayRole(Role::FOODSAVER)) {
            $statistics['postCount'] = $userArray['stat_postcount'];
        }

        return $statistics;
    }

    private function getSleepingHatInformation(array $userArray): array
    {
        return [
            'sleepStatus' => $userArray['sleep_status'] ?? null,
            'sleepFrom' => $userArray['sleep_from_ts'] ?? null,
            'sleepUntil' => $userArray['sleep_until_ts'] ?? null,
            'sleepMessage' => $userArray['sleep_msg'] ?? null,
        ];
    }

    private function getProfileInfos($userArray): array
    {
        $userId = $userArray['id'];
        $maySeeLastActivity = $this->profilePermissions->maySeelastActivity($userId);
        $formattedLastActivity = ($userArray['last_activity'] !== '0000-00-00 00:00:00')
            ? Carbon::parse($userArray['last_activity'])->format('d.m.Y')
            : null;

        $fsMail = ($userArray['rolle'] > Role::FOODSAVER->value && $this->profilePermissions->maySeeEmailAddress($userId)) ? ($userArray['mailbox'] ?? '') : '';
        $homeRegionName = $userArray['bezirk_id'] !== null ? $this->regionGateway->getRegionName($userArray['bezirk_id']) : null;

        return [
            'role' => $userArray['rolle'],
            'fsMail' => $fsMail,
            'privateMail' => $this->profilePermissions->maySeePrivateEmail($userId) ? $userArray['email'] : '',
            'registrationDate' => $this->profilePermissions->maySeeRegistrationDate($userId) ? Carbon::parse($userArray['anmeldedatum'])->format('d.m.Y') : '',
            'maySeeLastActivity' => $maySeeLastActivity,
            'lastActivity' => $maySeeLastActivity ? $formattedLastActivity : '',
            'buddyCount' => $userArray['stat_buddycount'],
            'name' => $userArray['name'],
            'fsId' => $userArray['id'],
            'fsIdSession' => $this->session->id(),
            'homeRegionId' => $userArray['bezirk_id'],
            'homeRegionName' => $homeRegionName,
            'isVerified' => (bool)$userArray['verified'],
        ];
    }

    private function getBounceWarning($userArray): array
    {
        $userId = $userArray['id'];
        $maySeeBounceWarning = $this->profilePermissions->maySeeBounceWarning($userId);

        if (!($maySeeBounceWarning && $userArray['emailIsBouncing'])) {
            return [];
        }

        $mayRemove = $this->profilePermissions->mayRemoveFromBounceList($userId);

        return [
            'userId' => $userId,
            'emailAddress' => $userArray['email'],
            'mayRemove' => $mayRemove,
            'bounceEvents' => $mayRemove ? $userArray['emailBounceCategories'] : []
        ];
    }

    private function getPickupsSection(int $fsId): array
    {
        $maySeePickups = $this->profilePermissions->maySeePickups($fsId);

        return [
            'showRegisteredTab' => $maySeePickups,
            'showOptionsTab' => $this->storePermissions->maySeePickupOptions() && $this->session->id() === $fsId,
            'showHistoryTab' => $maySeePickups,
            'fsId' => $fsId,
            'allowSlotCancelation' => $this->profilePermissions->mayCancelSlotsFromProfile($fsId),
            'isOwnProfile' => ($fsId === $this->session->id()),
        ];
    }

    private function getAchievementsData(int $userId): ?array
    {
        $achievements = null;
        if ($this->achievementPermissions->maySeeUserAchievements($userId)) {
            $achievements = $this->achievementGateway->getAwardedAchievementsForUser($userId);
        }

        return $achievements;
    }

    private function getKamData(int $userId): ?array
    {
        $kamPositions = null;
        if ($this->storeChainPermissions->maySeeKamPositions()) {
            $kamPositions = $this->storeChainGateway->getStoreChainsManagedByFoodsaver($userId);
        }

        return $kamPositions;
    }
}
