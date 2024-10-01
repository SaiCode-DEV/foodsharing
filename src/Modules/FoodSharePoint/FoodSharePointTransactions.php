<?php

namespace Foodsharing\Modules\FoodSharePoint;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Core\DBConstants\Info\InfoType;
use Foodsharing\Modules\Core\DBConstants\Uploads\UploadUsage;
use Foodsharing\Modules\Uploads\UploadsGateway;
use Foodsharing\Permissions\FoodSharePointPermissions;
use Foodsharing\RestApi\Models\FoodSharePoint\AddFoodSharePointResponse;
use Foodsharing\RestApi\Models\FoodSharePoint\FoodSharePointForCreation;
use Foodsharing\RestApi\Models\Notifications\FoodSharePoint;
use Foodsharing\Utility\EmailHelper;
use Foodsharing\Utility\Sanitizer;
use Symfony\Contracts\Translation\TranslatorInterface;

class FoodSharePointTransactions
{
    public function __construct(
        private readonly FoodSharePointGateway $foodSharePointGateway,
        private readonly FoodSharePointPermissions $foodSharePointPermissions,
        private readonly BellGateway $bellGateway,
        private readonly UploadsGateway $uploadsGateway,
        private readonly EmailHelper $emailHelper,
        private readonly Sanitizer $sanitizer,
        private readonly TranslatorInterface $translator,
        private readonly Session $session
    ) {
    }

    public function sendNewFoodSharePointPostNotifications(int $foodSharePointId): void
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
                        'link' => BASE_URL . '/?page=fairteiler&sub=ft&id=' . (int)$foodSharePointId,
                        'name' => $f['name'],
                        'anrede' => $this->translator->trans('salutation.' . $f['geschlecht']),
                        'fairteiler' => $foodSharePoint['name'],
                        'post' => $body
                    ]);
                }
            }

            if ($followers = $this->foodSharePointGateway->getInfoFollowerIds($foodSharePointId)) {
                $followersWithoutPostAuthor = array_diff($followers, [$post['fs_id']]);
                $bellData = Bell::create(
                    'ft_update_title',
                    'ft_update',
                    'fas fa-recycle',
                    ['href' => '/?page=fairteiler&sub=ft&id=' . $foodSharePointId],
                    ['name' => $foodSharePoint['name'], 'user' => $post['fs_name'], 'teaser' => $this->sanitizer->tt($post['body'], 100)],
                    BellType::createIdentifier(BellType::FOOD_SHARE_POINT_POST, $foodSharePointId)
                );
                $this->bellGateway->addBell($followersWithoutPostAuthor, $bellData);
            }
        }
    }

    /**
     * Updates the user's notification settings for a list of food share points individually.
     *
     * @param FoodSharePoint[] $foodSharePoints
     */
    public function updateFoodSharePointNotifications(int $userId, array $foodSharePoints): void
    {
        foreach ($foodSharePoints as $foodSharePoint) {
            $foodSharePointIdsToUnfollow = [];

            if ($foodSharePoint->infotype == InfoType::NONE) {
                $foodSharePointIdsToUnfollow[] = $foodSharePoint->id;
            }
            $this->foodSharePointGateway->updateInfoType($userId, $foodSharePoint->id, $foodSharePoint->infotype);
        }

        if (!empty($foodSharePointIdsToUnfollow)) {
            $this->foodSharePointGateway->unfollowFoodSharePoints($userId, $foodSharePointIdsToUnfollow);
        }
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
        $id = $this->foodSharePointGateway->addFoodSharePoint($this->session->id(), $data, $isProposal);

        // If a picture was uploaded for this food share point, its usage type needs to be set
        if (!empty($data->picture)) {
            $uuid = substr($data->picture, 13);
            $this->uploadsGateway->setUsage([$uuid], UploadUsage::FOOD_SHARE_POINT_TITLE, $id);
        }

        return new AddFoodSharePointResponse($id, !$isProposal);
    }
}
