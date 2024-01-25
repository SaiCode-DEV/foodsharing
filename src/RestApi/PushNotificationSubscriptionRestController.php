<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\PushNotification\Notification\TestPushNotification;
use Foodsharing\Modules\PushNotification\PushNotificationGateway;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class PushNotificationSubscriptionRestController extends AbstractFOSRestController
{
    /**
     * @var PushNotificationGateway
     */
    private $gateway;

    /**
     * @var Session
     */
    private $session;

    public function __construct(PushNotificationGateway $gateway, Session $session)
    {
        $this->gateway = $gateway;
        $this->session = $session;
    }

    /**
     * @OA\Tag(name="pushnotification")
     */
    #[Rest\Get('pushnotification/{type}/server-information')]
    public function getServerInformation(string $type): Response
    {
        if (!$this->gateway->hasHandlerFor($type)) {
            throw new NotFoundHttpException();
        }

        $view = $this->view($this->gateway->getServerInformation($type), 200);

        return $this->handleView($view);
    }

    /**
     * @OA\Tag(name="pushnotification")
     */
    #[Rest\Post('pushnotification/{type}/subscription')]
    public function subscribe(Request $request, string $type): Response
    {
        if (!$this->gateway->hasHandlerFor($type)) {
            throw new NotFoundHttpException();
        }

        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('');
        }

        $pushSubscription = $request->getContent();
        $foodsaverId = $this->session->id();

        $subscriptionId = $this->gateway->addSubscription($foodsaverId, $pushSubscription, $type);

        $this->gateway->sendPushNotificationsToFoodsaver($foodsaverId, new TestPushNotification());

        return $this->handleView($this->view(['id' => $subscriptionId], 200));
    }

    /**
     * @OA\Tag(name="pushnotification")
     */
    #[Rest\Delete('pushnotification/{type}/subscription/{subscriptionId}', requirements: ['subscriptionId' => '\d+'])]
    public function unsubscribe(string $type, int $subscriptionId): Response
    {
        if (!$this->gateway->hasHandlerFor($type)) {
            throw new NotFoundHttpException();
        }

        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('');
        }

        $foodsaverId = $this->session->id();

        $this->gateway->deleteSubscription($foodsaverId, $subscriptionId, $type);

        return $this->handleView($this->view([], 200));
    }
}
