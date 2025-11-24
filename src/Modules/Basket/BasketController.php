<?php

namespace Foodsharing\Modules\Basket;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Core\DBConstants\Basket\Status;
use Foodsharing\Permissions\BasketPermissions;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BasketController extends FoodsharingController
{
    public function __construct(
        private readonly BasketGateway $basketGateway,
        private readonly BasketPermissions $basketPermissions,
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
        $this->pageHelper->addContent($this->prepareVueComponent('BasketFind', 'BasketFind'));

        return $this->renderGlobal();
    }

    #[Route('/essenskoerbe/{id}', name: 'essenskoerbe_id', requirements: ['id' => '\d+'])]
    public function basket(int $id): Response
    {
        $basket = $this->basketGateway->getBasket($id);

        if (!$basket) {
            return $this->redirect('/essenskoerbe/find');
        }

        $requests = null;
        $ownRequest = null;

        if ($this->session->mayRole()) {
            if ($basket->creator->id == $this->session->id()) {
                $requests = $this->basketGateway->listRequests($basket->id);
            } else {
                $ownRequest = $this->basketGateway->getRequest($basket->id, $this->session->id(), $basket->creator->id);
            }
        }
        if ($basket->status === Status::REQUESTED_MESSAGE_READ && $basket->until >= time()) {
            $this->pageHelper->addContent($this->prepareVueComponent('vue-basket-page', 'basket-page', [
                'basket' => $basket,
                'requests' => $requests,
                'hasRequested' => !empty($ownRequest),
                'mayEdit' => $this->basketPermissions->mayEdit($basket->creator->id),
                'mayDelete' => $this->basketPermissions->mayDelete($basket),
                'mayRequest' => $this->basketPermissions->mayRequest($basket->creator->id)
            ]));
        } elseif ($basket->status === Status::DELETED_OTHER_REASON || $basket->status === Status::DENIED || $basket->until <= time()) {
            $this->pageHelper->addContent($this->prepareVueComponent('BasketErrorPage', 'BasketErrorPage', [
                'id' => $basket->id,
            ]));
        }

        return $this->renderGlobal();
    }
}
