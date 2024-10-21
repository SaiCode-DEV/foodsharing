<?php

namespace Foodsharing\Modules\WorkGroup;

use Foodsharing\Modules\Core\Control;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Permissions\WorkGroupPermissions;
use Foodsharing\Utility\ImageHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WorkGroupControl extends Control
{
    private readonly WorkGroupGateway $workGroupGateway;
    private readonly WorkGroupPermissions $workGroupPermissions;
    private readonly ImageHelper $imageService;
    private readonly RegionGateway $regionGateway;

    public function __construct(
        WorkGroupView $view,
        WorkGroupGateway $workGroupGateway,
        WorkGroupPermissions $workGroupPermissions,
        ImageHelper $imageService,
        RegionGateway $regionGateway,
    ) {
        $this->view = $view;
        $this->workGroupGateway = $workGroupGateway;
        $this->workGroupPermissions = $workGroupPermissions;
        $this->imageService = $imageService;
        $this->regionGateway = $regionGateway;

        parent::__construct();
    }

    public function index(Request $request, Response $response): void
    {
        if (!$this->session->mayRole()) {
            $this->routeHelper->goLoginAndExit();
        }

        $region_id = $request->query->getInt('p', $this->currentUserUnits->getCurrentRegionId() ?? 0);
        $parent = $this->regionGateway->getRegionName($region_id);

        $this->pageHelper->addBread($parent, '/region?bid=' . $region_id);
        $this->pageHelper->addBread($this->translator->trans('terminology.groups'), '/?page=groups');

        if (!$request->query->has('sub')) {
            $this->list($request, $response);
        } elseif ($request->query->get('sub') == 'edit') {
            $this->edit($request, $response);
        }
    }

    private function getSideMenuData(?string $activeUrlPartial = null): array
    {
        $countries = $this->workGroupGateway->getCountryGroups();
        $regions = $this->currentUserUnits->getRegions();

        $localRegions = array_filter($regions, fn ($region) => !in_array($region['type'], [UnitType::COUNTRY, UnitType::WORKING_GROUP]));

        // Sort local regions by name in ascending order
        usort($localRegions, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        $regionToMenuItem = fn ($region) => [
            'name' => $region['name'],
            'href' => '/?page=groups&p=' . $region['id']
        ];

        $menuGlobal = [['name' => $this->translator->trans('group.show-all'), 'href' => '/?page=groups']];
        $menuLocalRegions = array_map($regionToMenuItem, $localRegions);
        $menuCountries = array_map($regionToMenuItem, $countries);

        $myRegions = $this->currentUserUnits->getRegions();
        $myGroups = array_filter($myRegions, fn ($group) => UnitType::isGroup($group['type']));

        // Sort the myGroups array by the 'name' key in ascending order
        usort($myGroups, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        $menuMyGroups = array_map(
            fn ($group) => [
                'name' => $group['name'],
                'href' => '/region?bid=' . $group['id'] . '&sub=forum'
            ], $myGroups
        );

        return [
            'global' => $menuGlobal,
            'local' => $menuLocalRegions,
            'countries' => $menuCountries,
            'groups' => $menuMyGroups,
            'active' => $activeUrlPartial,
        ];
    }

    private function list(Request $request, Response $response): void
    {
        $this->pageHelper->addTitle($this->translator->trans('terminology.groups'));

        $sessionId = $this->session->id();
        $parent = $request->query->getInt('p', RegionIDs::GLOBAL_WORKING_GROUPS);
        $myApplications = $this->workGroupGateway->getApplications($sessionId);
        $myStats = $this->workGroupGateway->getStats($sessionId);
        $groups = $this->getGroups($parent, $myApplications, $myStats);

        foreach ($groups as &$group) {
            $group['function_tooltip_key'] = $this->getTooltipKey($group);
        }

        $this->pageHelper->addContent($this->view->vueComponent('vue-groups', 'Groups', [
                'groups' => $groups,
                'nav' => $this->getSideMenuData('=' . $parent),
                'isGlobalWorkingGroup' => $parent === RegionIDs::GLOBAL_WORKING_GROUPS
        ]));
    }

    /**
     * Returns the translation key of the tooltip text that is shown for working groups with special
     * functions. Returns null if the group does not have any function.
     */
    private function getTooltipKey(array $group): ?string
    {
        // working group function that can be present in any region
        // TODO: remove the exception when the FS-management group is implemented
        if (!empty($group['function']) && $group['function'] !== WorkgroupFunction::FSMANAGEMENT) {
            return 'group.function.tooltip_function_' . $group['function'];
        }

        // special permissions for unique super-regional groups
        if (RegionIDs::hasSpecialPermission($group['id'])) {
            return 'group.unique_function.tooltip_function_region' . $group['id'];
        }

        return null;
    }

    private function getGroups(int $parent, array $applications, array $stats): array
    {
        $insertLeaderImage = fn (array $leader): array => array_merge($leader, ['image' => $this->imageService->img($leader['photo'])]);
        $enrichGroupData = function (array $group) use ($insertLeaderImage, $applications, $stats): array {
            $leaders = array_map($insertLeaderImage, $group['leaders']);
            $satisfied = $this->workGroupPermissions->fulfillApplicationRequirements($group, $stats);

            $memberCount = count($group['members']);
            $image = $this->fixPhotoPath($group['photo']);
            unset($group['photo']);
            unset($group['members']);

            return array_merge($group, [
                'leaders' => $leaders,
                'image' => $image,
                'membersCount' => $memberCount,
                'appliedFor' => in_array($group['id'], $applications),
                'applicationRequirementsNotFulfilled' => !$satisfied,
                'mayEdit' => $this->workGroupPermissions->mayEdit($group),
                'mayAccess' => $this->workGroupPermissions->mayAccess($group),
                'mayApply' => $this->workGroupPermissions->mayApply($group, $applications, $stats),
                'mayJoin' => $this->workGroupPermissions->mayJoin($group),
            ]);
        };

        return array_map($enrichGroupData, $this->workGroupGateway->listGroups($parent));
    }

    private function edit(Request $request, Response $response): void
    {
        $groupId = $request->query->getInt('id');
        $group = $this->workGroupGateway->getGroup($groupId);
        if (!$group) {
            $this->routeHelper->goAndExit('/?page=groups');
        } elseif ($group['type'] != UnitType::WORKING_GROUP || !$this->workGroupPermissions->mayEdit($group)) {
            $this->routeHelper->goAndExit('/dashboard');
        }

        $bread = $this->translator->trans('group.edit.title', ['{group}' => $group['name']]);
        $this->pageHelper->addBread($bread, '/?page=groups&sub=edit&id=' . (int)$group['id']);

        $group['photo'] = $this->fixPhotoPath($group['photo']);

        $response->setContent($this->render('pages/WorkGroup/edit.twig',
            ['nav' => $this->getSideMenuData(), 'group' => $group]
        ));
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
