<?php

namespace Foodsharing\Modules\Region;

use Exception;
use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Content\ContentView;
use Foodsharing\Modules\Core\DBConstants\Map\MapConstants;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Region\RegionOptionType;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Permissions\FoodSharePointPermissions;
use Foodsharing\Permissions\ForumPermissions;
use Foodsharing\Permissions\RegionPermissions;
use Foodsharing\Permissions\ReportPermissions;
use Foodsharing\Permissions\VotingPermissions;
use Foodsharing\Permissions\WorkGroupPermissions;
use Foodsharing\Utility\DataHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class RegionController extends FoodsharingController
{
    private array $region;
    private const DisplayAvatarListEntries = 30;

    public function __construct(
        private readonly ContentView $view,
        private readonly ReportPermissions $reportPermissions,
        private readonly RegionGateway $regionGateway,
        private readonly ForumPermissions $forumPermissions,
        private readonly RegionPermissions $regionPermissions,
        private readonly ForumTransactions $forumTransactions,
        private readonly RegionGateway $gateway,
        private readonly VotingPermissions $votingPermissions,
        private readonly WorkGroupPermissions $workGroupPermissions,
        private readonly StoreGateway $storeGateway,
        private readonly DataHelper $dataHelper,
        private readonly FoodSharePointPermissions $foodSharePointPermissions,
        private readonly ForumGateway $forumGateway
    ) {
        parent::__construct();
    }

    private function mayAccessApplications(array $group): bool
    {
        return $this->workGroupPermissions->mayEdit($group);
    }

    private function isHomeDistrict($region): bool
    {
        return (int)$region['id'] === $this->session->getCurrentRegionId();
    }

    private function getMenu(array $group, bool $isWorkgroup): array
    {
        $groupType = $isWorkgroup ? UnitType::WORKING_GROUP : UnitType::REGION;
        $groupId = $group['id'];

        $menu = [];
        $menu['id'] = $group['id'];
        $menu['name'] = $group['name'];
        $menu['type'] = $groupType;
        $menu['parent_id'] = $group['parent_id'];
        $menu['mayHandleFoodsaverRegionMenu'] = $this->regionPermissions->mayHandleFoodsaverRegionMenu($groupId);
        $menu['hasConference'] = $this->regionPermissions->hasConference($groupType);

        if ($this->session->isAdminFor($groupId)) {
            $menu['mailboxId'] = $group['mailbox_id'];
        }

        if (UnitType::isRegion($groupType)) {
            $menu['isAdmin'] = $this->session->isAdminFor($groupId);
            $menu['mayAccessReportGroupReports'] = $this->reportPermissions->mayAccessReportGroupReports($groupId);
            $menu['mayAccessArbitrationGroupReports'] = $this->reportPermissions->mayAccessArbitrationReports($groupId);
            $menu['maySetRegionPin'] = $this->regionPermissions->maySetRegionPin($groupId);
        } else {
            $menu['isAdmin'] = $this->workGroupPermissions->mayEdit($group);
            $menu['hasSubgroups'] = $this->regionGateway->hasSubgroups($groupId);
            if ($groupId == RegionIDs::STORE_CHAIN_GROUP) {
                $menu['isChainGroup'] = true;
            }
        }

        return $menu;
    }

    private function mergeAdmins(array $region, bool $isWorkgroup, callable $avatarListEntry): array
    {
        $allRegionAdmins = [
            'botschafter',
            'welcomeAdmins',
            'votingAdmins',
            'fspAdmins',
            'storesAdmins',
            'reportAdmins',
            'mediationAdmins',
            'arbitrationAdmins',
            'fsManagementAdmins',
            'prAdmins',
            'moderationAdmins',
            'boardAdmins',
            'electionAdmins'
        ];

        $allGroupAdmins = [
            'botschafter'
        ];

        $allAdmins = $isWorkgroup ? $allGroupAdmins : $allRegionAdmins;

        $mergedAdmins = [];
        foreach ($allAdmins as $adminKey) {
            if (isset($region[$adminKey]) && is_array($region[$adminKey])) {
                $mergedAdmins[$adminKey] = array_map($avatarListEntry, array_slice($region[$adminKey], 0, self::DisplayAvatarListEntries));
            }
        }

        return $mergedAdmins;
    }

    private function convertDataToObject(array $region, ?string $activeSubpage, ?array $pageData): array
    {
        $regionId = (int)$region['id'];

        $avatarListEntry = fn ($fs) => new Profile(
            $fs['id'],
            $fs['name'],
            $fs['photo'],
            (int)$this->dataHelper->parseSleepingState($fs['sleep_status'], $fs['sleep_from'], $fs['sleep_until'])
        );

        $isWorkGroup = UnitType::isGroup($region['type']);

        $menu = $this->getMenu($region, $isWorkGroup);

        return [
            'regionId' => $regionId,
            'name' => $this->region['name'],
            'moderated' => $this->region['moderated'],
            'isWorkGroup' => $isWorkGroup,
            'isHomeDistrict' => $this->isHomeDistrict($region),
            'isRegion' => !UnitType::isGroup($region['type']),
            'foodSaverCount' => $this->region['fs_count'],
            'foodSaverHomeDistrictCount' => $this->region['fs_home_count'],
            'foodSaverHasSleepingHatCount' => $this->region['sleeper_count'],
            'ambassadorCount' => $this->region['stat_botcount'],
            'storesCount' => $this->region['stat_betriebcount'],
            'storesCooperationCount' => $this->region['stat_korpcount'],
            'storesPickupsCount' => $this->region['stat_fetchcount'],
            'storesFetchedWeight' => round($this->region['stat_fetchweight']),
            'parent_id' => $this->region['parent_id'],
            'allAdmins' => $this->mergeAdmins($region, UnitType::isGroup($region['type']), $avatarListEntry),
            'activeSubpage' => $activeSubpage,
            'pageData' => $pageData,
            'menu' => $menu,
            'mayAccessApplications' => $this->mayAccessApplications($region)
        ];
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

    #[Route('/regions/edit')]
    public function edit(Request $request): Response
    {
        if (!$this->regionPermissions->mayAdministrateRegions()) {
            return $this->redirect('/');
        }

        $this->pageHelper->addContent($this->prepareVueComponent('regions-admin-page', 'RegionsAdmin'));

        return $this->renderGlobal();
    }

    private function wall(Request $request, array $region): Response
    {
        $this->pageHelper->addBread($this->translator->trans('terminology.wall'), '/region?bid=' . $region['id'] . '&sub=wall');
        $sub = $request->query->get('sub');
        $params = $this->convertDataToObject($region, $sub, null);
        $this->pageHelper->addContent($this->view->vueComponent('region-page', 'RegionPage', $params));

        return $this->renderGlobal();
    }

    private function foodSharePoint(Request $request, array $region): Response
    {
        $this->pageHelper->addBread($this->translator->trans('terminology.fsp'), '/region?bid=' . $region['id'] . '&sub=fairteiler');
        $this->pageHelper->addTitle($this->translator->trans('terminology.fsp'));
        $sub = $request->query->get('sub');
        $pageData['foodSharePointPermission'] = $this->foodSharePointPermissions->mayAdd($region['id']);
        $params = $this->convertDataToObject($region, $sub, $pageData);
        $this->pageHelper->addContent($this->view->vueComponent('region-page', 'RegionPage', $params));

        return $this->renderGlobal();
    }

    private function forum(Request $request, $region, $ambassadorForum): Response
    {
        $sub = $request->query->get('sub');
        $trans = $this->translator->trans(($ambassadorForum) ? 'terminology.ambassador_forum' : 'terminology.forum');
        $this->pageHelper->addBread($trans, $this->forumTransactions->url($region['id'], $ambassadorForum));
        $this->pageHelper->addTitle($trans);

        if ($threadId = $request->query->getInt('tid')) {
            $thread = $this->forumGateway->getThreadInfo($threadId);
            if (empty($thread)) {
                $this->flashMessageHelper->error($this->translator->trans('forum.not_found'));

                return $this->redirect('/region?sub=forum&bid=' . $region['id']);
            }
            $this->pageHelper->addTitle($thread['title']);
            $pageData['threadId'] = $threadId;
        } elseif ($request->query->has('newthread')) {
            $this->pageHelper->addTitle($this->translator->trans('forum.new_thread'));
            $postActiveWithoutModeration = $this->forumPermissions->mayStartUnmoderatedThread($region, $ambassadorForum);
            $pageData['newThreadForm'] = true;
            $pageData['postActiveWithoutModeration'] = $postActiveWithoutModeration;
        } else {
            $pageData['threads'] = [];
        }

        $params = $this->convertDataToObject($region, $sub, $pageData);

        $this->pageHelper->addContent($this->view->vueComponent('region-page', 'RegionPage', $params));

        return $this->renderGlobal();
    }

    private function events(Request $request, $region): Response
    {
        $this->pageHelper->addBread($this->translator->trans('events.bread'), '/region?bid=' . $region['id'] . '&sub=events');
        $this->pageHelper->addTitle($this->translator->trans('events.bread'));
        $sub = $request->query->get('sub');
        $params = $this->convertDataToObject($region, $sub, null);
        $this->pageHelper->addContent($this->view->vueComponent('region-page', 'RegionPage', $params));

        return $this->renderGlobal();
    }

    private function applications(Request $request, $region): Response
    {
        $this->pageHelper->addBread($this->translator->trans('group.applications'), '/region?bid=' . $region['id'] . '&sub=events');
        $this->pageHelper->addTitle($this->translator->trans('group.applications_for', ['%name%' => $region['name']]));
        $sub = $request->query->get('sub');

        $params = $this->convertDataToObject($region, $sub, null);

        $this->pageHelper->addContent($this->view->vueComponent('region-page', 'RegionPage', $params));

        return $this->renderGlobal();
    }

    private function members(Request $request, array $region): Response
    {
        $this->pageHelper->addBread($this->translator->trans('group.members'), '/region?bid=' . $region['id'] . '&sub=members');
        $this->pageHelper->addTitle($this->translator->trans('group.members'));
        $sub = $request->query->get('sub');

        if ($region['type'] === UnitType::WORKING_GROUP) {
            $mayEditMembers = $this->workGroupPermissions->mayEdit($region);
            $maySetAdminOrAmbassador = $mayEditMembers;
            $mayRemoveAdminOrAmbassador = $mayEditMembers;
        } else {
            $mayEditMembers = $this->regionPermissions->mayDeleteFoodsaverFromRegion((int)$region['id']);
            $maySetAdminOrAmbassador = $this->regionPermissions->maySetRegionAdmin();
            $mayRemoveAdminOrAmbassador = $this->regionPermissions->mayRemoveRegionAdmin();
        }
        $pageData['mayEditMembers'] = $mayEditMembers;
        $pageData['maySetAdminOrAmbassador'] = $maySetAdminOrAmbassador;
        $pageData['mayRemoveAdminOrAmbassador'] = $mayRemoveAdminOrAmbassador;
        $pageData['userId'] = $this->session->id();
        $params = $this->convertDataToObject($region, $sub, $pageData);

        $this->pageHelper->addContent($this->view->vueComponent('region-page', 'RegionPage', $params));

        return $this->renderGlobal();
    }

    private function statistic(Request $request, array $region): Response
    {
        $this->pageHelper->addBread(
            $this->translator->trans('terminology.statistic'),
            '/region?bid=' . $region['id'] . '&sub=statistic'
        );
        $this->pageHelper->addTitle($this->translator->trans('terminology.statistic'));
        $sub = $request->query->get('sub');

        $pageData['pickupData']['daily'] = 0;
        $pageData['pickupData']['weekly'] = 0;
        $pageData['pickupData']['monthly'] = 0;
        $pageData['pickupData']['yearly'] = 0;

        if ($region['type'] !== UnitType::COUNTRY || $this->regionPermissions->mayAccessStatisticCountry()) {
            $pageData['pickupData']['daily'] = $this->gateway->listRegionPickupsByDate((int)$region['id'], '%Y-%m-%d');
            $pageData['pickupData']['weekly'] = $this->gateway->listRegionPickupsByDate((int)$region['id'], '%Y/%v');
            $pageData['pickupData']['monthly'] = $this->gateway->listRegionPickupsByDate((int)$region['id'], '%Y-%m');
            $pageData['pickupData']['yearly'] = $this->gateway->listRegionPickupsByDate((int)$region['id'], '%Y');
        }
        $params = $this->convertDataToObject($region, $sub, $pageData);

        $this->pageHelper->addContent($this->view->vueComponent('region-page', 'RegionPage', $params));

        return $this->renderGlobal();
    }

    private function polls(Request $request, array $region): Response
    {
        $this->pageHelper->addBread($this->translator->trans('terminology.polls'), '/region?bid=' . $region['id'] . '&sub=polls');
        $this->pageHelper->addTitle($this->translator->trans('terminology.polls'));
        $pageData['mayCreatePoll'] = $this->votingPermissions->mayCreatePoll($region['id']);

        $params = $this->convertDataToObject($region, $request->query->get('sub'), $pageData);
        $this->pageHelper->addContent($this->view->vueComponent('region-page', 'RegionPage', $params));

        return $this->renderGlobal();
    }

    /**
     * @throws Exception
     */
    private function options(Request $request, array $region): Response
    {
        $this->pageHelper->addBread($this->translator->trans('terminology.options'), '/region?bid=' . $region['id'] . '&sub=options');
        $this->pageHelper->addTitle($this->translator->trans('terminology.options'));
        $regionOptions = $this->gateway->getAllRegionOptions($region['id']);
        $pageData['maySetRegionOptionsReportButtons'] = boolval($this->regionPermissions->maySetRegionOptionsReportButtons($region['id']));
        $pageData['maySetRegionOptionsRegionPickupRule'] = boolval($this->regionPermissions->maySetRegionOptionsRegionPickupRule($region['id']));
        $pageData['isReportButtonEnabled'] = boolval(array_key_exists(RegionOptionType::ENABLE_REPORT_BUTTON, $regionOptions) ? $regionOptions[RegionOptionType::ENABLE_REPORT_BUTTON] : 0);
        $pageData['isMediationButtonEnabled'] = boolval(array_key_exists(RegionOptionType::ENABLE_MEDIATION_BUTTON, $regionOptions) ? $regionOptions[RegionOptionType::ENABLE_MEDIATION_BUTTON] : 0);
        $pageData['isRegionPickupRuleActive'] = boolval(array_key_exists(RegionOptionType::REGION_PICKUP_RULE_ACTIVE, $regionOptions) ? $regionOptions[RegionOptionType::REGION_PICKUP_RULE_ACTIVE] : 0);
        $pageData['regionPickupRuleTimespanDays'] = intval(array_key_exists(RegionOptionType::REGION_PICKUP_RULE_TIMESPAN_DAYS, $regionOptions) ? $regionOptions[RegionOptionType::REGION_PICKUP_RULE_TIMESPAN_DAYS] : 0);
        $pageData['regionPickupRuleLimitNumber'] = intval(array_key_exists(RegionOptionType::REGION_PICKUP_RULE_LIMIT_NUMBER, $regionOptions) ? $regionOptions[RegionOptionType::REGION_PICKUP_RULE_LIMIT_NUMBER] : 0);
        $pageData['regionPickupRuleLimitDayNumber'] = intval(array_key_exists(RegionOptionType::REGION_PICKUP_RULE_LIMIT_DAY_NUMBER, $regionOptions) ? $regionOptions[RegionOptionType::REGION_PICKUP_RULE_LIMIT_DAY_NUMBER] : 0);
        $pageData['regionPickupRuleInactiveHours'] = intval(array_key_exists(RegionOptionType::REGION_PICKUP_RULE_INACTIVE_HOURS, $regionOptions) ? $regionOptions[RegionOptionType::REGION_PICKUP_RULE_INACTIVE_HOURS] : 0);
        $pageData['regionPickupRuleActiveStoreList'] = $this->storeGateway->listRegionStoresActivePickupRule($region['id']);

        $params = $this->convertDataToObject($region, $request->query->get('sub'), $pageData);

        $this->pageHelper->addContent($this->view->vueComponent('region-page', 'RegionPage', $params));

        return $this->renderGlobal();
    }

    private function pin(Request $request, array $region): Response
    {
        $this->pageHelper->addBread($this->translator->trans('terminology.pin'), '/region?bid=' . $region['id'] . '&sub=pin');
        $this->pageHelper->addTitle($this->translator->trans('terminology.pin'));
        $result = $this->gateway->getRegionPin($region['id']);
        $pageData['lat'] = $result['lat'] ?? MapConstants::CENTER_GERMANY_LAT;
        $pageData['lon'] = $result['lon'] ?? MapConstants::CENTER_GERMANY_LON;
        $pageData['desc'] = $result['desc'] ?? null;
        $pageData['status'] = $result['status'] ?? null;

        $params = $this->convertDataToObject($region, $request->query->get('sub'), $pageData);

        $this->pageHelper->addContent($this->view->vueComponent('region-page', 'RegionPage', $params));

        return $this->renderGlobal();
    }
}
