<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\FoodSharePoint\FoodSharePointGateway;
use Foodsharing\Modules\FoodSharePoint\FoodSharePointTransactions;
use Foodsharing\Modules\Region\ForumFollowerGateway;
use Foodsharing\Modules\Region\ForumTransactions;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Region\RegionTransactions;
use Foodsharing\Modules\Settings\SettingsGateway;
use Foodsharing\RestApi\Models\Notifications\FoodSharePoint;
use Foodsharing\RestApi\Models\Notifications\NewsletterChat;
use Foodsharing\RestApi\Models\Notifications\Region;
use Foodsharing\RestApi\Models\Notifications\Thread;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Tag;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class NotificationsController extends AbstractFOSRestController
{
    public function __construct(
        private readonly Session $session,
        private readonly ForumFollowerGateway $forumFollowerGateway,
        private readonly FoodSharePointGateway $foodSharePointGateway,
        private readonly RegionGateway $regionGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly SettingsGateway $settingsGateway,
        private readonly FoodSharePointTransactions $foodSharePointTransactions,
        private readonly ForumTransactions $forumTransactions,
        private readonly RegionTransactions $regionTransactions
    ) {
    }

    /**
     * Returns notifications for specific target parameter.
     */
    #[Tag('notifications')]
    #[Rest\Get(path: 'notifications/{target}')]
    #[Response(response: HttpResponse::HTTP_OK, description: 'Successful')]
    #[Response(response: HttpResponse::HTTP_FORBIDDEN, description: 'Forbidden')]
    #[Response(response: HttpResponse::HTTP_BAD_REQUEST, description: 'Target not found')]
    public function getNotifications(string $target): JsonResponse
    {
        $userId = $this->session->id();
        if (!$userId) {
            throw new UnauthorizedHttpException('');
        }

        $notifications = match ($target) {
            'forum' => $this->forumFollowerGateway->getEmailSubscribedThreadsForUser($userId),
            'foodsharepoints' => $this->foodSharePointGateway->listFoodsaversFoodSharePoints($userId),
            'regions' => $this->regionGateway->listForFoodsaverExceptWorkingGroups($userId),
            'user' => $this->foodsaverGateway->getSubscriptions($userId),
            'groups' => $this->regionGateway->listForFoodsaverExceptWorkingGroups($userId, false),
            default => throw new BadRequestHttpException(),
        };

        return $this->json($notifications, 200);
    }

    /**
     * Update notification state for regions or working groups.
     */
    #[Tag('notifications')]
    #[Rest\Patch(path: 'notifications/regions')]
    #[Response(response: HttpResponse::HTTP_OK, description: 'Successful')]
    #[Response(response: HttpResponse::HTTP_FORBIDDEN, description: 'Forbidden')]
    #[RequestBody(content: new JsonContent(type: 'array', items: new Items(ref: new Model(type: Region::class))))]
    #[ParamConverter(data: 'regions', class: 'array<Foodsharing\RestApi\Models\Notifications\Region>', converter: 'fos_rest.request_body')]
    public function updateRegionsAndWorkgroupsNotifications(array $regions, ValidatorInterface $validator): HttpResponse
    {
        $userId = $this->session->id();
        if (!$userId) {
            throw new UnauthorizedHttpException('');
        }

        $errors = $validator->validate($regions);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = [
                    'field' => $error->getPropertyPath(),
                    'message' => $error->getMessage(),
                ];
            }
            throw new BadRequestHttpException(json_encode($errorMessages));
        }

        $this->regionTransactions->updateRegionNotification($userId, $regions);

        return $this->handleView($this->view([], 200));
    }

    /**
     * Set or disable notification for forum threads.
     */
    #[Tag('notifications')]
    #[Rest\Patch(path: 'notifications/forum')]
    #[Response(response: HttpResponse::HTTP_OK, description: 'Successful')]
    #[Response(response: HttpResponse::HTTP_FORBIDDEN, description: 'Forbidden')]
    #[RequestBody(content: new JsonContent(type: 'array', items: new Items(ref: new Model(type: Thread::class))))]
    #[ParamConverter(data: 'threads', class: 'array<Foodsharing\RestApi\Models\Notifications\Thread>', converter: 'fos_rest.request_body')]
    public function setThreadsNotifications(array $threads, ValidatorInterface $validator): HttpResponse
    {
        $userId = $this->session->id();
        if (!$userId) {
            throw new UnauthorizedHttpException('');
        }

        $errors = $validator->validate($threads);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = [
                    'field' => $error->getPropertyPath(),
                    'message' => $error->getMessage(),
                ];
            }
            throw new BadRequestHttpException(json_encode($errorMessages));
        }

        $this->forumTransactions->updateThreadNotifications($userId, $threads);

        return $this->handleView($this->view($threads, 200));
    }

    /**
     * Set or disable notification for foodSharePoints.
     */
    #[Tag('notifications')]
    #[Rest\Patch(path: 'notifications/foodsharepoints')]
    #[Response(response: HttpResponse::HTTP_OK, description: 'Successful')]
    #[Response(response: HttpResponse::HTTP_FORBIDDEN, description: 'Forbidden')]
    #[RequestBody(content: new JsonContent(type: 'array', items: new Items(ref: new Model(type: FoodSharePoint::class))))]
    #[ParamConverter(data: 'foodSharePoints', class: 'array<Foodsharing\RestApi\Models\Notifications\FoodSharePoint>', converter: 'fos_rest.request_body')]
    public function setFoodSharePointNotifications(array $foodSharePoints, ValidatorInterface $validator): HttpResponse
    {
        $userId = $this->session->id();
        if (!$userId) {
            throw new UnauthorizedHttpException('');
        }

        $errors = $validator->validate($foodSharePoints);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = [
                    'field' => $error->getPropertyPath(),
                    'message' => $error->getMessage(),
                ];
            }
            throw new BadRequestHttpException(json_encode($errorMessages));
        }

        $this->foodSharePointTransactions->updateFoodSharePointNotifications($userId, $foodSharePoints);

        return $this->handleView($this->view([], 200));
    }

    /**
     * Activate or disable the newsletter or mail notification for chat.
     */
    #[Tag('notifications')]
    #[Rest\Patch(path: 'notifications/user')]
    #[Response(response: HttpResponse::HTTP_OK, description: 'Successful')]
    #[Response(response: HttpResponse::HTTP_FORBIDDEN, description: 'Forbidden')]
    #[RequestBody(content: new Model(type: NewsletterChat::class))]
    #[ParamConverter(data: 'newsletterChat', class: 'Foodsharing\RestApi\Models\Notifications\NewsletterChat', converter: 'fos_rest.request_body')]
    public function setUserNotification(NewsletterChat $newsletterChat, ValidatorInterface $validator): HttpResponse
    {
        $userId = $this->session->id();
        if (!$userId) {
            throw new UnauthorizedHttpException('');
        }

        $errors = $validator->validate($newsletterChat);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = [
                    'field' => $error->getPropertyPath(),
                    'message' => $error->getMessage(),
                ];
            }
            throw new BadRequestHttpException(json_encode($errorMessages));
        }

        $this->settingsGateway->saveInfoSettings($userId, $newsletterChat);

        return $this->handleView($this->view([], HttpResponse::HTTP_OK));
    }
}
