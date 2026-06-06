<?php

namespace Foodsharing\Modules\WorkGroup;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Region\RegionGateway;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WorkGroupController extends FoodsharingController
{
    public function __construct(
        private readonly RegionGateway $regionGateway,
    ) {
        parent::__construct();
    }

    #[Route('/groups', name: 'groups')]
    public function index(Request $request): Response
    {
        if (!$this->session->mayRole()) {
            $this->routeHelper->goLoginAndExit();
        }

        $region_id = $request->query->getInt('p');
        if ($region_id) {
            $parent = $this->regionGateway->getRegionName($region_id);
            $this->pageHelper->addBread($parent, '/region?bid=' . $region_id);

            // Check if the user has access to the groups page (if they are a member
            // of the parent region). If not, redirect.
            if (!$this->currentUserUnits->mayBezirk($region_id)) {
                return $this->redirect('/region/denied/' . $region_id);
            }
        }
        $this->pageHelper->addBread($this->translator->trans('terminology.groups'), '/?page=groups');

        return match ($request->query->get('sub')) {
            'edit' => $this->redirectToRoute('region', ['sub' => 'edit', 'bid' => $request->query->getInt('bid')]),
            null => $this->list($request),
            default => $this->renderGlobal(),
        };
    }

    private function list(Request $request): Response
    {
        $this->pageHelper->addTitle($this->translator->trans('terminology.groups'));

        $parent = $request->query->getInt('p', RegionIDs::GLOBAL_WORKING_GROUPS);

        $this->pageHelper->addContent($this->prepareVueComponent('vue-groups', 'Groups', ['regionId' => $parent]));

        return $this->renderGlobal();
    }
}
