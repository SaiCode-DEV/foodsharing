<?php

namespace Foodsharing\Modules\FoodSharePoint;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\FoodSharePoint\FollowerType;
use Foodsharing\Modules\Core\DBConstants\Uploads\UploadUsage;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Uploads\UploadsGateway;
use Foodsharing\Modules\Uploads\UploadsTransactions;
use Foodsharing\Permissions\FoodSharePointPermissions;
use Foodsharing\RestApi\DTO\Notifications\NotificationSettingsPatch;
use Foodsharing\RestApi\Models\FoodSharePoint\AddFoodSharePointResponse;
use Foodsharing\RestApi\Models\FoodSharePoint\FoodSharePointDetails;
use Foodsharing\RestApi\Models\FoodSharePoint\FoodSharePointEditData;
use Foodsharing\RestApi\Models\FoodSharePoint\FoodSharePointForCreation;
use Foodsharing\RestApi\Models\FoodSharePoint\FoodSharePointPermission;
use Foodsharing\Utility\EmailHelper;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class FoodSharePointTransactions
{
    public function __construct(
        private readonly FoodSharePointGateway $foodSharePointGateway,
        private readonly FoodSharePointPermissions $foodSharePointPermissions,
        private readonly UploadsGateway $uploadsGateway,
        private readonly UploadsTransactions $uploadsTransactions,
        private readonly EmailHelper $emailHelper,
        private readonly TranslatorInterface $translator,
        private readonly RegionGateway $regionGateway,
        private readonly Session $session
    ) {
    }

    public function sendNewFoodSharePointMailNotifications(int $foodSharePointId): void
    {
        if ($foodSharePoint = $this->foodSharePointGateway->getFoodSharePoint($foodSharePointId)) {
            $post = $this->foodSharePointGateway->getLastFoodSharePointPost($foodSharePointId);
            if ($followers = $this->foodSharePointGateway->getEmailFollower($foodSharePointId)) {
                $body = nl2br((string)$post['body']);

                if (!empty($post['attach'])) {
                    $attach = json_decode((string)$post['attach'], true);
                    if (isset($attach['image']) && !empty($attach['image'])) {
                        foreach ($attach['image'] as $img) {
                            $body .= '
							<div>
								<img src="' . BASE_URL . '/images/wallpost/medium_' . $img['file'] . '" />
							</div>';
                        }
                    }
                }

                $followersWithoutPostAuthor = array_filter($followers, fn ($x) => $x['id'] !== $post['fs_id']);
                foreach ($followersWithoutPostAuthor as $f) {
                    $this->emailHelper->tplMail('foodSharePoint/new_message', $f['email'], [
                        'link' => BASE_URL . '/fairteiler/' . (int)$foodSharePointId,
                        'name' => $f['name'],
                        'anrede' => $this->translator->trans('salutation.' . $f['geschlecht']),
                        'fairteiler' => $foodSharePoint->name,
                        'post' => $body
                    ]);
                }
            }
        }
    }

    /**
     * Updates the user's notification settings for food share points.
     * @throws BadRequestHttpException when trying to set an invalid notification pattern
     */
    public function updateFoodSharePointsNotifications(int $userId, NotificationSettingsPatch $settings): void
    {
        $changes = [];
        foreach ($settings->notifications as $notificationUpdate) {
            if ($notificationUpdate->bell === false && $notificationUpdate->email === true) {
                throw new BadRequestHttpException('Currently not possible to have email notifications without bells.');
            }
            if (is_null($notificationUpdate->bell) && is_null($notificationUpdate->email)) {
                continue;
            }
            $changes[] = $notificationUpdate;
        }

        $this->foodSharePointGateway->updateFoodSharePointsNotifications($userId, $changes);
    }

    /**
     * Adds a new food share point. If the user is not allowed to add a food share point to that region, it will be
     * suggested and needs to be approved by someone responsible.
     *
     * @param FoodSharePointForCreation $data initial data for the FSP
     * @return AddFoodSharePointResponse information about the created food share point
     */
    public function addFoodSharePoint(FoodSharePointForCreation $data): AddFoodSharePointResponse
    {
        $isProposal = !$this->foodSharePointPermissions->mayAdd($data->regionId);
        $data->picture = $this->uploadsTransactions->fixUUIDForWriting($data->picture);
        $id = $this->foodSharePointGateway->addFoodSharePoint($this->session->id(), $data, $isProposal);

        // If a picture was uploaded for this food share point, its usage type needs to be set
        if (!empty($data->picture)) {
            $uuid = $this->uploadsTransactions->getUUID($data->picture);
            $this->uploadsGateway->setUsage([$uuid], UploadUsage::FOOD_SHARE_POINT_TITLE, $id);
        }

        return new AddFoodSharePointResponse($id, !$isProposal);
    }

    public function editFoodSharePoint(int $foodSharePointId, DTO\FoodSharePoint $currentData, FoodSharePointEditData $newData): void
    {
        $newData->picture = $this->uploadsTransactions->fixUUIDForWriting($newData->picture);
        $this->foodSharePointGateway->updateFoodSharePoint($foodSharePointId, $newData);

        /* If the picture of this food share point was changed, the usage type of the new one (if any) needs to be set
         and the old picture needs to be deleted. */
        $newPicture = $newData->picture ?? '';
        if ($newPicture !== $currentData->picture) {
            if (!empty($currentData->picture)) {
                $oldUUID = $this->uploadsTransactions->getUUID($currentData->picture);
                $this->uploadsTransactions->deleteUploadedFile($oldUUID);
            }

            if (!empty($newPicture)) {
                $uuid = $this->uploadsTransactions->getUUID($newPicture);
                $this->uploadsGateway->setUsage([$uuid], UploadUsage::FOOD_SHARE_POINT_TITLE, $foodSharePointId);
            }
        }

        $this->foodSharePointGateway->updateFSPManagers($currentData->id, $newData->managerIds);
    }

    public function deleteFoodSharePoint(int $foodSharePointId): void
    {
        // Delete the food share point's title picture
        $foodSharePoint = $this->foodSharePointGateway->getFoodSharePoint($foodSharePointId);
        if (!empty($foodSharePoint->picture)) {
            $uuid = $this->uploadsTransactions->getUUID($foodSharePoint->picture);
            $this->uploadsTransactions->deleteUploadedFile($uuid);
        }

        $this->foodSharePointGateway->deleteFoodSharePoint($foodSharePointId);
    }

    /**
     * Assumes that the given Id exists. This needs to be asserted beforehand.
     */
    public function getFoodSharePointDetails(int $foodSharePointId): FoodSharePointDetails
    {
        $foodSharePoint = $this->foodSharePointGateway->getFoodSharePoint($foodSharePointId);

        $fspDetails = FoodSharePointDetails::create(
            $foodSharePoint->id,
            $foodSharePoint->name,
            $foodSharePoint->regionId,
            $foodSharePoint->picture,
            $foodSharePoint->status,
            $foodSharePoint->description,
            $foodSharePoint->address,
            $foodSharePoint->location,
            $foodSharePoint->createdAt,
            $foodSharePoint->creator,
        );
        $fspDetails->regionName = $this->regionGateway->getRegionName($foodSharePoint->regionId);
        $fspDetails->followerCount = $this->foodSharePointGateway->getFollowerCount($foodSharePointId);
        $fspDetails->managers = $this->foodSharePointGateway->getManagers($foodSharePointId);

        return $fspDetails;
    }

    public function getPermission(int $foodSharePointId): ?FoodSharePointPermission
    {
        $regionId = $this->foodSharePointGateway->getFoodSharePoint($foodSharePointId)->regionId;
        $permission = new FoodSharePointPermission();
        if ($this->session->id()) {
            $followerType = $this->foodSharePointGateway->getFollowerStatus($foodSharePointId, $this->session->id());
            $permission->isFollower = $followerType >= FollowerType::FOLLOWER;
            $permission->mayEdit = $this->foodSharePointPermissions->mayEditFromManager($regionId, $followerType === FollowerType::FOOD_SHARE_POINT_MANAGER);
            $permission->mayDelete = $this->foodSharePointPermissions->mayDeleteFoodSharePointOfRegion($regionId);
        }

        return $permission;
    }
}
