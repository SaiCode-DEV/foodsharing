<?php

namespace Foodsharing\Modules\Map;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MapController extends FoodsharingController
{
    public function __construct(
        private readonly MapGateway $mapGateway,
    ) {
        parent::__construct();
    }

    #[Route('/karte', 'karte')]
    public function index(Request $request): Response
    {
        $this->pageHelper->addTitle($this->translator->trans('map.title'));

        $params = [
            'maySeeStores' => $this->session->mayRole(Role::FOODSAVER)
        ];

        if ($this->session->mayRole(Role::FOODSAVER) && $request->query->has('bid')) {
            $storeId = intval($request->query->get('bid'));
            $params['center'] = $this->mapGateway->getStoreLocation($storeId);
            $params['selectedStoreId'] = $storeId;
        } elseif ($request->query->has('fspId')) {
            $foodSharePointId = intval($request->query->get('fspId'));
            $params['center'] = $this->mapGateway->getFoodSharePointLocation($foodSharePointId);
            $params['selectedFoodSharePointId'] = $foodSharePointId;
        }

        $this->pageHelper->addContent($this->prepareVueComponent('map-page', 'MapPage', $params));

        return $this->renderGlobal('layouts/map.twig');
    }
}
