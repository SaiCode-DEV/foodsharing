<?php

namespace Foodsharing\Modules\FoodSharePoint;

use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Core\DBConstants\Info\InfoType;
use Foodsharing\RestApi\Models\Notifications\FoodSharePoint;
use Foodsharing\Utility\EmailHelper;
use Foodsharing\Utility\Sanitizer;
use Symfony\Contracts\Translation\TranslatorInterface;

class FoodSharePointTransactions
{
    private FoodSharePointGateway $foodSharePointGateway;
    private BellGateway $bellGateway;
    private EmailHelper $emailHelper;
    private Sanitizer $sanitizer;
    private TranslatorInterface $translator;

    public function __construct(
        FoodSharePointGateway $foodSharePointGateway,
        BellGateway $bellGateway,
        EmailHelper $emailHelper,
        Sanitizer $sanitizer,
        TranslatorInterface $translator
    ) {
        $this->foodSharePointGateway = $foodSharePointGateway;
        $this->bellGateway = $bellGateway;
        $this->emailHelper = $emailHelper;
        $this->sanitizer = $sanitizer;
        $this->translator = $translator;
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
}
