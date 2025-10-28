<?php

namespace Foodsharing\Modules\Region;

use Exception;
use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Content\ContentView;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\WorkGroup\WorkGroupGateway;
use Foodsharing\Permissions\ForumPermissions;
use Foodsharing\Permissions\RegionPermissions;
use Foodsharing\Permissions\WorkGroupPermissions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class RegionController extends FoodsharingController
{
    private array $region;
    private const int DisplayAvatarListEntries = 30;

    public function __construct(
        private readonly ContentView $view,
        private readonly RegionGateway $regionGateway,
        private readonly ForumPermissions $forumPermissions,
        private readonly RegionPermissions $regionPermissions,
        private readonly ForumTransactions $forumTransactions,
        private readonly WorkGroupPermissions $workGroupPermissions,
        private readonly ForumGateway $forumGateway,
        private readonly GroupFunctionGateway $groupFunctionGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly RegionTransactions $regionTransactions,
        private readonly WorkGroupGateway $workGroupGateway,
    ) {
        parent::__construct();
    }

    private function mayAccessApplications(array $group): bool
    {
        return $this->workGroupPermissions->mayEdit($group);
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
                WorkgroupFunction::RESOURCES => 'resourcesAdmins',
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

        $menu = $this->regionTransactions->getMenu($region['id'], $region);

        return [
            'regionId' => $regionId,
            'name' => $this->region['name'],
            'moderated' => $this->region['moderated'],
            'isWorkGroup' => $isWorkGroup,
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

    #[Route('/region', name: 'region')]
    public function index(Request $request): Response
    {
        if (!$this->session->mayRole()) {
            $this->routeHelper->goLoginAndExit();
        }

        $region_id = $request->query->getInt('bid', $this->currentUserUnits->getCurrentRegionId() ?? 0);

        $region = $this->regionGateway->getRegionDetails($region_id);

        if (empty($region)) {
            $this->flashMessageHelper->error($this->translator->trans('region.not-existant'));

            return $this->redirectToRoute('dashboard');
        }
        if (!$this->currentUserUnits->mayBezirk($region_id)) {
            return $this->missingMembershipRedirect($region_id);
        }

        $big = [UnitType::BIG_CITY, UnitType::FEDERAL_STATE, UnitType::COUNTRY];
        $region['moderated'] = $region['moderated'] || in_array($region['type'], $big);
        $this->region = $region;

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
                return $this->redirect('/region/' . $region_id);
            case 'achievements':
                return $this->achievements($request, $region);
            case 'resources':
                return $this->resources($request, $region);
            case 'edit':
                return $this->editRegion($request, $region);
            default:
                if (UnitType::isGroup($region['type'])) {
                    return $this->redirect('/region?bid=' . $region_id . '&sub=wall');
                } else {
                    return $this->redirect($this->forumTransactions->url($region_id, false));
                }
        }
    }

    #[Route(path: '/region/{id}', name: 'regionPublic', requirements: ['id' => Requirement::POSITIVE_INT])]
    public function regionPublic(int $id): Response
    {
        try {
            $type = $this->regionGateway->getType($id);
            if ($type === UnitType::WORKING_GROUP) {
                return $this->missingMembershipRedirect($id);
            }
        } catch (\Throwable $th) {
            return $this->redirect('/');
        }

        $this->pageHelper->addContent($this->prepareVueComponent('public-region-page', 'PublicRegionPage', ['id' => $id]));

        return $this->renderGlobal();
    }

    #[Route(path: '/region/{regionMail}', name: 'regionPublicByName', requirements: ['regionMail' => '[a-zA-Z][a-zA-Z0-9.\-_]+[a-zA-Z0-9.]'])]
    public function regionPublicByMail(string $regionMail): Response
    {
        try {
            $id = $this->regionGateway->getRegionIdByMail($regionMail);

            return $this->regionPublic($id);
        } catch (\Throwable $th) {
            return $this->redirect('/');
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

    /**
     * Redirects to a different page when the user tried to access a region or group related page that they have no permission to access.
     * This redirects to the next regions public page or the next groups subgroup page up the region hierarchie of the denied region / group.
     * @param int $deniedRegionId id of the region the user was denied to access
     */
    #[Route(path: '/region/denied/{deniedRegionId}', name: 'regionDenied', requirements: ['deniedRegionId' => Requirement::POSITIVE_INT])]
    public function missingMembershipRedirect(int $deniedRegionId): Response
    {
        $redirects = $this->regionTransactions->getInaccessibleRegionRedirects($deniedRegionId, $this->session->id() ?? 0);
        if (empty($redirects)) {
            // in case there is no ancestor the user has access to, redirect to start page
            // (can only happen for groups that don't have a region parent until root)
            return $this->redirectToRoute('dashboard');
        }
        if (end($redirects)['type'] === UnitType::WORKING_GROUP) {
            $extra = '';
            if (end($redirects)['is_member'] !== 1) {
                // Do NOT include the "denied=" parameter if we redirected to
                // the groups's wall page when we have access to it.
                $extra = '&denied=' . $deniedRegionId;
            }

            return $this->redirect('/region?bid=' . end($redirects)['id'] . $extra);
        }

        return $this->redirect('/region/' . end($redirects)['id'] . '?denied=' . $deniedRegionId);
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
        $params = $this->convertDataToObject($region, $sub, []);
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

        $params = $this->convertDataToObject($region, $request->query->get('sub'), []);
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

        $params = $this->convertDataToObject($region, $request->query->get('sub'), []);

        $this->pageHelper->addContent($this->view->vueComponent('region-page', 'RegionPage', $params));

        return $this->renderGlobal();
    }

    private function achievements(Request $request, array $region): Response
    {
        $this->pageHelper->addBread($this->translator->trans('terminology.achievements'), '/region?bid=' . $region['id'] . '&sub=achievements');
        $this->pageHelper->addTitle($this->translator->trans('terminology.achievements'));

        $params = $this->convertDataToObject($region, $request->query->get('sub'), []);

        $this->pageHelper->addContent($this->view->vueComponent('region-page', 'RegionPage', $params));

        return $this->renderGlobal();
    }

    private function resources(Request $request, array $region): Response
    {
        $this->pageHelper->addBread($this->translator->trans('resource_mosaic.title'), '/region?bid=' . $region['id'] . '&sub=resources');
        $this->pageHelper->addTitle($this->translator->trans('resource_mosaic.title'));

        $params = $this->convertDataToObject($region, $request->query->get('sub'), []);

        $this->pageHelper->addContent($this->view->vueComponent('region-page', 'RegionPage', $params));

        return $this->renderGlobal();
    }

    private function editRegion(Request $request, array $region): Response
    {
        $group = $this->workGroupGateway->getGroup($region['id']);
        if (!$group) {
            return $this->redirectToRoute('groups');
        } elseif ($group['type'] != UnitType::WORKING_GROUP || !$this->workGroupPermissions->mayEdit($group)) {
            return $this->redirectToRoute('dashboard');
        }

        $translation = $this->translator->trans('group.edit.title', ['{group}' => $group['name']]);
        $this->pageHelper->addBread($translation, '/groups?sub=edit&id=' . (int)$group['id']);
        $this->pageHelper->addTitle($translation);

        $group['photo'] = $this->fixPhotoPath($group['photo']);
        $params = $this->convertDataToObject($region, $request->query->get('sub'), [
            'group' => $group,
        ]);

        $this->pageHelper->addContent($this->view->vueComponent('region-page', 'RegionPage', $params));

        return $this->renderGlobal();
    }

    /**
     * Old photos that were uploaded by Xhr are named "workgroup/[uuid].jpg" or "photo/[uuid].jpg" and are in the
     * /images/workgroup directory. New ones that were uploaded with the REST API already contain the full path when
     * stored in the database. This function returns a valid path for all photos.
     *
     * @param string $photo the group's photo file from the database
     *
     * @return string the valid path that can be used in the frontend
     */
    private function fixPhotoPath(string $photo): string
    {
        return (!empty($photo) && (str_starts_with($photo, 'workgroup') || str_starts_with($photo, 'photo')))
            ? '/images/' . $photo
            : $photo;
    }
}
