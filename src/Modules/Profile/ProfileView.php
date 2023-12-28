<?php

namespace Foodsharing\Modules\Profile;

use Carbon\Carbon;
use Foodsharing\Lib\Session;
use Foodsharing\Lib\View\Utils;
use Foodsharing\Lib\View\vPage;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\RegionOptionType;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Core\View;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Group\GroupGateway;
use Foodsharing\Modules\Mailbox\MailboxGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Permissions\ProfilePermissions;
use Foodsharing\Permissions\ReportPermissions;
use Foodsharing\Permissions\StorePermissions;
use Foodsharing\Utility\DataHelper;
use Foodsharing\Utility\IdentificationHelper;
use Foodsharing\Utility\ImageHelper;
use Foodsharing\Utility\NumberHelper;
use Foodsharing\Utility\PageHelper;
use Foodsharing\Utility\RouteHelper;
use Foodsharing\Utility\Sanitizer;
use Foodsharing\Utility\TimeHelper;
use Foodsharing\Utility\TranslationHelper;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProfileView extends View
{
    private array $foodsaver;
    private ProfilePermissions $profilePermissions;
    private StorePermissions $storePermissions;
    private ReportPermissions $reportPermissions;
    private RegionGateway $regionGateway;
    private MailboxGateway $mailboxGateway;
    private GroupFunctionGateway $groupFunctionGateway;
    private GroupGateway $groupGateway;

    public function __construct(
        \Twig\Environment $twig,
        Session $session,
        Utils $viewUtils,
        ProfilePermissions $profilePermissions,
        StorePermissions $storePermissions,
        ReportPermissions $reportPermissions,
        DataHelper $dataHelper,
        IdentificationHelper $identificationHelper,
        ImageHelper $imageService,
        NumberHelper $numberHelper,
        PageHelper $pageHelper,
        RouteHelper $routeHelper,
        Sanitizer $sanitizerService,
        TimeHelper $timeHelper,
        TranslationHelper $translationHelper,
        TranslatorInterface $translator,
        RegionGateway $regionGateway,
        MailboxGateway $mailboxGateway,
        GroupFunctionGateway $groupFunctionGateway,
        GroupGateway $groupGateway,
        private readonly StoreGateway $storeGateway
    ) {
        parent::__construct(
            $twig,
            $session,
            $viewUtils,
            $dataHelper,
            $identificationHelper,
            $imageService,
            $numberHelper,
            $pageHelper,
            $routeHelper,
            $sanitizerService,
            $timeHelper,
            $translationHelper,
            $translator
        );

        $this->mailboxGateway = $mailboxGateway;
        $this->regionGateway = $regionGateway;
        $this->profilePermissions = $profilePermissions;
        $this->storePermissions = $storePermissions;
        $this->reportPermissions = $reportPermissions;
        $this->groupFunctionGateway = $groupFunctionGateway;
        $this->groupGateway = $groupGateway;
        $this->imageService = $imageService;
    }

    public function profile(string $wallPosts, array $userStores = [], array $commitmentsStats = []): void
    {
        $page = new vPage($this->foodsaver['name'], $this->infos());
        $fsId = $this->foodsaver['id'];
        $regionId = $this->foodsaver['bezirk_id'];

        // what is the viewer allowed to do in this profile?
        $maySeeHistory = $this->profilePermissions->maySeeHistory($fsId);
        $mayAdmin = $this->profilePermissions->mayAdministrateUserProfile($fsId, $regionId);
        $maySeeBounceWarning = $this->profilePermissions->maySeeBounceWarning($fsId);
        $maySeePickups = $this->profilePermissions->maySeePickups($fsId);
        $maySeeStores = $this->profilePermissions->maySeeStores($fsId);
        $maySeeCommitmentsStat = $this->profilePermissions->maySeeCommitmentsStat($fsId);

        if ($this->foodsaver['rolle'] > Role::FOODSHARER) {
            // MediationRequest
            if ($this->regionGateway->getRegionOption($regionId, RegionOptionType::ENABLE_MEDIATION_BUTTON)) {
                $mediationGroupEmail = $this->renderMediationRequest($regionId);
            }

            // ReportRequest
            $isReportButtonEnabled = intval($this->regionGateway->getRegionOption($regionId, RegionOptionType::ENABLE_REPORT_BUTTON)) === 1;

            if ($this->regionGateway->getRegionOption($regionId, RegionOptionType::ENABLE_REPORT_BUTTON)) {
                // if the current user is not allowed to see all stores of the profile, the report dialog will only show stores in which both users are
                if ($maySeeStores) {
                    $reportStores = $userStores;
                } else {
                    $myStores = $this->storeGateway->listMyStores($this->session->id());
                    $myStoreIds = array_column($myStores, 'id');
                    $reportStores = array_filter($userStores, function ($store) use ($myStoreIds) {
                        return in_array($store['id'], $myStoreIds);
                    });
                }

                $storeListOptions = [['value' => null, 'text' => $this->translator->trans('profile.choosestore')]];
                foreach ($reportStores as $store) {
                    $storeListOptions[] = ['value' => $store['id'], 'text' => $store['name']];
                }
                $isReportedIdReportAdmin = $this->groupFunctionGateway->isRegionFunctionGroupAdmin($regionId, WorkgroupFunction::REPORT, $this->foodsaver['id']);
                $isReporterIdReportAdmin = $this->groupFunctionGateway->isRegionFunctionGroupAdmin($regionId, WorkgroupFunction::REPORT, $this->session->id());
                $isReportedIdArbitrationAdmin = $this->groupFunctionGateway->isRegionFunctionGroupAdmin($regionId, WorkgroupFunction::ARBITRATION, $this->foodsaver['id']);
                $isReporterIdArbitrationAdmin = $this->groupFunctionGateway->isRegionFunctionGroupAdmin($regionId, WorkgroupFunction::ARBITRATION, $this->session->id());

                $hasReportGroup = $this->groupFunctionGateway->existRegionFunctionGroup($regionId, WorkgroupFunction::REPORT);
                $reporterHasReportGroup = $hasReportGroup;

                if ($hasReportGroup) {
                    $reportGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId($regionId, WorkgroupFunction::REPORT);
                    $reportGroupDetails = $this->groupGateway->getGroupLegacy($reportGroupId);
                    $MailboxNameReportRequest = $this->mailboxGateway->getMailboxname($reportGroupDetails['mailbox_id']) ?? '';
                }

                $hasArbitrationGroup = $this->groupFunctionGateway->existRegionFunctionGroup($regionId, WorkgroupFunction::ARBITRATION);

                if ($regionId != $this->session->getCurrentRegionId()) {
                    $reporterHasReportGroup = $this->groupFunctionGateway->existRegionFunctionGroup($this->session->getCurrentRegionId(), WorkgroupFunction::REPORT);
                }

                $buttonNameReportRequest = $this->translator->trans('profile.reportRequest');
            }
        }

        $page->addSectionLeft(
            $this->vueComponent('vue-profile-menu', 'ProfileMenu', [
                'isOnline' => $this->foodsaver['online'],
                'foodSaverName' => $this->foodsaver['name'],
                'photo' => $this->foodsaver['photo'],
                'fsId' => $this->foodsaver['id'],
                'fsIdSession' => $this->session->id(),
                'isSleeping' => $this->dataHelper->parseSleepingState($this->foodsaver['sleep_status'], $this->foodsaver['sleep_from'], $this->foodsaver['sleep_until']),
                'initialBuddyType' => $this->foodsaver['buddy'],
                'mayAdmin' => $mayAdmin,
                'mayHistory' => $maySeeHistory,
                'noteCount' => $this->foodsaver['note_count'] ?? 0,
                'mayNotes' => $this->reportPermissions->mayHandleReports(),
                'violationCount' => $this->foodsaver['violation_count'] ?? 0,
                'mayViolation' => $this->reportPermissions->mayHandleReports(),
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
                'buttonNameReportRequest' => $buttonNameReportRequest ?? $this->translator->trans('profile.report.oldReportButton')
            ])
        );

        if ($maySeeBounceWarning && $this->foodsaver['emailIsBouncing']) {
            $mayRemove = $this->profilePermissions->mayRemoveFromBounceList($this->foodsaver['id']);
            $page->addSection($this->vueComponent('email-bounce-list', 'EmailBounceList', [
                'userId' => $this->foodsaver['id'],
                'emailAddress' => $this->foodsaver['email'],
                'mayRemove' => $mayRemove,
                'bounceEvents' => $mayRemove ? $this->foodsaver['emailBounceCategories'] : []
            ]));
        }

        if ($maySeePickups) {
            $page->addSection(
                $this->vueComponent('pickups-section', 'PickupsSection', [
                    'showRegisteredTab' => $maySeePickups,
                    'showOptionsTab' => $this->storePermissions->maySeePickupOptions($fsId),
                    'showHistoryTab' => $maySeePickups,
                    'fsId' => $fsId,
                    'allowSlotCancelation' => $this->profilePermissions->mayCancelSlotsFromProfile($fsId),
                    'isOwnProfile' => ($fsId === $this->session->id()),
                ]),
            );
        }

        if ($maySeeCommitmentsStat && $commitmentsStats) {
            $page->addSection($this->vueComponent('profile-commitments-stat', 'ProfileCommitmentsStat', [
                'commitmentsStats' => $commitmentsStats,
            ]),
                $this->translator->trans('profile.commitments_stat.title')
            );
        }

        $wallTitle = $this->translator->trans('profile.pinboard', ['{name}' => $this->foodsaver['name']]);
        $page->addSection($wallPosts, $wallTitle);

        if ($this->session->id() != $fsId) {
            $this->pageHelper->addStyle('#wallposts .tools {display:none;}');
        }

        $fsMail = '';
        if ($this->foodsaver['rolle'] > Role::FOODSAVER) {
            if ($this->profilePermissions->maySeeEmailAddress($fsId)) {
                $fsMail = $this->foodsaver['mailbox'] ?? '';
            }
        }

        $formattedLastActivity = ($this->foodsaver['last_activity'] !== '0000-00-00 00:00:00')
            ? Carbon::parse($this->foodsaver['last_activity'])->format('d.m.Y')
            : null;
        $maySeeLastActivity = $this->profilePermissions->maySeelastActivity($fsId);

        $page->addSectionLeft(
            $this->vueComponent('vue-profile-infos', 'ProfileInfos', [
                'isfoodsaver' => $this->foodsaver['rolle'] > Role::FOODSHARER,
                'fsMail' => $fsMail,
                'privateMail' => $this->profilePermissions->maySeePrivateEmail($fsId) ? $this->foodsaver['email'] : '',
                'registrationDate' => $this->profilePermissions->maySeeRegistrationDate($fsId) ? Carbon::parse($this->foodsaver['anmeldedatum'])->format('d.m.Y') : '',
                'maySeeLastActivity' => $maySeeLastActivity,
                'lastActivity' => $maySeeLastActivity ? $formattedLastActivity : '',
                'buddyCount' => $this->foodsaver['stat_buddycount'],
                'name' => $this->foodsaver['name'],
                'fsId' => $this->foodsaver['id'],
                'fsIdSession' => $this->session->id()
            ]),
            $this->translator->trans('profile.infos.title')
        );

        if ($maySeeStores && count($userStores) > 0) {
            $page->addSectionLeft(
                $this->vueComponent('vue-profile-storelist', 'ProfileStoreList', [
                    'stores' => $userStores,
                ])
            );
        }

        $page->render();
    }

    private function infos(): string
    {
        $infos = $this->renderInformation();
        $stats = join('', $this->renderStatistics());
        $bananas = $this->renderBananas();

        return '
			<div>
				<div class="profile statdisplay">
					' . $stats . '
					' . $bananas . '
				</div>
			    <div class="infos"> ' . $infos . ' </div>
			</div>';
    }

    public function userNotes(string $notes, array $userStores): void
    {
        $fsName = $this->foodsaver['name'];

        $page = new vPage(
            $this->translator->trans('profile.notes.title', ['{name}' => $fsName]),
            $this->v_utils->v_info($this->translator->trans('profile.notes.info')) . $notes
        );
        $page->setBread($this->translator->trans('profile.notes.bread'));

        $page->addSectionLeft('<a href="#"><img src="' . $this->imageService->img($this->foodsaver['photo'], 130) . '" /></a>');

        $page->render();
    }

    public function setData(array $data): void
    {
        $this->foodsaver = $data;
    }

    private function renderStatistics(): array
    {
        $stats = [];
        if (($fetchWeight = $this->foodsaver['stat_fetchweight']) > 0) {
            $label = $this->translator->trans('profile.stats.weight');
            $stats[] = $this->renderStat($fetchWeight, 'kg', $label, 'stat_fetchweight');
        }

        if (($fetchCount = $this->foodsaver['stat_fetchcount']) > 0) {
            $label = $this->translator->trans('profile.stats.count');
            $stats[] = $this->renderStat($fetchCount, 'x', $label, 'stat_fetchcount');
        }

        if (($basketCount = $this->foodsaver['basketCount']) > 0) {
            $label = $this->translator->trans('profile.stats.baskets');
            $stats[] = '<a href="/essenskoerbe">'
                . $this->renderStat($basketCount, '', $label, 'stat_basketcount')
                . '</a>';
        }

        if ($this->session->mayRole(Role::FOODSAVER)) {
            $label = $this->translator->trans('profile.stats.posts');
            $stats[] = $this->renderStat($this->foodsaver['stat_postcount'], '', $label, 'stat_postcount');
        }

        return $stats;
    }

    private function renderStat($number, string $suffix, string $label, string $class): string
    {
        return '<span class="item ' . $class . '">'
            . '<span class="val">' . $this->numberHelper->format_number($number)
            . ($suffix ? '<span style="white-space:nowrap">&thinsp;</span>' . $suffix : '')
            . '</span>
			<span class="name">' . $label . '</span>
		</span>';
    }

    private function renderBananas(): string
    {
        if (!$this->session->mayRole(Role::FOODSAVER)) {
            return '';
        }

        $this->pageHelper->addJs('
			$(".stat_bananacount").fancybox({
				closeClick: false,
				closeBtn: true,
			});
		');

        $canGiveBanana = (!$this->foodsaver['bouched']) && ($this->foodsaver['id'] != $this->session->id());

        $this->pageHelper->addHidden(
            $this->vueComponent('vue-profile-bananalist', 'BananaList', [
                'recipientId' => intval($this->foodsaver['id']),
                'recipientName' => $this->foodsaver['name'],
                'canGiveBanana' => $canGiveBanana,
                'canRemoveBanana' => $this->profilePermissions->mayDeleteBanana($this->foodsaver['id']),
                'bananas' => $this->foodsaver['bananen'],
            ])
        );

        $buttonClass = $canGiveBanana ? '' : ' bouched';
        $bananaCount = count($this->foodsaver['bananen']) ?: '&nbsp;';

        return '
			<a href="#bananas" onclick="return false;" class="item stat_bananacount' . $buttonClass . '">
				<span class="val">' . $bananaCount . '</span>
				<span class="name">&nbsp;</span>
			</a>
		';
    }

    private function renderMediationRequest(int $regionId): string
    {
        if (($this->foodsaver['rolle'] < Role::FOODSAVER) || ($this->foodsaver['id'] === $this->session->id())) {
            return '';
        }

        $mailboxName = '';
        if ($this->groupFunctionGateway->existRegionFunctionGroup($regionId, WorkgroupFunction::MEDIATION)) {
            $mediationGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId($regionId, WorkgroupFunction::MEDIATION);
            $mediationGroupDetails = $this->groupGateway->getGroupLegacy($mediationGroupId);
            $mailboxName = $this->mailboxGateway->getMailboxname($mediationGroupDetails['mailbox_id']);
        }

        return $mailboxName;
    }

    private function renderInformation(): string
    {
        $infos = [];
        [$ambassador, $infos] = $this->renderAmbassadorInformation($infos);
        $infos = $this->renderFoodsaverInformation($ambassador, $infos);
        $infos = $this->renderOrgaTeamMemberInformation($infos);
        if (
            $this->foodsaver['id'] != $this->session->id()
            && $this->foodsaver['rolle'] > Role::FOODSHARER
            && $this->session->mayRole(Role::FOODSAVER)
        ) {
            $infos = $this->renderFoodsaverTeamMemberInformation($infos);
        }
        $infos = $this->renderOrgaUserInformation($infos);
        $infos = $this->renderSleepingHatInformation($infos);
        $infos = $this->renderAboutMeInternalInformation($infos);

        $out = '<dl class="profile-infos profile-main">';
        foreach ($infos as $info) {
            $out .= '<dt>' . $info['name'];
            if (!empty($info['val'])) {
                $out .= ':';
            }
            $out .= '</dt>';

            if (!empty($info['val'])) {
                $out .= '<dd>' . $info['val'] . '</dd>';
            }
        }
        $out .= '</dl>';

        return $out;
    }

    private function renderAmbassadorInformation(array $infos): array
    {
        $ambassador = [];
        if ($this->foodsaver['botschafter']) {
            foreach ($this->foodsaver['botschafter'] as $foodsaver) {
                $ambassador[$foodsaver['id']] = '<a class="light" href="/region?bid=' . $foodsaver['id'] . '&sub=forum">' . $foodsaver['name'] . '</a>';
            }
            $infos[] = [
                'name' => $this->translator->trans('profile.ambRegions', [
                    '{name}' => $this->foodsaver['name'],
                    '{role}' => $this->translationHelper->genderWord(
                        $this->foodsaver['geschlecht'],
                        $this->translator->trans('terminology.ambassador.m'),
                        $this->translator->trans('terminology.ambassador.f'),
                        $this->translator->trans('terminology.ambassador.d')
                    ),
                ]),
                'val' => implode(', ', $ambassador),
            ];
        }

        return [$ambassador, $infos];
    }

    private function renderFoodsaverInformation(array $ambassador, array $infos): array
    {
        if ($this->foodsaver['foodsaver']) {
            $fsa = [];
            $fsHomeDistrict = '';
            foreach ($this->foodsaver['foodsaver'] as $foodsaver) {
                if ($foodsaver['id'] == $this->foodsaver['bezirk_id']) {
                    $fsHomeDistrict = '<a class="light" href="/region?bid=' . $foodsaver['id'] . '&sub=forum">' . $foodsaver['name'] . '</a>';
                    if ($this->profilePermissions->maySeeHistory($this->foodsaver['id']) && !empty($this->foodsaver['home_district_history'])) {
                        $fsHomeDistrict .= ' (<a class="light" href="/profile/' . $this->foodsaver['home_district_history']['changer_id'] . '">' . $this->foodsaver['home_district_history']['changer_full_name'] . '</a> ';
                        $fsHomeDistrict .= Carbon::parse($this->foodsaver['home_district_history']['date'])->format('d.m.Y H:i:s') . ')';
                    }
                }
                if (!isset($ambassador[$foodsaver['id']])) {
                    $fsa[] = '<a class="light" href="/region?bid=' . $foodsaver['id'] . '&sub=forum">' . $foodsaver['name'] . '</a>';
                }
            }
            if (!empty($fsa)) {
                $infos[] = [
                    'name' => $this->translator->trans('profile.regions', ['{name}' => $this->foodsaver['name']]),
                    'val' => implode(', ', $fsa),
                ];
            }
            if (!empty($fsHomeDistrict)) {
                $infos[] = [
                    'name' => $this->translator->trans('profile.homeRegion', ['{name}' => $this->foodsaver['name']]),
                    'val' => $fsHomeDistrict,
                ];
            }
        }

        return $infos;
    }

    private function renderAboutMeInternalInformation(array $infos): array
    {
        if ($this->foodsaver['about_me_intern']) {
            $infos[] = [
                'name' => $this->translator->trans('profile.about_me_intern'),
                'val' => $this->foodsaver['about_me_intern'],
            ];
        }

        return $infos;
    }

    private function renderOrgaTeamMemberInformation(array $infos): array
    {
        if ($this->foodsaver['orga']) {
            $ambassador = [];
            foreach ($this->foodsaver['orga'] as $foodsaver) {
                if ($this->session->mayRole(Role::FOODSAVER)) {
                    $ambassador[$foodsaver['id']] = '<a class="light" href="/region?bid=' . $foodsaver['id'] . '&sub=forum">' . $foodsaver['name'] . '</a>';
                } else {
                    $ambassador[$foodsaver['id']] = $foodsaver['name'];
                }
            }
            $infos[] = [
                'name' => $this->translator->trans('profile.workgroups_admin', ['{name}' => $this->foodsaver['name']]),
                'val' => implode(', ', $ambassador),
            ];
        }

        return $infos;
    }

    private function renderOrgaUserInformation(array $infos): array
    {
        if ($this->foodsaver['rolle'] >= Role::ORGA) {
            $infos[] = [
                'name' => $this->translator->trans('profile.orga_rights', [
                    '{name}' => $this->foodsaver['name'],
                    '{wikiurl}' => 'https://wiki.foodsharing.de/Benutzerrolle_:_Orga',
                ]),
                'val' => '',
            ];
        }

        return $infos;
    }

    private function renderFoodsaverTeamMemberInformation(array $infos): array
    {
        // only display groups in which the user is not an admin
        if (!empty($this->foodsaver['working_groups'])) {
            $groups = $this->foodsaver['working_groups'];
            $groupInfos = [];
            foreach ($groups as $group) {
                $groupInfos[$group['id']] = $group['name'];
            }

            $infos[] = [
                'name' => $this->translator->trans('profile.workgroups_member', ['{name}' => $this->foodsaver['name']]),
                'val' => implode(', ', $groupInfos),
            ];
        } else {
            $infos[] = [
                'name' => $this->translator->trans('profile.no_common_workgroups', ['{name}' => $this->foodsaver['name']]),
                'val' => null,
            ];
        }

        return $infos;
    }

    private function renderSleepingHatInformation(array $infos): array
    {
        switch ($this->foodsaver['sleep_status']) {
            case 1:
                $infos[] = [
                    'name' => $this->translator->trans('profile.sleepinfo', [
                        '{name}' => $this->foodsaver['name'],
                        '{from}' => date('d.m.Y', $this->foodsaver['sleep_from_ts']),
                        '{until}' => date('d.m.Y', $this->foodsaver['sleep_until_ts']),
                    ]),
                    'val' => $this->foodsaver['sleep_msg'],
                ];
                break;
            case 2:
                $infos[] = [
                    'name' => $this->translator->trans('profile.sleeping', ['{name}' => $this->foodsaver['name']]),
                    'val' => $this->foodsaver['sleep_msg'],
                ];
                break;
            default:
                break;
        }

        return $infos;
    }
}
