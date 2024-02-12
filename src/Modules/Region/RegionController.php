<?php

namespace Foodsharing\Modules\Region;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Map\MapConstants;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Region\RegionOptionType;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Core\View;
use Foodsharing\Modules\Event\EventGateway;
use Foodsharing\Modules\Mailbox\MailboxGateway;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Modules\Voting\VotingGateway;
use Foodsharing\Permissions\ForumPermissions;
use Foodsharing\Permissions\RegionPermissions;
use Foodsharing\Permissions\ReportPermissions;
use Foodsharing\Permissions\VotingPermissions;
use Foodsharing\Permissions\WorkGroupPermissions;
use Foodsharing\Utility\DataHelper;
use Foodsharing\Utility\ImageHelper;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Service\Attribute\Required;

final class RegionController extends FoodsharingController
{
    private array $region;
    private FormFactoryInterface $formFactory;

    private const DisplayAvatarListEntries = 30;

    #[Required]
    public function setFormFactory(FormFactoryInterface $formFactory)
    {
        $this->formFactory = $formFactory;
    }

    public function __construct(
        private readonly EventGateway $eventGateway,
        private readonly ForumGateway $forumGateway,
        private readonly ForumFollowerGateway $forumFollowerGateway,
        private readonly ForumPermissions $forumPermissions,
        private readonly RegionPermissions $regionPermissions,
        private readonly ForumTransactions $forumTransactions,
        private readonly RegionGateway $gateway,
        private readonly ReportPermissions $reportPermissions,
        private readonly ImageHelper $imageService,
        private readonly MailboxGateway $mailboxGateway,
        private readonly VotingGateway $votingGateway,
        private readonly VotingPermissions $votingPermissions,
        private readonly WorkGroupPermissions $workGroupPermissions,
        private readonly StoreGateway $storeGateway,
        private readonly DataHelper $dataHelper,
        private readonly View $view
    ) {
        parent::__construct();
    }

    private function mayAccessApplications(int $regionId): bool
    {
        return $this->forumPermissions->mayAccessAmbassadorBoard($regionId);
    }

    private function isHomeDistrict($region): bool
    {
        return (int)$region['id'] === $this->session->getCurrentRegionId();
    }

    private function regionViewData(array $region, ?string $activeSubpage): array
    {
        $isWorkGroup = UnitType::isGroup($region['type']);
        $regionId = (int)$region['id'];
        $isHomeDistrict = $this->isHomeDistrict($region);

        // The store and member pages are temporarily disabled for large regions because they cause an out-of-memory error
        $storesAndMembersDisabled = in_array($regionId, [RegionIDs::EUROPE, RegionIDs::GERMANY]);

        $menu = [
            'forum' => ['name' => 'terminology.forum', 'href' => '/region?bid=' . $regionId . '&sub=forum'],
            'events' => ['name' => 'terminology.events', 'href' => '/region?bid=' . $regionId . '&sub=events'],
            'polls' => ['name' => 'terminology.polls', 'href' => '/region?bid=' . $regionId . '&sub=polls'],
        ];
        if (!$storesAndMembersDisabled) {
            $menu['members'] = ['name' => 'group.members', 'href' => '/region?bid=' . $regionId . '&sub=members'];
        }

        if (!$isWorkGroup && $this->forumPermissions->mayAccessAmbassadorBoard($regionId)) {
            $menu['ambassador_forum'] = ['name' => 'terminology.ambassador_forum', 'href' => '/region?bid=' . $regionId . '&sub=botforum'];
        }

        if (!$isWorkGroup) {
            $menu['options'] = ['name' => 'terminology.options', 'href' => '/region?bid=' . $regionId . '&sub=options'];
        }

        if (!$isWorkGroup && $this->regionPermissions->maySetRegionPin($regionId)) {
            $menu['options'] = ['name' => 'terminology.pin', 'href' => '/region?bid=' . $regionId . '&sub=pin'];
        }

        if ($isWorkGroup) {
            $menu['wall'] = ['name' => 'menu.entry.wall', 'href' => '/region?bid=' . $regionId . '&sub=wall'];
            if ($region['has_children'] === 1) {
                $menu['subgroups'] = ['name' => 'terminology.subgroups', 'href' => '/?page=groups&p=' . $regionId];
            }
            if ($this->session->isAdminFor($regionId) || $this->session->mayRole(Role::ORGA)) {
                $menu['workingGroupEdit'] = ['name' => 'menu.entry.workingGroupEdit', 'href' => '/?page=groups&sub=edit&id=' . $regionId];
            }
        } else {
            $menu['fsp'] = ['name' => 'terminology.fsp', 'href' => '/region?bid=' . $regionId . '&sub=fairteiler'];
            $menu['groups'] = ['name' => 'terminology.groups', 'href' => '/?page=groups&p=' . $regionId];
            $menu['statistic'] = ['name' => 'terminology.statistic', 'href' => '/region?bid=' . $regionId . '&sub=statistic'];

            if (!$storesAndMembersDisabled) {
                $menu['stores'] = ['name' => 'menu.entry.stores', 'href' => '/?page=betrieb&bid=' . $regionId];
            }

            if ($this->session->isAdminFor($regionId)) {
                $menu['passports'] = ['name' => 'menu.entry.ids', 'href' => '/?page=passgen&bid=' . $regionId];
            }

            if ($this->reportPermissions->mayAccessReportGroupReports($regionId)) {
                $menu['reports'] = ['name' => 'terminology.reports', 'href' => '/?page=report&bid=' . $regionId];
            }
            if ($this->reportPermissions->mayAccessArbitrationReports($regionId)) {
                $menu['arbitration'] = ['name' => 'terminology.arbitration', 'href' => '/?page=report&bid=' . $regionId];
            }
        }

        if ($this->session->isAdminFor($regionId)) {
            $regionOrGroupString = $isWorkGroup ? $this->translator->trans('group.mail_link_title.workgroup') : $this->translator->trans('group.mail_link_title.region');
            if ($regionMailInfo = $this->mailboxGateway->getMailboxesWithUnreadCount([$region['mailbox_id']])) {
                $regionOrGroupString .= ' (' . $regionMailInfo[0]['count'] . ')';
            }

            $menu['mailbox'] = ['name' => $regionOrGroupString, 'href' => '/?page=mailbox&mailbox=' . $region['mailbox_id']];
        }

        if ($regionId == RegionIDs::STORE_CHAIN_GROUP) {
            $menu['chainList'] = ['name' => 'menu.entry.chainList', 'href' => '/?page=chain'];
        }

        if ($this->mayAccessApplications($regionId)) {
            if ($requests = $this->gateway->listApplicants($regionId)) {
                $menu['applications'] = ['name' => $this->translator->trans('group.applications') . ' (' . count($requests) . ')', 'href' => '/region?bid=' . $regionId . '&sub=applications'];
            }
        }

        $avatarListEntry = fn ($fs) => [
            'user' => [
                'id' => $fs['id'],
                'name' => $fs['name'],
                'sleep_status' => $this->dataHelper->parseSleepingState($fs['sleep_status'], $fs['sleep_from'], $fs['sleep_until'])
            ],
            'size' => 50,
            'imageUrl' => $this->imageService->img($fs['photo'], 50, 'q')
        ];

        $menu = $this->sortMenuItems($menu);

        $viewdata['isRegion'] = !$isWorkGroup;
        $stat = [
            'num_fs' => $this->region['fs_count'],
            'num_fs_home' => $this->region['fs_home_count'],
            'num_sleeping' => $this->region['sleeper_count'],
            'num_ambassadors' => $this->region['stat_botcount'],
            'num_stores' => $this->region['stat_betriebcount'],
            'num_cooperations' => $this->region['stat_korpcount'],
            'num_pickups' => $this->region['stat_fetchcount'],
            'pickup_weight_kg' => round($this->region['stat_fetchweight']),
        ];

        $viewdata['region'] = [
            'id' => $this->region['id'],
            'parent_id' => $this->region['parent_id'],
            'name' => $this->region['name'],
            'moderated' => $this->region['moderated'],
            'isWorkGroup' => $isWorkGroup,
            'isHomeDistrict' => $isHomeDistrict,
            'stat' => $stat,
            'admins' => array_map($avatarListEntry, array_slice($this->region['botschafter'], 0, self::DisplayAvatarListEntries)),
            'welcomeAdmins' => array_map($avatarListEntry, array_slice($this->region['welcomeAdmins'], 0, self::DisplayAvatarListEntries)),
            'votingAdmins' => array_map($avatarListEntry, array_slice($this->region['votingAdmins'], 0, self::DisplayAvatarListEntries)),
            'fspAdmins' => array_map($avatarListEntry, array_slice($this->region['fspAdmins'], 0, self::DisplayAvatarListEntries)),
            'storesAdmins' => array_map($avatarListEntry, array_slice($this->region['storesAdmins'], 0, self::DisplayAvatarListEntries)),
            'reportAdmins' => array_map($avatarListEntry, array_slice($this->region['reportAdmins'], 0, self::DisplayAvatarListEntries)),
            'mediationAdmins' => array_map($avatarListEntry, array_slice($this->region['mediationAdmins'], 0, self::DisplayAvatarListEntries)),
            'arbitrationAdmins' => array_map($avatarListEntry, array_slice($this->region['arbitrationAdmins'], 0, self::DisplayAvatarListEntries)),
            'fsManagementAdmins' => array_map($avatarListEntry, array_slice($this->region['fsManagementAdmins'], 0, self::DisplayAvatarListEntries)),
            'prAdmins' => array_map($avatarListEntry, array_slice($this->region['prAdmins'], 0, self::DisplayAvatarListEntries)),
            'moderationAdmins' => array_map($avatarListEntry, array_slice($this->region['moderationAdmins'], 0, self::DisplayAvatarListEntries)),
            'boardAdmins' => array_map($avatarListEntry, array_slice($this->region['boardAdmins'], 0, self::DisplayAvatarListEntries)),
            'electionAdmins' => array_map($avatarListEntry, array_slice($this->region['electionAdmins'], 0, self::DisplayAvatarListEntries)),
        ];
        $viewdata['nav'] = [
            'menu' => $menu,
            'active' => $activeSubpage ? ('=' . $activeSubpage) : null,
        ];

        return $viewdata;
    }

    private function sortMenuItems(array $menu): array
    {
        $menuOrderMaster = [
            ['key' => 'wall', 'position' => 0],
            ['key' => 'forum', 'position' => 1],
            ['key' => 'ambassador_forum', 'position' => 2],
            ['key' => 'stores', 'position' => 3],
            ['key' => 'groups', 'position' => 4],
            ['key' => 'events', 'position' => 5],
            ['key' => 'fsp', 'position' => 6],
            ['key' => 'conferences', 'position' => 7],
            ['key' => 'polls', 'position' => 8],
            ['key' => 'members', 'position' => 9],
            ['key' => 'statistic', 'position' => 10],
            ['key' => 'fsList', 'position' => 11],
            ['key' => 'passports', 'position' => 12],
            ['key' => 'mailbox', 'position' => 13],
            ['key' => 'workingGroupEdit', 'position' => 14],
            ['key' => 'reports', 'position' => 15],
            ['key' => 'applications', 'position' => 16],
            ['key' => 'arbitration', 'position' => 17],
            ['key' => 'subgroups', 'position' => 18],
            ['key' => 'options', 'position' => 19],
            ['key' => 'pin', 'position' => 20],
            ['key' => 'chainList', 'position' => 21],
        ];

        $orderedMenu = [];

        foreach ($menuOrderMaster as $value) {
            if (array_key_exists($value['key'], $menu)) {
                $orderedMenu[] = $menu[$value['key']];
            }
        }

        return $orderedMenu;
    }

    #[Route('/region')]
    public function index(Request $request): Response
    {
        if (!$this->session->mayRole()) {
            $this->routeHelper->goLoginAndExit();
        }

        $region_id = $request->query->getInt('bid', $this->session->getCurrentRegionId());

        if ($this->session->mayBezirk($region_id) && ($region = $this->gateway->getRegionDetails($region_id))) {
            $big = [UnitType::BIG_CITY, UnitType::FEDERAL_STATE, UnitType::COUNTRY];
            $region['moderated'] = $region['moderated'] || in_array($region['type'], $big);
            $this->region = $region;
        } else {
            $this->flashMessageHelper->error($this->translator->trans('region.not-member'));

            return $this->redirect('/?page=dashboard');
        }

        $this->pageHelper->addTitle($region['name']);
        $this->pageHelper->addBread($region['name'], '/region?bid=' . $region_id);

        switch ($request->query->get('sub')) {
            case 'botforum':
                if (!$this->forumPermissions->mayAccessAmbassadorBoard($region_id)) {
                    return $this->redirect($this->forumTransactions->url($region_id, false));
                }

                return $this->forum($request, $region, true);
            case 'forum':
                return $this->forum($request, $region, false);
            case 'wall':
                if (!UnitType::isGroup($region['type'])) {
                    $this->flashMessageHelper->info($this->translator->trans('region.forum-redirect'));

                    return $this->redirect('/region?bid=' . $region_id . '&sub=forum');
                } else {
                    return $this->wall($request, $region);
                }
                // no break
            case 'fairteiler':
                return $this->foodSharePoint($request, $region);
            case 'events':
                return $this->events($request, $region);
            case 'applications':
                return $this->applications($request, $region);
            case 'members':
                return $this->members($request, $region);
            case 'statistic':
                return $this->statistic($request, $region);
            case 'polls':
                return $this->polls($request, $region);
            case 'options':
                return $this->options($request, $region);
            case 'pin':
                if (!$this->regionPermissions->maySetRegionPin($region_id) || UnitType::isGroup($region['type'])) {
                    $this->flashMessageHelper->info($this->translator->trans('region.restricted'));

                    return $this->redirect($this->forumTransactions->url($region_id, false));
                }

                return $this->pin($request, $region);
            default:
                if (UnitType::isGroup($region['type'])) {
                    return $this->redirect('/region?bid=' . $region_id . '&sub=wall');
                } else {
                    return $this->redirect($this->forumTransactions->url($region_id, false));
                }
        }
    }

    private function wall(Request $request, array $region): Response
    {
        $this->pageHelper->addBread($this->translator->trans('terminology.wall'), '/region?bid=' . $region['id'] . '&sub=wall');
        $this->pageHelper->addContent($this->view->vueComponent('vue-wall', 'wall', [
            'target' => 'bezirk',
            'targetId' => $region['id'],
        ]));

        $viewdata = $this->regionViewData($region, $request->query->get('sub'));

        return $this->renderGlobal('pages/Region/baseRegion.twig', $viewdata);
    }

    private function foodSharePoint(Request $request, array $region): Response
    {
        $this->pageHelper->addBread($this->translator->trans('terminology.fsp'), '/region?bid=' . $region['id'] . '&sub=fairteiler');
        $this->pageHelper->addTitle($this->translator->trans('terminology.fsp'));
        $viewdata = $this->regionViewData($region, $request->query->get('sub'));

        return $this->renderGlobal('pages/Region/foodSharePoint.twig', $viewdata);
    }

    private function handleNewThreadForm(Request $request, array $region, $ambassadorForum, bool $postActiveWithoutModeration)
    {
        $this->pageHelper->addBread($this->translator->trans('forum.new_thread'));
        $data = CreateForumThreadData::create();
        $form = $this->formFactory->create(ForumCreateThreadForm::class, $data, ['postActiveWithoutModeration' => $postActiveWithoutModeration]);
        $form->handleRequest($request);
        if (
            $form->isSubmitted() && $form->isValid()
            && $this->forumPermissions->mayPostToRegion($region['id'], $ambassadorForum)
        ) {
            $threadId = $this->forumTransactions->createThread(
                $this->session->id(),
                $data->title,
                $data->body,
                $region,
                $ambassadorForum,
                $postActiveWithoutModeration,
                $postActiveWithoutModeration ? $data->sendMail : null
            );

            $this->forumFollowerGateway->followThreadByBell($this->session->id(), $threadId);

            if (!$postActiveWithoutModeration) {
                $this->flashMessageHelper->info($this->translator->trans('forum.hold_back_for_moderation'));
            }

            return $this->redirect($this->forumTransactions->url($region['id'], $ambassadorForum));
        }

        return $form->createView();
    }

    private function forum(Request $request, $region, $ambassadorForum): Response
    {
        $sub = $request->query->get('sub');
        $trans = $this->translator->trans(($ambassadorForum) ? 'terminology.ambassador_forum' : 'terminology.forum');
        $viewdata = $this->regionViewData($region, $sub);
        $this->pageHelper->addBread($trans, $this->forumTransactions->url($region['id'], $ambassadorForum));
        $this->pageHelper->addTitle($trans);
        $viewdata['sub'] = $sub;

        if ($threadId = $request->query->getInt('tid')) {
            $thread = $this->forumGateway->getThreadInfo($threadId);
            if (empty($thread)) {
                $this->flashMessageHelper->error($this->translator->trans('forum.not_found'));

                return $this->redirect('/region?sub=forum&bid=' . $region['id']);
            }
            $this->pageHelper->addTitle($thread['title']);
            $viewdata['threadId'] = $threadId; // this triggers the rendering of the vue component `Thread`
        } elseif ($request->query->has('newthread')) {
            $this->pageHelper->addTitle($this->translator->trans('forum.new_thread'));
            $postActiveWithoutModeration = $this->forumPermissions->mayStartUnmoderatedThread($region, $ambassadorForum);
            $viewdata['newThreadForm'] = $this->handleNewThreadForm($request, $region, $ambassadorForum, $postActiveWithoutModeration);
            $viewdata['postActiveWithoutModeration'] = $postActiveWithoutModeration;
        } else {
            $viewdata['threads'] = []; // this triggers the rendering of the vue component `ThreadList`
        }

        return $this->renderGlobal('pages/Region/forum.twig', $viewdata);
    }

    private function events(Request $request, $region): Response
    {
        $this->pageHelper->addBread($this->translator->trans('events.bread'), '/region?bid=' . $region['id'] . '&sub=events');
        $this->pageHelper->addTitle($this->translator->trans('events.bread'));
        $sub = $request->query->get('sub');
        $viewdata = $this->regionViewData($region, $sub);

        $viewdata['events'] = $this->eventGateway->listForRegion($region['id']);

        return $this->renderGlobal('pages/Region/events.twig', $viewdata);
    }

    private function applications(Request $request, $region): Response
    {
        $this->pageHelper->addBread($this->translator->trans('group.applications'), '/region?bid=' . $region['id'] . '&sub=events');
        $this->pageHelper->addTitle($this->translator->trans('group.applications_for', ['%name%' => $region['name']]));
        $sub = $request->query->get('sub');
        $viewdata = $this->regionViewData($region, $sub);
        if ($this->mayAccessApplications($region['id'])) {
            $viewdata['applications'] = $this->gateway->listApplicants($region['id']);
        }

        return $this->renderGlobal('pages/Region/applications.twig', $viewdata);
    }

    private function members(Request $request, array $region): Response
    {
        $this->pageHelper->addBread($this->translator->trans('group.members'), '/region?bid=' . $region['id'] . '&sub=members');
        $this->pageHelper->addTitle($this->translator->trans('group.members'));
        $sub = $request->query->get('sub');
        $viewdata = $this->regionViewData($region, $sub);

        if ($region['type'] === UnitType::WORKING_GROUP) {
            $mayEditMembers = $this->workGroupPermissions->mayEdit($region);
            $maySetAdminOrAmbassador = $mayEditMembers;
            $mayRemoveAdminOrAmbassador = $mayEditMembers;
        } else {
            $mayEditMembers = $this->regionPermissions->mayDeleteFoodsaverFromRegion((int)$region['id']);
            $maySetAdminOrAmbassador = $this->regionPermissions->maySetRegionAdmin();
            $mayRemoveAdminOrAmbassador = $this->regionPermissions->mayRemoveRegionAdmin();
        }
        $viewdata['mayEditMembers'] = $mayEditMembers;
        $viewdata['maySetAdminOrAmbassador'] = $maySetAdminOrAmbassador;
        $viewdata['mayRemoveAdminOrAmbassador'] = $mayRemoveAdminOrAmbassador;
        $viewdata['userId'] = $this->session->id();

        return $this->renderGlobal('pages/Region/members.twig', $viewdata);
    }

    private function statistic(Request $request, array $region): Response
    {
        $this->pageHelper->addBread(
            $this->translator->trans('terminology.statistic'),
            '/region?bid=' . $region['id'] . '&sub=statistic'
        );
        $this->pageHelper->addTitle($this->translator->trans('terminology.statistic'));
        $sub = $request->query->get('sub');
        $viewData = $this->regionViewData($region, $sub);

        $viewData['pickupData']['daily'] = 0;
        $viewData['pickupData']['weekly'] = 0;
        $viewData['pickupData']['monthly'] = 0;
        $viewData['pickupData']['yearly'] = 0;

        if ($region['type'] !== UnitType::COUNTRY || $this->regionPermissions->mayAccessStatisticCountry()) {
            $viewData['pickupData']['daily'] = $this->gateway->listRegionPickupsByDate((int)$region['id'], '%Y-%m-%d');
            $viewData['pickupData']['weekly'] = $this->gateway->listRegionPickupsByDate((int)$region['id'], '%Y/%v');
            $viewData['pickupData']['monthly'] = $this->gateway->listRegionPickupsByDate((int)$region['id'], '%Y-%m');
            $viewData['pickupData']['yearly'] = $this->gateway->listRegionPickupsByDate((int)$region['id'], '%Y');
        }

        return $this->renderGlobal('pages/Region/statistic.twig', $viewData);
    }

    private function polls(Request $request, array $region): Response
    {
        $this->pageHelper->addBread($this->translator->trans('terminology.polls'), '/region?bid=' . $region['id'] . '&sub=polls');
        $this->pageHelper->addTitle($this->translator->trans('terminology.polls'));
        $viewdata = $this->regionViewData($region, $request->query->get('sub'));
        $viewdata['polls'] = $this->votingGateway->listPolls($region['id']);
        $viewdata['regionId'] = $region['id'];
        $viewdata['mayCreatePoll'] = $this->votingPermissions->mayCreatePoll($region['id']);

        return $this->renderGlobal('pages/Region/polls.twig', $viewdata);
    }

    private function options(Request $request, array $region): Response
    {
        $this->pageHelper->addBread($this->translator->trans('terminology.options'), '/region?bid=' . $region['id'] . '&sub=options');
        $this->pageHelper->addTitle($this->translator->trans('terminology.options'));
        $viewdata = $this->regionViewData($region, $request->query->get('sub'));
        $regionOptions = $this->gateway->getAllRegionOptions($region['id']);
        $viewdata['maySetRegionOptionsReportButtons'] = boolval($this->regionPermissions->maySetRegionOptionsReportButtons($region['id']));
        $viewdata['maySetRegionOptionsRegionPickupRule'] = boolval($this->regionPermissions->maySetRegionOptionsRegionPickupRule($region['id']));
        $viewdata['isReportButtonEnabled'] = boolval(array_key_exists(RegionOptionType::ENABLE_REPORT_BUTTON, $regionOptions) ? $regionOptions[RegionOptionType::ENABLE_REPORT_BUTTON] : 0);
        $viewdata['isMediationButtonEnabled'] = boolval(array_key_exists(RegionOptionType::ENABLE_MEDIATION_BUTTON, $regionOptions) ? $regionOptions[RegionOptionType::ENABLE_MEDIATION_BUTTON] : 0);
        $viewdata['isRegionPickupRuleActive'] = boolval(array_key_exists(RegionOptionType::REGION_PICKUP_RULE_ACTIVE, $regionOptions) ? $regionOptions[RegionOptionType::REGION_PICKUP_RULE_ACTIVE] : 0);
        $viewdata['regionPickupRuleTimespanDays'] = intval(array_key_exists(RegionOptionType::REGION_PICKUP_RULE_TIMESPAN_DAYS, $regionOptions) ? $regionOptions[RegionOptionType::REGION_PICKUP_RULE_TIMESPAN_DAYS] : 0);
        $viewdata['regionPickupRuleLimitNumber'] = intval(array_key_exists(RegionOptionType::REGION_PICKUP_RULE_LIMIT_NUMBER, $regionOptions) ? $regionOptions[RegionOptionType::REGION_PICKUP_RULE_LIMIT_NUMBER] : 0);
        $viewdata['regionPickupRuleLimitDayNumber'] = intval(array_key_exists(RegionOptionType::REGION_PICKUP_RULE_LIMIT_DAY_NUMBER, $regionOptions) ? $regionOptions[RegionOptionType::REGION_PICKUP_RULE_LIMIT_DAY_NUMBER] : 0);
        $viewdata['regionPickupRuleInactiveHours'] = intval(array_key_exists(RegionOptionType::REGION_PICKUP_RULE_INACTIVE_HOURS, $regionOptions) ? $regionOptions[RegionOptionType::REGION_PICKUP_RULE_INACTIVE_HOURS] : 0);
        $viewdata['regionPickupRuleActiveStoreList'] = $this->storeGateway->listRegionStoresActivePickupRule($region['id']);

        return $this->renderGlobal('pages/Region/options.twig', $viewdata);
    }

    private function pin(Request $request, array $region): Response
    {
        $this->pageHelper->addBread($this->translator->trans('terminology.pin'), '/region?bid=' . $region['id'] . '&sub=pin');
        $this->pageHelper->addTitle($this->translator->trans('terminology.pin'));
        $viewdata = $this->regionViewData($region, $request->query->get('sub'));
        $result = $this->gateway->getRegionPin($region['id']);
        $viewdata['lat'] = $result['lat'] ?? MapConstants::CENTER_GERMANY_LAT;
        $viewdata['lon'] = $result['lon'] ?? MapConstants::CENTER_GERMANY_LON;
        $viewdata['desc'] = $result['desc'] ?? null;
        $viewdata['status'] = $result['status'] ?? null;

        return $this->renderGlobal('pages/Region/pin.twig', $viewdata);
    }
}
