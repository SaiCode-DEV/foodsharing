<?php

namespace Foodsharing\Modules\Basket;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Core\DBConstants\Basket\Status;
use Foodsharing\Modules\Core\DBConstants\Map\MapConstants;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BasketController extends FoodsharingController
{
    public function __construct(
        private readonly BasketView $view,
        private readonly BasketGateway $basketGateway,
    ) {
        parent::__construct();
    }

    #[Route('/essenskoerbe', name: 'essenskoerbe')]
    public function index(): Response
    {
        return $this->redirect('/essenskoerbe/find');
    }

    #[Route('/essenskoerbe/find', name: 'essenskoerbe_find')]
    public function find(): Response
    {
        $this->pageHelper->addBread($this->translator->trans('terminology.baskets'));

        $loc = $this->session->user('location');
        if (!$loc || $loc->lat === 0 && $loc->lon === 0) {
            $loc = GeoLocation::createFromArray(['lat' => MapConstants::CENTER_GERMANY_LAT, 'lon' => MapConstants::CENTER_GERMANY_LON]);
            $zoom = MapConstants::ZOOM_COUNTRY;
        } else {
            $zoom = MapConstants::ZOOM_CITY;
        }
        $baskets = $this->basketGateway->listNearbyBasketsByDistance($this->session->id(), $loc);
        $this->view->find($baskets, $loc, $zoom);

        return $this->renderGlobal();
    }

    #[Route('/essenskoerbe/{id}', name: 'essenskoerbe_id', requirements: ['id' => '\d+'])]
    public function basket(int $id): Response
    {
        $basket = $this->basketGateway->getBasket($id);

        if (!$basket) {
            return $this->redirect('/essenskoerbe/find');
        }

        $this->pageHelper->addBread($this->translator->trans('terminology.baskets'));

        $requests = false;

        if ($this->session->mayRole()) {
            if ($basket->creator->id == $this->session->id()) {
                $requests = $this->basketGateway->listRequests($basket->id);
            } else {
                $requests = $this->basketGateway->getRequest($basket->id, $this->session->id(), $basket->creator->id);
            }
        }
        if ($basket->status === Status::REQUESTED_MESSAGE_READ && $basket->until >= time()) {
            $this->view->basket($basket, $requests);
        } elseif ($basket->status === Status::DELETED_OTHER_REASON || $basket->status === Status::DENIED || $basket->until <= time()) {
            $this->view->basketTaken($basket);
        }

        return $this->renderGlobal();
    }
}
