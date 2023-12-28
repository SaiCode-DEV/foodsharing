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
        private readonly MapView $view
    ) {
        parent::__construct();
    }

    #[Route('/karte', 'karte')]
    public function index(Request $request): Response
    {
        $this->pageHelper->addTitle($this->translator->trans('map.title'));

        if ($this->session->mayRole()) {
            $center = $this->mapGateway->getFoodsaverLocation($this->session->id());
        }
        $this->pageHelper->addContent($this->view->mapControl(), CNT_TOP);

        $jsarr = '';
        if ($request->query->has('load') && $request->query->get('load') == 'baskets') {
            $jsarr = '["baskets"]';
        } elseif ($request->query->has('load') && $request->query->get('load') == 'fairteiler') {
            $jsarr = '["fairteiler"]';
        }

        $this->pageHelper->addContent(
            $this->view->lMap()
        );

        if ($this->session->mayRole(Role::FOODSAVER) && $request->query->has('bid')) {
            $storeId = intval($request->query->get('bid'));
            $center = $this->mapGateway->getStoreLocation($storeId);
            $this->pageHelper->addJs('ajreq(\'bubble\', { app: \'store\', id: ' . $storeId . ' });');
        }

        $this->pageHelper->addJs('u_init_map();');

        if (!empty($center)) {
            if ($center['lat'] == 0 && $center['lon'] == 0) {
                $this->pageHelper->addJs('u_map.fitBounds([[46.0, 4.0],[55.0, 17.0]]);');
            } else {
                $this->pageHelper->addJs('u_map.setView([' . $center['lat'] . ',' . $center['lon'] . '],15);');
            }
        }

        $this->pageHelper->addJs('map.initMarker(' . $jsarr . ');');

        return $this->renderGlobal('layouts/map.twig');
    }
}
