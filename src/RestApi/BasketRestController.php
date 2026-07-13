<?php

namespace Foodsharing\RestApi;

use Carbon\Carbon;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Basket\BasketGateway;
use Foodsharing\Modules\Basket\BasketTransactions;
use Foodsharing\Modules\Basket\DTO\Basket;
use Foodsharing\Modules\Basket\DTO\BasketForListView;
use Foodsharing\Modules\Basket\DTO\BasketForOwnerMenu;
use Foodsharing\Modules\Core\DBConstants\Basket\Status as BasketStatus;
use Foodsharing\Modules\Core\DBConstants\BasketRequests\Status as RequestStatus;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Message\MessageTransactions;
use Foodsharing\Permissions\BasketPermissions;
use Foodsharing\RestApi\DTO\OptionalMessage;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'basket')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
final class BasketRestController extends AbstractFoodsharingRestController
{
    // literal constants
    private const string STATUS = 'status';
    private const int MAX_BASKET_DISTANCE = 50;

    public function __construct(
        protected Session $session,
        private readonly BasketTransactions $basketTransactions,
        private readonly MessageTransactions $messageTransactions,
        private readonly BasketPermissions $basketPermissions,
        private readonly BasketGateway $basketGateway
    ) {
        parent::__construct($session);
    }

    #[OA\Get(summary: 'Returns all current baskets of the user')]
    #[Route('users/current/baskets', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array',
        items: new OA\Items(ref: new Model(type: BasketForOwnerMenu::class)),
    ))]
    public function listBaskets(): Response
    {
        $this->assertLoggedIn();
        $baskets = $this->basketTransactions->getCurrentUsersBaskets();

        return $this->respondOK($baskets);
    }

    #[OA\Get(
        summary: 'Returns a list of baskets close to a given location.',
        description: 'If no valid location is given, the user\'s home location is used. If the user has no home location, lat and lon paramters are required.<br>Baskets created by the current user are excluded.'
    )]
    #[Route('baskets/nearby', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array',
        items: new OA\Items(ref: new Model(type: BasketForListView::class)),
    ))]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Location missing')]
    #[Rest\QueryParam(name: 'distance', description: 'Distance in kilometers.', default: 30)]
    public function listNearbyBaskets(
        #[MapQueryParameter(options: ['min_range' => -90, 'max_range' => 90])] ?float $lat,
        #[MapQueryParameter(options: ['min_range' => -180, 'max_range' => 180])] ?float $lon,
        #[MapQueryParameter(options: ['min_range' => 1, 'max_range' => self::MAX_BASKET_DISTANCE])] int $distance = 30,
    ): Response {
        $this->assertLoggedIn();
        $location = $this->fetchLocationOrUserHome($lat, $lon);

        $baskets = $this->basketGateway->listNearbyBasketsByDistance($this->session->id(), $location, $distance);

        return $this->respondOK($baskets);
    }

    /**
     * Returns details of the basket with the given ID. Returns 200 and the
     * basket, 500 if the basket does not exist, or 401 if not logged in.
     */
    #[OA\Get(summary: 'Returns details of a basket')]
    #[Route('baskets/{basketId}', methods: ['GET'], requirements: ['basketId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: Basket::class))]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Basket not available')]
    public function getBasket(int $basketId): Response
    {
        $this->assertLoggedIn();

        $basket = $this->basketGateway->getBasket($basketId);

        $this->verifyBasketIsAvailable($basket);

        return $this->respondOK($basket);
    }

    #[OA\Post(summary: 'Adds a new basket')]
    #[Route('baskets', methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: Basket::class))]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid basket data')]
    public function addBasket(#[MapRequestPayload] Basket $basket): Response
    {
        $this->assertLoggedIn();

        $basketId = $this->basketTransactions->addBasket($basket);
        if (!$basketId) {
            throw new BadRequestHttpException('Unable to create the basket.');
        }

        return $this->getBasket($basketId);
    }

    #[OA\Delete(summary: 'Removes a new basket')]
    #[Route('baskets/{basketId}', methods: ['DELETE'], requirements: ['basketId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Basket not available')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function removeBasket(int $basketId): Response
    {
        $this->assertLoggedIn();
        $basket = $this->basketGateway->getBasket($basketId);
        $this->verifyBasketIsAvailable($basket);

        if (!$this->basketPermissions->mayDelete($basket)) {
            throw new AccessDeniedHttpException('you are not allowed to delete this basket.');
        }

        $this->basketTransactions->removeBasket($basket);

        return $this->respondOK();
    }

    #[OA\Patch(summary: 'Updates an existing basket')]
    #[Route('baskets/{basketId}', methods: ['PATCH'], requirements: ['basketId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: Basket::class))]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Basket not available')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function editBasket(int $basketId, #[MapRequestPayload] Basket $basket): Response
    {
        $this->assertLoggedIn();
        $existingBasket = $this->basketGateway->getBasket($basketId);
        $this->verifyBasketIsAvailable($existingBasket);

        if ($existingBasket->creator->id !== $this->session->id()) {
            throw new AccessDeniedHttpException('You are not the owner of the basket.');
        }

        $this->basketTransactions->editBasket($basketId, $basket);

        return $this->getBasket($basketId);
    }

    #[OA\Post(summary: 'Requests a basket')]
    #[Route('baskets/{basketId}/requests', methods: ['POST'], requirements: ['basketId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: Basket::class))]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Basket not available')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Request was denied')]
    public function requestBasket(int $basketId, #[MapRequestPayload] OptionalMessage $message): Response
    {
        $this->assertLoggedIn();

        $basket = $this->basketGateway->getBasket($basketId);
        $this->verifyBasketIsAvailable($basket);

        $basketCreatorId = $basket->creator->id;

        // check for existing request
        $requestStatus = $this->basketGateway->getRequestStatus($basketId, $this->session->id(), $basketCreatorId);
        if ($requestStatus && $requestStatus[self::STATUS] === RequestStatus::DENIED) {
            throw new AccessDeniedHttpException('Your request was denied by the basket creator.');
        }

        // Send the message to the creator
        if ($message->message && trim($message->message)) {
            $this->messageTransactions->sendMessageToUser($basketCreatorId, $this->session->id(), trim($message->message), 'basket/request');
        }
        $this->basketGateway->setStatus($basketId, RequestStatus::REQUESTED_MESSAGE_UNREAD, $this->session->id());

        return $this->getBasket($basketId);
    }

    #[OA\Delete(summary: 'Withdraws a basket requests')]
    #[Route('baskets/{basketId}/requests', methods: ['DELETE'], requirements: ['basketId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: Basket::class))]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Basket not available')]
    public function withdrawBasketRequest(int $basketId): Response
    {
        $this->assertLoggedIn();

        $basket = $this->basketGateway->getBasket($basketId);
        $this->verifyBasketIsAvailable($basket);

        // Check that there is an existing active request. If not, there is nothing to withdraw and nothing to be done.
        $requestStatus = $this->basketGateway->getRequestStatus($basketId, $this->session->id(), $basket->creator->id);
        if ($requestStatus && ($requestStatus[self::STATUS] === RequestStatus::REQUESTED_MESSAGE_UNREAD || $requestStatus[self::STATUS] === RequestStatus::REQUESTED_MESSAGE_READ)) {
            $this->basketGateway->setStatus($basketId, RequestStatus::DELETED_OTHER_REASON, $this->session->id());
        }

        return $this->getBasket($basketId);
    }

    #[OA\Patch(
        summary: 'Updates the status of a basket request',
        description: 'The creator of a basket can set the request status for their basket (e.g. mark as picked up, not picked up, denied etc.).'
    )]
    #[Route('baskets/{basketId}/requests/{requesterId}/status', methods: ['PATCH'], requirements: [
        'basketId' => Requirement::POSITIVE_INT,
        'requesterId' => Requirement::POSITIVE_INT
    ])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Request not available')]
    public function updateRequestStatus(
        int $basketId,
        int $requesterId,
        #[MapQueryParameter(options: [
            'min_range' => RequestStatus::DELETED_PICKED_UP,
            'max_range' => RequestStatus::DELETED_OTHER_REASON
        ])] int $status,
    ): Response {
        $this->assertLoggedIn();

        $basket = $this->basketGateway->getBasket($basketId);
        if (!$basket || $basket->creator->id !== $this->session->id()) {
            throw new AccessDeniedHttpException('You can only update request status for your own baskets.');
        }

        $request = $this->basketGateway->getRequest($basketId, $requesterId, $this->session->id());
        if (!$request) {
            throw new NotFoundHttpException('Request not found.');
        }

        $this->basketGateway->setStatus($basketId, $status, $requesterId);

        return $this->respondOK();
    }

    /**
     * Verifies that the basket was not deleted and is not expired. Otherwise this
     * method throws a fitting 404 not found HttpException.
     *
     * @throws NotFoundHttpException if the basket is not available
     */
    private function verifyBasketIsAvailable(?Basket $basket): void
    {
        if (!$basket || $basket->status === BasketStatus::DELETED_OTHER_REASON) {
            throw new NotFoundHttpException('Basket does not exist.');
        }

        if ($basket->status === BasketStatus::DELETED_PICKED_UP) {
            throw new NotFoundHttpException('Basket was already picked up.');
        }

        if (Carbon::instance($basket->until)->isPast()) {
            throw new NotFoundHttpException('Basket is expired.');
        }
    }

    /**
     * Returns a location from the param fetcher in the 'lat' and 'lon' fields.
     * If no valid location is given, it returns the user's home address.
     * If that also doesn't exist or is invalid, an Exception is thrown.
     *
     * @return GeoLocation the location
     * @throws BadRequestHttpException if no location and no default location were given and the user's
     * home address is not set
     */
    private function fetchLocationOrUserHome(?float $lat, ?float $lon): GeoLocation
    {
        if (is_null($lat) || is_null($lon)) {
            $loc = $this->session->user('location');
            if (!$loc || ($loc->lat === 0 && $loc->lon === 0)) {
                throw new BadRequestHttpException('You must provide a location since the user profile has none.');
            }
            $lat = $loc->lat;
            $lon = $loc->lon;
        }

        return new GeoLocation($lat, $lon);
    }
}
