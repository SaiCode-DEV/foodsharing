<?php

namespace Foodsharing\Modules\Basket;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Basket\DTO\Basket;
use Foodsharing\Modules\Core\DBConstants\Uploads\UploadUsage;
use Foodsharing\Modules\Uploads\UploadsGateway;

class BasketTransactions
{
    public function __construct(
        private readonly BasketGateway $basketGateway,
        private readonly UploadsGateway $uploadsGateway,
        private readonly Session $session,
    ) {
    }

    /**
     * Adds a new basket and returns its id. This takes care of tagging the uploaded picture.
     *
     * @param Basket $basket The basket's data that was submitted by the client
     */
    public function addBasket(Basket $basket): int
    {
        $basketId = $this->basketGateway->addBasket($basket, $this->session->user('bezirk_id'), $this->session->id());

        if ($basketId && !empty($basket->imageUrl)) {
            $uuid = substr($basket->imageUrl, 13);
            $this->uploadsGateway->setUsage([$uuid], UploadUsage::BASKET, $basketId);
        }

        return $basketId;
    }

    /**
     * Updates properties of a basket. This takes care of tagging the uploaded picture and removing the old one, in case
     * the picture was changed.
     *
     * @param Basket $basket The basket's data that was submitted by the client
     */
    public function editBasket(int $basketId, Basket $basket): void
    {
        $result = $this->basketGateway->editBasket($basketId, $basket, $this->session->id());

        if ($result > 0 && !empty($basket->imageUrl)) {
            $uuid = substr($basket->imageUrl, 13);
            $this->uploadsGateway->setUsage([$uuid], UploadUsage::BASKET, $basketId);
        }
    }
}
