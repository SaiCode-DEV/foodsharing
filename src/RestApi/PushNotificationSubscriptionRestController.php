<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\PushNotification\Notification\TestPushNotification;
use Foodsharing\Modules\PushNotification\PushNotificationGateway;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'pushnotification')]
class PushNotificationSubscriptionRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        private readonly PushNotificationGateway $gateway,
        protected Session $session
    ) {
        parent::__construct($this->session);
    }

    #[OA\Get(summary: 'Returns information necessary for registering subscribing to push notifications with this handler')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Successful')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Handler type does not exist')]
    #[OA\Parameter(name: 'type', description: 'the handler type', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[Route(path: '/pushnotification/{type}/server-information', methods: ['GET'])]
    public function getServerInformation(string $type): Response
    {
        if (!$this->gateway->hasHandlerFor($type)) {
            throw new NotFoundHttpException();
        }

        $information = $this->gateway->getServerInformation($type);

        return $this->respondOK($information);
    }

    #[OA\Post(summary: 'Subscribes to push notifications with the specified handler')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Successful')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Handler type does not exist')]
    #[OA\Parameter(name: 'type', description: 'the handler type', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[Route(path: '/pushnotification/{type}/subscription', methods: ['POST'])]
    public function subscribe(Request $request, string $type): Response
    {
        if (!$this->gateway->hasHandlerFor($type)) {
            throw new NotFoundHttpException();
        }

        $this->assertLoggedIn();

        $pushSubscription = $request->getContent();
        $foodsaverId = $this->session->id();

        $subscriptionId = $this->gateway->addSubscription($foodsaverId, $pushSubscription, $type);

        $this->gateway->sendPushNotificationsToFoodsaver($foodsaverId, new TestPushNotification(), $subscriptionId);

        return $this->respondOK(['id' => $subscriptionId]);
    }

    #[OA\Delete(summary: 'Subscribes to push notifications with the specified handler')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Successful')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Handler type does not exist')]
    #[OA\Parameter(name: 'type', description: 'the handler type', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'subscriptionId', description: 'unique identifier of the subscription', in: 'path', required: true)]
    #[Route(path: '/pushnotification/{type}/subscription/{subscriptionId}', requirements: ['subscriptionId' => '\d+'], methods: ['DELETE'])]
    public function unsubscribe(string $type, int $subscriptionId): Response
    {
        if (!$this->gateway->hasHandlerFor($type)) {
            throw new NotFoundHttpException();
        }

        $this->assertLoggedIn();

        $this->gateway->deleteSubscription($this->session->id(), $subscriptionId, $type);

        return $this->respondOK();
    }
}
