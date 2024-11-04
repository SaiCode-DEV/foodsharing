<?php

namespace Foodsharing\Modules\Basket;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Basket\DTO\Basket;
use Foodsharing\Modules\Basket\DTO\BasketForOwnerMenu;
use Foodsharing\Modules\Basket\DTO\BasketRequest;
use Foodsharing\Modules\Core\DBConstants\Uploads\UploadUsage;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Modules\Uploads\UploadsGateway;

class BasketTransactions
{
    public function __construct(
        private readonly BasketGateway $basketGateway,
        private readonly UploadsGateway $uploadsGateway,
        private readonly Session $session,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
    ) {
    }

    /**
     * Adds a new basket and returns its id. This takes care of tagging the uploaded picture.
     *
     * @param Basket $basket The basket's data that was submitted by the client
     */
    public function addBasket(Basket $basket): int
    {
        $basket->id = $this->basketGateway->addBasket($basket, $this->currentUserUnits->getCurrentRegionId() ?? 0, $this->session->id());
        $this->tagUploadedImages($basket);

        return $basket->id;
    }

    /**
     * Updates properties of a basket. This takes care of tagging the uploaded picture and removing the old one, in case
     * the picture was changed.
     *
     * @param Basket $basket The basket's data that was submitted by the client
     */
    public function editBasket(int $basketId, Basket $basket): void
    {
        $this->basketGateway->editBasket($basketId, $basket, $this->session->id());
        $basket->id = $basketId;
        $this->tagUploadedImages($basket);
    }

    private function tagUploadedImages(Basket $basket)
    {
        if ($basket->id && !empty($basket->pictures)) {
            $uuids = array_map(fn ($picture) => substr($picture, 13), $basket->pictures);
            $this->uploadsGateway->setUsage($uuids, UploadUsage::BASKET, $basket->id);
        }
    }

    /**
     * @return array<BasketForOwnerMenu>
     */
    public function getCurrentUsersBaskets(): array
    {
        $requests = $this->basketGateway->getBasketRequestData($this->session->id());
        $baskets = $this->basketGateway->listMyBaskets($this->session->id());

        foreach ($baskets as $basket) {
            $fittingRequests = array_values(array_filter($requests, function ($request) use ($basket) {
                return $request['id'] === $basket->id;
            }));
            $basket->requests = array_map([BasketRequest::class, 'createFromArray'], $fittingRequests);
        }

        return $baskets;
    }
}
