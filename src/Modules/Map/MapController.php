<?php

namespace Foodsharing\Modules\Map;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MapController extends FoodsharingController
{
    public function __construct(
        private readonly MapGateway $mapGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
    ) {
        parent::__construct();
    }

    #[Route('/karte', 'karte')]
    public function index(Request $request): Response
    {
        $this->pageHelper->addTitle($this->translator->trans('map.title'));

        $ambassadorRegions = [];
        if (!empty($this->currentUserUnits->isAdminFor(null))) {
            $ambassadorRegions = $this->foodsaverGateway->getAmbassadorsRegions($this->session->id(), true);
        }

        $params = [
            'maySeeStores' => $this->session->mayRole(Role::FOODSAVER),
            'ambassadorRegions' => $ambassadorRegions,
        ];

        if ($this->session->mayRole(Role::FOODSAVER) && $request->query->has('bid')) {
            $storeId = intval($request->query->get('bid'));
            $location = $this->mapGateway->getStoreLocation($storeId);
            if (!empty($location)) {
                $params['center'] = $location;
                $params['selectedStoreId'] = $storeId;
            }
        } elseif ($request->query->has('fspId')) {
            $foodSharePointId = intval($request->query->get('fspId'));
            $location = $this->mapGateway->getFoodSharePointLocation($foodSharePointId);
            if (!empty($location)) {
                $params['center'] = $location;
                $params['selectedFoodSharePointId'] = $foodSharePointId;
            }
        } elseif ($request->query->has('lat') && $request->query->has('lon')) {
            $params['center'] = [
                'lat' => floatval($request->query->get('lat')),
                'lon' => floatval($request->query->get('lon')),
            ];
        }
        if ($request->query->has('zoom')) {
            $params['initialZoom'] = intval($request->query->get('zoom'));
        }
        if ($request->query->has('loadMarkers')) {
            $params['loadMarkers'] = $request->query->get('loadMarkers');
        }

        $this->pageHelper->addContent($this->prepareVueComponent('map-page', 'MapPage', $params));

        return $this->renderGlobal('layouts/no-footer.twig');
    }
}
