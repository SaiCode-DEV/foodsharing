<?php

namespace Foodsharing\Modules\Foodsaver;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Basket\BasketGateway;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Uploads\UploadUsage;
use Foodsharing\Modules\Quiz\QuizSessionGateway;
use Foodsharing\Modules\Store\StoreTransactions;
use Foodsharing\Modules\Uploads\UploadsGateway;
use Foodsharing\Modules\Uploads\UploadsTransactions;

class FoodsaverTransactions
{
    public function __construct(
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly QuizSessionGateway $quizSessionGateway,
        private readonly BasketGateway $basketGateway,
        private readonly UploadsGateway $uploadsGateway,
        private readonly UploadsTransactions $uploadsTransactions,
        private readonly StoreTransactions $storeTransactions,
        private readonly Session $session
    ) {
    }

    public function downgradeAndBlockForQuizPermanently(int $fsId): int
    {
        $this->quizSessionGateway->blockUserForQuiz($fsId, Role::FOODSAVER->value);

        $this->storeTransactions->leaveAllStoreTeams($fsId);

        return $this->foodsaverGateway->downgradePermanently($fsId);
    }

    public function deleteFoodsaver(int $foodsaverId, ?string $reason): void
    {
        // set all active baskets of the user to deleted
        $this->basketGateway->removeActiveUserBaskets($foodsaverId);

        $this->storeTransactions->leaveAllStoreTeams($foodsaverId);

        $this->deletePhoto($foodsaverId);

        // delete the user
        $this->foodsaverGateway->deleteFoodsaver($foodsaverId, $this->session->id(), $reason);
    }

    /**
     * Sets a previously uploaded photo as the user's new profile photo. Deletes the previous profile photo, if there
     * was any.
     *
     * @param int $foodsaverId the user's id
     * @param string $uuid the UUID of the uploaded file
     */
    public function updatePhoto(int $foodsaverId, string $uuid): void
    {
        // Delete the old photo, if there was one
        $this->deletePhoto($foodsaverId);

        // Set the new profile photo and its upload usage
        $this->foodsaverGateway->updatePhoto($this->session->id(), '/api/uploads/' . $uuid);
        $this->uploadsGateway->setUsage([$uuid], UploadUsage::PROFILE_PHOTO, $foodsaverId);
    }

    /**
     * Removes the photo from a user's profile and deletes all corresponding files.
     *
     * @param int $foodsaverId the user's id
     */
    public function deletePhoto(int $foodsaverId): void
    {
        $photo = $this->foodsaverGateway->getPhotoFileName($foodsaverId);
        if (!empty($photo)) {
            if (str_starts_with($photo, '/api/uploads/')) {
                $oldUUID = substr($photo, 13);
                $this->uploadsTransactions->deleteUploadedFile($oldUUID);
            } else {
                // Delete all resized files of the old picture
                $oldFormats = ['', '130_q_', '50_q_', 'med_q_', 'mini_q_', 'thumb_', 'thumb_crop_', 'q_'];
                foreach ($oldFormats as $format) {
                    @unlink('./images/' . $format . $photo);
                }
            }
        }
    }
}
