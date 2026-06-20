<?php

namespace Foodsharing\Modules\Region;

use Exception;
use Foodsharing\Lib\FoodsharingController;
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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class RegionController extends FoodsharingController
{
    private array $region;
    private const int DisplayAvatarListEntries = 30;

    public function __construct(
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
            'initialActiveSubpage' => $activeSubpage,
            'pageData' => $pageData ?? [],
            'menu' => $menu,
            'mayAccessApplications' => $this->mayAccessApplications($region)
        ];
    }

    #[Route('/region', name: 'region')]
    public function index(
        #[MapQueryParameter] ?int $bid = null,
        #[MapQueryParameter] ?string $sub = null,
        #[MapQueryParameter] ?int $tid = null,
        #[MapQueryParameter] ?bool $newthread = null,
    ): Response {
        if (!$this->session->mayRole()) {
            $this->routeHelper->goLoginAndExit();
        }

        $region_id = $bid ?? $this->currentUserUnits->getCurrentRegionId() ?? 0;

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

        switch ($sub) {
            case 'botforum':
                if (!$this->forumPermissions->mayAccessAmbassadorBoard($region_id)) {
                    return $this->redirect($this->forumTransactions->url($region_id, false));
                }

                return $this->forum($region, $sub, $tid, $newthread, true);
            case 'forum':
                return $this->forum($region, $sub, $tid, $newthread, false);
            case 'wall':
                if (!UnitType::isGroup($region['type'])) {
                    $this->flashMessageHelper->info($this->translator->trans('region.forum-redirect'));

                    return $this->redirect('/region?bid=' . $region_id . '&sub=forum');
                } else {
                    return $this->wall($region, $sub);
                }
                // no break
            case 'fairteiler':
                return $this->foodSharePoint($region, $sub);
            case 'events':
                return $this->events($region, $sub);
            case 'applications':
                return $this->applications($region, $sub);
            case 'members':
                return $this->members($region, $sub);
            case 'statistic':
                return $this->statistic($region, $sub);
            case 'polls':
                return $this->polls($region, $sub);
            case 'options':
                return $this->options($region, $sub);
            case 'pin':
                return $this->redirect('/region/' . $region_id);
            case 'achievements':
                return $this->achievements($region, $sub);
            case 'resources':
                return $this->resources($region, $sub);
            case 'edit':
                return $this->editRegion($region, $sub);
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
                if ($this->currentUserUnits->mayBezirk($id)) {
                    return $this->redirect('/region?bid=' . $id . '&sub=wall');
                }

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
        if ($deniedRegionId === RegionIDs::ROOT) {
            return $this->redirectToRoute('dashboard');
        }
        $deniedRegion = $this->regionGateway->getRegion($deniedRegionId);

        // For denied regions, always redirect to their public region page.
        if (UnitType::isRegion($deniedRegion['type'])) {
            return $this->redirect('/region/' . $deniedRegionId . '?denied=' . $deniedRegionId);
        }

        $redirects = $this->regionTransactions->getInaccessibleRegionRedirects($deniedRegionId, $this->session->id() ?? 0);
        // In case there is no ancestor the user has access to, redirect to start page.
        // (can only happen for groups that don't have a region parent until root)
        if (empty($redirects)) {
            $this->flashMessageHelper->error($this->translator->trans('region.denied.some_group', [
                'name' => $deniedRegion['name'],
            ]));

            return $this->redirectToRoute('dashboard');
        }
        // Last redirect should be either a group where user is a member, or a region
        $redirect = end($redirects);

        // If User is member of the direct parent region/group of the denied group, show it's groups page.
        if ((UnitType::isGroup($deniedRegion['type']) && $redirect->isMember) &&
            $redirect->id === $deniedRegion['parent_id']
        ) {
            return $this->redirect('/groups?p=' . $redirect->id . '&denied=' . $deniedRegionId);
        }

        // Otherwise, go to the public region page and explain the denial.
        return $this->redirect('/region/' . $redirect->id . '?denied=' . $deniedRegionId);
    }

    /**
     * TODO: Backwards compatible endpoint for old bells, see MR 4594. This can be removed as soon as there are no old
     * bells referring to group applications anymore.
     */
    #[Route('/regions/{regionId}/applications', requirements: ['regionId' => '\d+'])]
    #[Route('/regions/{regionId}/applications/{userId}', requirements: ['regionId' => '\d+', 'userId' => '\d+'])]
    public function applicationsFallback(int $regionId, ?int $userId = null): Response
    {
        $path = "/region?bid=$regionId&sub=applications";
        if (!is_null($userId)) {
            $path .= "&userId=$userId";
        }

        return $this->redirect($path);
    }

    private function wall(array $region, string $sub): Response
    {
        $this->pageHelper->addBread($this->translator->trans('terminology.wall'), '/region?bid=' . $region['id'] . '&sub=wall');

        return $this->renderRegionPage($region, $sub);
    }

    private function foodSharePoint(array $region, string $sub): Response
    {
        $this->pageHelper->addBread($this->translator->trans('terminology.fsp'), '/region?bid=' . $region['id'] . '&sub=fairteiler');
        $this->pageHelper->addTitle($this->translator->trans('terminology.fsp'));

        return $this->renderRegionPage($region, $sub);
    }

    private function forum(array $region, string $sub, ?int $threadId, ?bool $newthread, bool $ambassadorForum): Response
    {
        $trans = $this->translator->trans(($ambassadorForum) ? 'terminology.ambassador_forum' : 'terminology.forum');
        $this->pageHelper->addBread($trans, $this->forumTransactions->url($region['id'], $ambassadorForum));
        $this->pageHelper->addTitle($trans);

        if ($threadId) {
            $thread = $this->forumGateway->getThreadInfo($threadId);
            if (empty($thread)) {
                $this->flashMessageHelper->error($this->translator->trans('forum.not_found'));

                return $this->redirect('/region?sub=forum&bid=' . $region['id']);
            }
            $this->pageHelper->addBread($thread['title'], $this->forumTransactions->url($region['id'], $ambassadorForum, $threadId));
            $this->pageHelper->addTitle($thread['title']);
        } elseif ($newthread) {
            $this->pageHelper->addTitle($this->translator->trans('forum.new_thread'));
        }

        return $this->renderRegionPage($region, $sub);
    }

    private function events(array $region, string $sub): Response
    {
        $this->pageHelper->addBread($this->translator->trans('events.bread'), '/region?bid=' . $region['id'] . '&sub=events');
        $this->pageHelper->addTitle($this->translator->trans('events.bread'));

        return $this->renderRegionPage($region, $sub);
    }

    private function applications(array $region, string $sub): Response
    {
        $this->pageHelper->addBread($this->translator->trans('group.applications'), '/region?bid=' . $region['id'] . '&sub=events');
        $this->pageHelper->addTitle($this->translator->trans('group.applications_for', ['%name%' => $region['name']]));

        return $this->renderRegionPage($region, $sub);
    }

    private function members(array $region, string $sub): Response
    {
        $this->pageHelper->addBread($this->translator->trans('group.members'), '/region?bid=' . $region['id'] . '&sub=members');
        $this->pageHelper->addTitle($this->translator->trans('group.members'));

        return $this->renderRegionPage($region, $sub);
    }

    private function statistic(array $region, string $sub): Response
    {
        $this->pageHelper->addBread(
            $this->translator->trans('terminology.statistic'),
            '/region?bid=' . $region['id'] . '&sub=statistic'
        );
        $this->pageHelper->addTitle($this->translator->trans('terminology.statistic'));

        return $this->renderRegionPage($region, $sub);
    }

    private function polls(array $region, string $sub): Response
    {
        $this->pageHelper->addBread($this->translator->trans('terminology.polls'), '/region?bid=' . $region['id'] . '&sub=polls');
        $this->pageHelper->addTitle($this->translator->trans('terminology.polls'));

        return $this->renderRegionPage($region, $sub);
    }

    /**
     * @throws Exception
     */
    private function options(array $region, string $sub): Response
    {
        $this->pageHelper->addBread($this->translator->trans('terminology.options'), '/region?bid=' . $region['id'] . '&sub=options');
        $this->pageHelper->addTitle($this->translator->trans('terminology.options'));

        return $this->renderRegionPage($region, $sub);
    }

    private function achievements(array $region, string $sub): Response
    {
        $this->pageHelper->addBread($this->translator->trans('terminology.achievements'), '/region?bid=' . $region['id'] . '&sub=achievements');
        $this->pageHelper->addTitle($this->translator->trans('terminology.achievements'));

        return $this->renderRegionPage($region, $sub);
    }

    private function resources(array $region, string $sub): Response
    {
        $this->pageHelper->addBread($this->translator->trans('resource_mosaic.title'), '/region?bid=' . $region['id'] . '&sub=resources');
        $this->pageHelper->addTitle($this->translator->trans('resource_mosaic.title'));

        return $this->renderRegionPage($region, $sub);
    }

    private function editRegion(array $region, string $sub): Response
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

        return $this->renderRegionPage($region, $sub);
    }

    private function renderRegionPage(array $region, string $sub): Response
    {
        $extraParams = [];

        // The user can switch between subpages in Vue,
        // so must send need all props data we might need for this region.
        $group = $this->workGroupGateway->getGroup($region['id']);
        if ($group && $group['type'] === UnitType::WORKING_GROUP && $this->workGroupPermissions->mayEdit($group)) {
            $extraParams['group'] = $group;
            $extraParams['group']['photo'] = $this->fixPhotoPath($group['photo']);
        }

        $params = $this->convertDataToObject($region, $sub, $extraParams);
        $this->pageHelper->addContent($this->prepareVueComponent('region-page', 'RegionPage', $params));

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
