<?php

namespace Foodsharing\Modules\Profile;

use Carbon\Carbon;
use Exception;
use Foodsharing\Lib\FoodsharingController;
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
use Foodsharing\Permissions\ProfilePermissions;
use Foodsharing\Permissions\ReportPermissions;
use Foodsharing\Permissions\StorePermissions;
use Foodsharing\Utility\DataHelper;
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
        private readonly DataHelper $dataHelper,
        private readonly StorePermissions $storePermissions
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
        $userStores = $maySeeStores ? $this->profileGateway->listStoresOfFoodsaver($userId) : [];
        $userArray = $this->createUserArray($userId);
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
        $viewerId = $this->session->id() ?? -1;
        $userArray = $this->profileGateway->getData($userId, $viewerId, $this->reportPermissions->mayHandleReports());

        $isRemoved = (!$userArray) || isset($userArray['deleted_at']);
        if ($isRemoved) {
            $this->flashMessageHelper->error($this->translator->trans('profile.notFound'));
            $this->routeHelper->goPageAndExit('dashboard');
        }

        $userArray['buddy'] = $this->profileGateway->buddyStatus($userId, $viewerId);
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
            'bananaStatistics' => $this->renderBananaStatistics($userArray),
            'ambassadorRegions' => $userArray['botschafter'] ? $userArray['botschafter'] : [],
            'foodSaverRegions' => $userArray['foodsaver'] ? $userArray['foodsaver'] : [],
            'homeDistrictHistory' => (object)$this->getHomeDistrictHistory($userArray),
            'aboutMeIntern' => $userArray['about_me_intern'] ?? '',
            'workingGroupsAdmins' => $userArray['orga'] ? $userArray['orga'] : [],
            'workingGroups' => $userArray['working_groups'] ?? [],
            'sleepingInformation' => $this->getSleepingHatInformation($userArray),
            'profileInfos' => $this->getProfileInfos($userArray),
            'profileCommitmentsStat' => $this->getProfileCommitmentsStat($userArray['id']),
            'bounceWarning' => (object)$this->getBounceWarning($userArray),
            'pickupsSection' => $this->getPickupsSection($userArray['id']),
            'maySeeUserNotes' => $this->profilePermissions->maySeeUserNotes($userArray['id']),
            'noteCount' => $userArray['note_count'] ?? 0,
            'stores' => $userStores,
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
        if ($userArray['rolle'] > Role::FOODSHARER->value) {
            // MediationRequest
            if ($this->regionGateway->getRegionOption($regionId, RegionOptionType::ENABLE_MEDIATION_BUTTON)) {
                $mediationGroupEmail = $this->renderMediationRequest($userArray);
            }

            // ReportRequest
            $isReportButtonEnabled = intval(
                $this->regionGateway->getRegionOption($regionId, RegionOptionType::ENABLE_REPORT_BUTTON)
            ) === 1;

            if ($this->regionGateway->getRegionOption($regionId, RegionOptionType::ENABLE_REPORT_BUTTON)) {
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

                $hasReportGroup = $this->groupFunctionGateway->existRegionFunctionGroup(
                    $regionId,
                    WorkgroupFunction::REPORT
                );
                $reporterHasReportGroup = $hasReportGroup;

                if ($hasReportGroup) {
                    $reportGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId(
                        $regionId,
                        WorkgroupFunction::REPORT
                    );
                    $reportGroupDetails = $this->groupGateway->getGroupLegacy($reportGroupId);
                    $MailboxNameReportRequest = $this->mailboxGateway->getMailboxname(
                        $reportGroupDetails['mailbox_id']
                    ) ?? '';
                }

                $hasArbitrationGroup = $this->groupFunctionGateway->existRegionFunctionGroup(
                    $regionId,
                    WorkgroupFunction::ARBITRATION
                );

                if ($regionId != $this->session->getCurrentRegionId()) {
                    $reporterHasReportGroup = $this->groupFunctionGateway->existRegionFunctionGroup(
                        $this->session->getCurrentRegionId(),
                        WorkgroupFunction::REPORT
                    );
                }

                $buttonNameReportRequest = $this->translator->trans('profile.reportRequest');
            }
        }

        return [
            'isOnline' => $userArray['online'],
            'foodSaverName' => $userArray['name'],
            'photo' => $userArray['photo'],
            'fsId' => $userArray['id'],
            'fsIdSession' => $this->session->id(),
            'isSleeping' => $this->dataHelper->parseSleepingState($userArray['sleep_status'], $userArray['sleep_from'], $userArray['sleep_until']),
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
            'reporterHasReportGroup' => $reporterHasReportGroup ?? false,
            'mailboxNameReportRequest' => $MailboxNameReportRequest ?? '',
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
        if ($this->groupFunctionGateway->existRegionFunctionGroup($regionId, WorkgroupFunction::MEDIATION)) {
            $mediationGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId($regionId, WorkgroupFunction::MEDIATION);
            $mediationGroupDetails = $this->groupGateway->getGroupLegacy($mediationGroupId);
            $mailboxName = $this->mailboxGateway->getMailboxname($mediationGroupDetails['mailbox_id']);
        }

        return $mailboxName;
    }

    private function getHomeDistrictHistory($userArray): array
    {
        $history = [];

        if ($this->profilePermissions->maySeeHistory($this->session->id()) && !empty($userArray['home_district_history'])) {
            $history['homeDistrictHistoryChangerId'] = $userArray['home_district_history']['changer_id'];
            $history['homeDistrictHistoryChangerFullName'] = $userArray['home_district_history']['changer_full_name'];
            $history['homeDistrictHistoryDate'] = $userArray['home_district_history']['date'];
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

    private function renderBananaStatistics($userArray): array
    {
        if (!$this->session->mayRole(Role::FOODSAVER)) {
            return [];
        }

        $recipientId = intval($userArray['id']);
        $viewerId = $this->session->id();

        $canGiveBanana = (!$userArray['bouched']) && ($userArray['id'] != $viewerId);

        return [
            'recipientId' => $recipientId,
            'recipientName' => $userArray['name'],
            'canGiveBanana' => $canGiveBanana,
            'canRemoveBanana' => $this->profilePermissions->mayDeleteBanana($recipientId),
            'bananas' => $userArray['bananen']
        ];
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
            'homeRegionName' => $homeRegionName
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
            'showOptionsTab' => $this->storePermissions->maySeePickupOptions($fsId),
            'showHistoryTab' => $maySeePickups,
            'fsId' => $fsId,
            'allowSlotCancelation' => $this->profilePermissions->mayCancelSlotsFromProfile($fsId),
            'isOwnProfile' => ($fsId === $this->session->id()),
        ];
    }
}
