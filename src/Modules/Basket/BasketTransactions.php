<?php

namespace Foodsharing\Modules\Basket;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Basket\DTO\Basket;
use Foodsharing\Modules\Basket\DTO\BasketForOwnerMenu;
use Foodsharing\Modules\Basket\DTO\BasketRequest;
use Foodsharing\Modules\Core\DBConstants\Uploads\UploadUsage;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Modules\Uploads\UploadsGateway;
use Foodsharing\Permissions\UploadsPermissions;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BasketTransactions
{
    public function __construct(
        private readonly BasketGateway $basketGateway,
        private readonly UploadsGateway $uploadsGateway,
        private readonly UploadsPermissions $uploadsPermissions,
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
        $this->tagUploadedImages($basket);
        $basket->id = $this->basketGateway->addBasket($basket, $this->currentUserUnits->getCurrentRegionId() ?? 0, $this->session->id());

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
        $this->tagUploadedImages($basket);
        $this->basketGateway->editBasket($basketId, $basket, $this->session->id());
        $basket->id = $basketId;
    }

    private function tagUploadedImages(Basket $basket)
    {
        if ($basket->id && !empty($basket->pictures)) {
            $uuids = array_map(fn ($picture) => substr((string)$picture, 13), $basket->pictures);

            // Check that the user is allowed to use all pictures in the basket
            foreach ($uuids as $uuid) {
                if (!$this->uploadsPermissions->maySetUploadUsage($uuid)) {
                    throw new AccessDeniedHttpException('Invalid upload UUID');
                }
            }

            $this->uploadsGateway->setUsage($uuids, UploadUsage::BASKET, $basket->id);
        }
    }

    /**
     * @return array<BasketForOwnerMenu>
     */
    public function getCurrentUsersBaskets(): array
    {
        $baskets = $this->basketGateway->listMyBaskets($this->session->id());
        if (empty($baskets)) {
            return $baskets;
        }

        $requests = $this->basketGateway->getBasketRequestData($this->session->id());
        foreach ($baskets as $basket) {
            $fittingRequests = array_values(array_filter($requests, fn ($request) => $request['id'] === $basket->id));
            $basket->requests = array_map(BasketRequest::createFromArray(...), $fittingRequests);
        }

        return $baskets;
    }
}
