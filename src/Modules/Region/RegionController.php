<?php

namespace Foodsharing\Modules\Region;

use Exception;
use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Achievement\AchievementGateway;
use Foodsharing\Modules\Content\ContentView;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Region\RegionOptionType;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Permissions\AchievementPermissions;
use Foodsharing\Permissions\FoodSharePointPermissions;
use Foodsharing\Permissions\ForumPermissions;
use Foodsharing\Permissions\RegionPermissions;
use Foodsharing\Permissions\ReportPermissions;
use Foodsharing\Permissions\VotingPermissions;
use Foodsharing\Permissions\WorkGroupPermissions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RegionController extends FoodsharingController
{
    private array $region;
    private const int DisplayAvatarListEntries = 30;

    public function __construct(
        private readonly ContentView $view,
        private readonly ReportPermissions $reportPermissions,
        private readonly RegionGateway $regionGateway,
        private readonly ForumPermissions $forumPermissions,
        private readonly RegionPermissions $regionPermissions,
        private readonly ForumTransactions $forumTransactions,
        private readonly VotingPermissions $votingPermissions,
        private readonly WorkGroupPermissions $workGroupPermissions,
        private readonly StoreGateway $storeGateway,
        private readonly FoodSharePointPermissions $foodSharePointPermissions,
        private readonly ForumGateway $forumGateway,
        private readonly AchievementGateway $achievementGateway,
        private readonly AchievementPermissions $achievementPermissions,
        private readonly GroupFunctionGateway $groupFunctionGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
    ) {
        parent::__construct();
    }

    private function mayAccessApplications(array $group): bool
    {
        return $this->workGroupPermissions->mayEdit($group);
    }

    private function isHomeDistrict(int $regionId): bool
    {
        return $regionId === $this->currentUserUnits->getCurrentRegionId();
    }

    private function getMenu(array $group): array
    {
        $groupId = $group['id'];

        $menu = [];
        $menu['id'] = $group['id'];
        $menu['name'] = $group['name'];
        $menu['type'] = $group['type'];
        $menu['parent_id'] = $group['parent_id'];
        $menu['mayHandleFoodsaverRegionMenu'] = $this->regionPermissions->mayHandleFoodsaverRegionMenu($groupId);
        $menu['hasConference'] = $this->regionPermissions->hasConference($group['type']);
        $menu['hasAchievements'] = $this->achievementGateway->regionHasAchievements($group['id']);

        if ($this->currentUserUnits->isAdminFor($groupId)) {
            $menu['mailboxId'] = $group['mailbox_id'];
        }

        if (UnitType::isRegion($group['type'])) {
            $menu['isAdmin'] = $this->currentUserUnits->isAdminFor($groupId);
            $menu['mayAccessReports'] = $this->reportPermissions->mayAccessReportsForRegion($groupId);
            $menu['isReportAdmin'] = $this->reportPermissions->isReportAdmin($groupId);
            $menu['isArbitrationAdmin'] = $this->reportPermissions->isArbitrationAdmin($groupId);
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

    /**
     * Fetches the admins of this group and, in case of a region, of all working groups with special functions.
     *
     * @return array an array with working group function strings as keys and a list of Profile objects as values
     */
    private function mergeAdmins(int $regionId, bool $isWorkgroup): array
    {
        $admins = $this->foodsaverGateway->getAdminsOrAmbassadors($regionId);
        shuffle($admins);
        $mergedAdmins = [
            'botschafter' => array_map(fn ($fs) => new Profile($fs), $admins)
        ];

        if (!$isWorkgroup) {
            $functionMappings = [
                WorkgroupFunction::WELCOME => 'welcomeAdmins',
                WorkgroupFunction::VOTING => 'votingAdmins',
                WorkgroupFunction::FSP => 'fspAdmins',
                WorkgroupFunction::STORES_COORDINATION => 'storesAdmins',
                WorkgroupFunction::REPORT => 'reportAdmins',
                WorkgroupFunction::MEDIATION => 'mediationAdmins',
                WorkgroupFunction::ARBITRATION => 'arbitrationAdmins',
                WorkgroupFunction::FSMANAGEMENT => 'fsManagementAdmins',
                WorkgroupFunction::PR => 'prAdmins',
                WorkgroupFunction::MODERATION => 'moderationAdmins',
                WorkgroupFunction::BOARD => 'boardAdmins',
                WorkgroupFunction::ELECTION => 'electionAdmins',
            ];

            foreach ($functionMappings as $function => $adminKey) {
                $groupId = $this->groupFunctionGateway->getRegionFunctionGroupId($regionId, $function);
                if ($groupId) {
                    $admins = $this->foodsaverGateway->getAdminsOrAmbassadors($groupId);
                    shuffle($admins);
                    $admins = array_slice($admins, 0, self::DisplayAvatarListEntries);
                    $mergedAdmins[$adminKey] = array_map(fn ($fs) => new Profile($fs), $admins);
                }
            }
        }

        return $mergedAdmins;
    }

    private function convertDataToObject(array $region, ?string $activeSubpage, ?array $pageData): array
    {
        $regionId = (int)$region['id'];

        $isWorkGroup = UnitType::isGroup($region['type']);

        $menu = $this->getMenu($region);

        return [
            'regionId' => $regionId,
            'name' => $this->region['name'],
            'moderated' => $this->region['moderated'],
            'isWorkGroup' => $isWorkGroup,
            'isHomeDistrict' => $this->isHomeDistrict($region['id']),
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
            'allAdmins' => $this->mergeAdmins($region['id'], UnitType::isGroup($region['type'])),
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

        $region_id = $request->query->getInt('bid', $this->currentUserUnits->getCurrentRegionId() ?? 0);

        $region = $this->regionGateway->getRegionDetails($region_id);
        if (!empty($region) && $this->currentUserUnits->mayBezirk($region_id)) {
            $big = [UnitType::BIG_CITY, UnitType::FEDERAL_STATE, UnitType::COUNTRY];
            $region['moderated'] = $region['moderated'] || in_array($region['type'], $big);
            $this->region = $region;
        } else {
            $this->flashMessageHelper->error($this->translator->trans('region.not-member'));

            return $this->redirectToRoute('dashboard');
        }

        $this->pageHelper->addTitle($region['name']);

        if ($region['parent_id'] !== RegionIDs::ROOT) {
            $parent = $this->regionGateway->getRegionName($region['parent_id']);
            $this->pageHelper->addBread($parent, '/region?bid=' . $region['parent_id']);
        }
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
            case 'achievements':
                return $this->achievements($request, $region);
            default:
                if (UnitType::isGroup($region['type'])) {
                    return $this->redirect('/region?bid=' . $region_id . '&sub=wall');
                } else {
                    return $this->redirect($this->forumTransactions->url($region_id, false));
                }
        }
    }

    #[Route('/regions/edit')]
    public function edit(): Response
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
        } elseif ($request->query->has('newthread')) {
            $this->pageHelper->addTitle($this->translator->trans('forum.new_thread'));
        }

        $params = $this->convertDataToObject($region, $sub, []);

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

        $params = $this->convertDataToObject($region, $sub, []);

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

        $params = $this->convertDataToObject($region, $sub, []);

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
        $regionOptions = $this->regionGateway->getAllRegionOptions($region['id']);
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
        $pageData['selectedReportReasonOptions'] = intval($regionOptions[RegionOptionType::REPORT_REASON_OPTIONS] ?? 1);
        $pageData['reportReasonOtherEnabled'] = boolval($regionOptions[RegionOptionType::REPORT_REASON_OTHER] ?? 1);

        $params = $this->convertDataToObject($region, $request->query->get('sub'), $pageData);

        $this->pageHelper->addContent($this->view->vueComponent('region-page', 'RegionPage', $params));

        return $this->renderGlobal();
    }

    private function pin(Request $request, array $region): Response
    {
        $this->pageHelper->addBread($this->translator->trans('terminology.pin'), '/region?bid=' . $region['id'] . '&sub=pin');
        $this->pageHelper->addTitle($this->translator->trans('terminology.pin'));

        $params = $this->convertDataToObject($region, $request->query->get('sub'), []);

        $this->pageHelper->addContent($this->view->vueComponent('region-page', 'RegionPage', $params));

        return $this->renderGlobal();
    }

    private function achievements(Request $request, array $region): Response
    {
        $this->pageHelper->addBread($this->translator->trans('terminology.achievements'), '/region?bid=' . $region['id'] . '&sub=achievements');
        $this->pageHelper->addTitle($this->translator->trans('terminology.achievements'));

        $params = $this->convertDataToObject($region, $request->query->get('sub'), []);
        $params['mayAdministrateAchievements'] = $this->achievementPermissions->mayAdministrateAchievementsFromRegion($region['id']);

        $this->pageHelper->addContent($this->view->vueComponent('region-page', 'RegionPage', $params));

        return $this->renderGlobal();
    }
}
