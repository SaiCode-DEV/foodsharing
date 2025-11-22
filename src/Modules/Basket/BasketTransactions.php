<?php

namespace Foodsharing\Modules\Basket;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Basket\DTO\Basket;
use Foodsharing\Modules\Basket\DTO\BasketForOwnerMenu;
use Foodsharing\Modules\Basket\DTO\BasketRequest;
use Foodsharing\Modules\Core\DBConstants\Uploads\UploadUsage;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Modules\Uploads\UploadsGateway;
use Foodsharing\Modules\Uploads\UploadsTransactions;
use Foodsharing\Permissions\UploadsPermissions;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BasketTransactions
{
    public function __construct(
        private readonly BasketGateway $basketGateway,
        private readonly UploadsGateway $uploadsGateway,
        private readonly UploadsPermissions $uploadsPermissions,
        private readonly UploadsTransactions $uploadsTransactions,
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
        $this->checkUploadedImagePermission($basket->pictures);
        $basket->id = $this->basketGateway->addBasket($basket, $this->currentUserUnits->getCurrentRegionId() ?? 0, $this->session->id());
        $this->tagUploadedImages($basket->id, $basket->pictures);

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
        $oldPictures = $this->basketGateway->getBasket($basketId)->pictures;
        $removedPictures = array_diff($oldPictures, $basket->pictures);
        $addedPictures = array_diff($basket->pictures, $oldPictures);

        /* Check permissions for all new pictures. The permission check would fail for the old pictures because they
         have already been tagged with a usage id. */
        $this->checkUploadedImagePermission($addedPictures);

        // Remove all old pictures that are not in the edited basket anymore
        $removedUuids = array_map(fn ($picture) => substr((string)$picture, 13), $removedPictures);
        foreach ($removedUuids as $uuid) {
            $this->uploadsTransactions->deleteUploadedFile($uuid);
        }

        // Save the basket
        $this->basketGateway->editBasket($basketId, $basket, $this->session->id());
        $basket->id = $basketId;

        // Tag only the new pictures
        $this->tagUploadedImages($basketId, $addedPictures);
    }

    /**
     * Check that the user is allowed to use all pictures in the list. The check needs to be done first to prevent any
     * changes in case the user does not have permission.
     *
     * @throws AccessDeniedHttpException if the user can not at least one of the pictures
     */
    private function checkUploadedImagePermission(array $pictures): void
    {
        if (!empty($pictures)) {
            $uuids = array_map(fn ($picture) => substr((string)$picture, 13), $pictures);
            foreach ($uuids as $uuid) {
                if (!$this->uploadsPermissions->maySetUploadUsage($uuid)) {
                    throw new AccessDeniedHttpException('Invalid upload UUID');
                }
            }
        }
    }

    /**
     * Adds the usage type and usage id to all the pictures in the array. This can only be done once the basket has an
     * id.
     */
    private function tagUploadedImages(int $basketId, array $pictures): void
    {
        if ($basketId && !empty($pictures)) {
            $uuids = array_map(fn ($picture) => substr((string)$picture, 13), $pictures);
            $this->uploadsGateway->setUsage($uuids, UploadUsage::BASKET, $basketId);
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
