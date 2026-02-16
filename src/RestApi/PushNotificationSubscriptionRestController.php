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
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'push-notification')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
#[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Handler type does not exist')]
class PushNotificationSubscriptionRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        private readonly PushNotificationGateway $gateway,
        protected Session $session
    ) {
        parent::__construct($this->session);
    }

    #[OA\Get(summary: 'Returns information necessary for registering subscribing to push notifications with this handler')]
    #[Route('push-notification/{type}/server-information', methods: ['GET'], requirements: ['type' => '\w+'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Successful', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'key', description: 'the public key to be used for subscribing, or null if not applicable', nullable: true, type: 'string')
    ]))]
    public function getServerInformation(string $type): Response
    {
        $this->assertHasHandler($type);
        $this->assertLoggedIn();

        $information = $this->gateway->getServerInformation($type);

        return $this->respondOK($information);
    }

    #[OA\Post(summary: 'Subscribes to push notifications with the specified handler')]
    #[Route('push-notification/{type}/subscription', methods: ['POST'], requirements: ['type' => '\w+'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Successful', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'id', description: 'the ID of the created subscription', type: 'integer')
    ]))]
    public function subscribe(Request $request, string $type): Response
    {
        $this->assertHasHandler($type);
        $this->assertLoggedIn();

        $pushSubscription = $request->getContent();
        $foodsaverId = $this->session->id();

        $subscriptionId = $this->gateway->addSubscription($foodsaverId, $pushSubscription, $type);

        $this->gateway->sendPushNotificationsToFoodsaver($foodsaverId, new TestPushNotification(), $subscriptionId);

        return $this->respondOK(['id' => $subscriptionId]);
    }

    #[OA\Delete(summary: 'Unsubscribes from push notifications with the specified handler')]
    #[Route('push-notification/{type}/subscription/{subscriptionId}', methods: ['DELETE'], requirements: ['type' => '\w+', 'subscriptionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Successful')]
    public function unsubscribe(string $type, int $subscriptionId): Response
    {
        $this->assertHasHandler($type);
        $this->assertLoggedIn();

        $this->gateway->deleteSubscription($this->session->id(), $subscriptionId, $type);

        return $this->respondOK();
    }

    private function assertHasHandler(string $type): void
    {
        if (!$this->gateway->hasHandlerFor($type)) {
            throw new NotFoundHttpException('Handler type does not exist');
        }
    }
}
