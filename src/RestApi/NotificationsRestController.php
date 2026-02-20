<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\UserOptionType;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\FoodSharePoint\FoodSharePointGateway;
use Foodsharing\Modules\FoodSharePoint\FoodSharePointTransactions;
use Foodsharing\Modules\Region\ForumFollowerGateway;
use Foodsharing\Modules\Region\ForumTransactions;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Region\RegionTransactions;
use Foodsharing\Modules\Settings\SettingsGateway;
use Foodsharing\Modules\Settings\SettingsTransactions;
use Foodsharing\RestApi\DTO\Notifications\GeneralNotificationSettings;
use Foodsharing\RestApi\DTO\Notifications\NotificationSetting;
use Foodsharing\RestApi\DTO\Notifications\NotificationSettingsPatch;
use Foodsharing\RestApi\DTO\Notifications\NotificationSettingWithRegion;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag('notifications')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
class NotificationsRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        protected Session $session,
        private readonly ForumFollowerGateway $forumFollowerGateway,
        private readonly FoodSharePointGateway $foodSharePointGateway,
        private readonly RegionGateway $regionGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly SettingsGateway $settingsGateway,
        private readonly SettingsTransactions $settingsTransactions,
        private readonly FoodSharePointTransactions $foodSharePointTransactions,
        private readonly ForumTransactions $forumTransactions,
        private readonly RegionTransactions $regionTransactions,
    ) {
        parent::__construct($session);
    }

    #[OA\Get(summary: 'Get the general notification settings')]
    #[Route('notifications', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: GeneralNotificationSettings::class))]
    public function getGeneralNotificationSettings()
    {
        $this->assertLoggedIn();

        $settings = new GeneralNotificationSettings();
        $subscriptions = $this->foodsaverGateway->getSubscriptions($this->session->id());
        $settings->emailOnChatMessage = boolval($subscriptions['infomail_message']);
        $settings->emailOnNewsletter = boolval($subscriptions['newsletter']);
        $settings->bellOnMention = !$this->settingsTransactions->getOption(UserOptionType::DISABLE_MENTION_NOTIFICATION);
        if ($this->session->mayRole(Role::STORE_MANAGER)) {
            $settings->emailOnStoreManagerPickupReminder = !$this->settingsTransactions->getOption(UserOptionType::DISABLE_PICKUP_REMINDER);
        }

        return $this->respondOK($settings);
    }

    #[OA\Patch(summary: 'Update the general notification settings')]
    #[Route('notifications', methods: ['PATCH'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    public function patchGeneralNotificationSettings(#[MapRequestPayload] GeneralNotificationSettings $settings)
    {
        $this->assertLoggedIn();

        if (!is_null($settings->emailOnChatMessage)) {
            $this->settingsGateway->updateEmailOnChatMessageSetting($this->session->id(), $settings->emailOnChatMessage);
        }
        if (!is_null($settings->emailOnNewsletter)) {
            $this->settingsGateway->updateNewsletterSetting($this->session->id(), $settings->emailOnNewsletter);
        }
        if (!is_null($settings->bellOnMention)) {
            $this->settingsTransactions->setOption(UserOptionType::DISABLE_MENTION_NOTIFICATION, intval(!$settings->bellOnMention));
        }
        if (!is_null($settings->emailOnStoreManagerPickupReminder) && $this->session->mayRole(Role::STORE_MANAGER)) {
            $this->settingsTransactions->setOption(UserOptionType::DISABLE_PICKUP_REMINDER, intval(!$settings->emailOnStoreManagerPickupReminder));
        }

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Get the threads notification settings')]
    #[Route('notifications/threads', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'array',
        items: new OA\Items(ref: new Model(type: NotificationSettingWithRegion::class))
    ))]
    public function getThreadsNotificationSettings()
    {
        $this->assertLoggedIn();

        $settings = $this->forumFollowerGateway->getThreadsNotificationSettings($this->session->id());

        return $this->respondOK($settings);
    }

    #[OA\Patch(summary: 'Update the threads notification settings')]
    #[Route('notifications/threads', methods: ['PATCH'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function patchThreadsNotificationSettings(#[MapRequestPayload] NotificationSettingsPatch $settings)
    {
        $this->assertLoggedIn();

        $this->forumTransactions->updateThreadsNotifications($this->session->id(), $settings);

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Get the food share points notification settings')]
    #[Route('notifications/food-share-points', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'array',
        items: new OA\Items(ref: new Model(type: NotificationSetting::class))
    ))]
    public function getFoodSharePointsNotificationSettings()
    {
        $this->assertLoggedIn();

        $settings = $this->foodSharePointGateway->getFoodSharePointsNotificationSettings($this->session->id());

        return $this->respondOK($settings);
    }

    #[OA\Patch(summary: 'Update the food share points notification settings')]
    #[Route('notifications/food-share-points', methods: ['PATCH'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid notification pattern (currently it is not possible to revieve emails without bells).')]
    public function patchFoodSharePointsNotificationSettings(#[MapRequestPayload] NotificationSettingsPatch $settings)
    {
        $this->assertLoggedIn();

        $this->foodSharePointTransactions->updateFoodSharePointsNotifications($this->session->id(), $settings);

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Get the region notification settings')]
    #[Route('notifications/regions', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'array',
        items: new OA\Items(ref: new Model(type: NotificationSetting::class))
    ))]
    public function getRegionsNotificationSettings(#[MapQueryParameter] ?bool $groups = null)
    {
        $this->assertLoggedIn();
        $groups ??= false;

        $settings = $this->regionGateway->getRegionsNotificationSettings($this->session->id(), $groups);

        return $this->respondOK($settings);
    }

    #[OA\Patch(summary: 'Update the regions notification settings')]
    #[Route('notifications/regions', methods: ['PATCH'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function patchRegionsNotificationSettings(#[MapRequestPayload] NotificationSettingsPatch $settings)
    {
        $this->assertLoggedIn();

        $this->regionTransactions->updateRegionNotification($this->session->id(), $settings);

        return $this->respondOK();
    }
}
