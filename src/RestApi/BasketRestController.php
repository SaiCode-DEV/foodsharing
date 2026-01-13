<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Basket\BasketGateway;
use Foodsharing\Modules\Basket\BasketTransactions;
use Foodsharing\Modules\Basket\DTO\Basket;
use Foodsharing\Modules\Core\DBConstants\Basket\Status as BasketStatus;
use Foodsharing\Modules\Core\DBConstants\BasketRequests\Status as RequestStatus;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Message\MessageTransactions;
use Foodsharing\Permissions\BasketPermissions;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use OpenApi\Attributes as OA2;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Rest controller for food baskets.
 */
#[OA2\Tag(name: 'basket')]
final class BasketRestController extends AbstractFoodsharingRestController
{
    // literal constants
    private const string STATUS = 'status';
    private const string LAT = 'lat';
    private const string LON = 'lon';
    private const int MAX_BASKET_DISTANCE = 50;

    public function __construct(
        private readonly BasketTransactions $basketTransactions,
        private readonly MessageTransactions $messageTransactions,
        protected Session $session,
        private readonly BasketPermissions $basketPermissions,
        private readonly BasketGateway $basketGateway
    ) {
        parent::__construct($session);
    }

    // TODO rework api description
    /**
     * Returns all current baskets of the user.
     *
     * Returns 200 and a list of baskets or 401 if not logged in.
     *
     * @OA\Tag(name="basket")
     */
    #[Rest\Get('user/current/baskets')]
    public function listBaskets(): Response
    {
        $this->assertLoggedIn();
        $baskets = $this->basketTransactions->getCurrentUsersBaskets();

        return $this->respondOK($baskets);
    }

    /**
     * Returns a list of baskets close to a given location. If the location is not valid the user's
     * home location is used. The distance is measured in kilometers.
     * Does not include baskets created by the current user.
     *
     * Returns 200 and a list of baskets, 400 if the distance is out of range, or 401 if not logged in.
     *
     * @OA\Tag(name="basket")
     */
    #[Rest\Get('baskets/nearby')]
    #[Rest\QueryParam(name: 'lat', nullable: true)]
    #[Rest\QueryParam(name: 'lon', nullable: true)]
    #[Rest\QueryParam(name: 'distance', nullable: false, requirements: '\d+')]
    public function listNearbyBaskets(ParamFetcher $paramFetcher): Response
    {
        $this->assertLoggedIn();

        $location = $this->fetchLocationOrUserHome($paramFetcher);
        $distance = $paramFetcher->get('distance');
        if ($distance < 1 || $distance > self::MAX_BASKET_DISTANCE) {
            throw new BadRequestHttpException('distance must be positive and <= ' . self::MAX_BASKET_DISTANCE);
        }

        $baskets = $this->basketGateway->listNearbyBasketsByDistance($this->session->id(), $location, $distance);

        return $this->respondOK($baskets);
    }

    /**
     * Returns details of the basket with the given ID. Returns 200 and the
     * basket, 500 if the basket does not exist, or 401 if not logged in.
     *
     * @OA\Tag(name="basket")
     */
    #[Rest\Get('baskets/{basketId}', requirements: ['basketId' => '\d+'])]
    public function getBasket(int $basketId): Response
    {
        $this->assertLoggedIn();

        $basket = $this->basketGateway->getBasket($basketId);

        $this->verifyBasketIsAvailable($basket);

        return $this->respondOK($basket);
    }

    /**
     * Adds a new basket. The description must not be empty. All other
     * parameters are optional. Returns the created basket.
     *
     * @OA\Tag(name="basket")
     * @OA\RequestBody(@Model(type=Basket::class))
     */
    #[Rest\Post('baskets')]
    #[ParamConverter('basket', class: Basket::class, converter: 'fos_rest.request_body')]
    public function addBasket(Basket $basket, ValidatorInterface $validator): Response
    {
        $this->assertLoggedIn();

        $errors = $validator->validate($basket);
        if ($errors->count() > 0) {
            $firstError = $errors->get(0);
            throw new BadRequestHttpException(json_encode(['field' => $firstError->getPropertyPath(), 'message' => $firstError->getMessage()]));
        }

        $basketId = $this->basketTransactions->addBasket($basket);
        if (!$basketId) {
            throw new BadRequestHttpException('Unable to create the basket.');
        }

        return $this->getBasket($basketId);
    }

    /**
     * Removes a basket of this user with the given ID. Returns 200 if a basket
     * of the user was found and deleted, 404 if no such basket was found, or
     * 401 if not logged in.
     *
     * @OA\Tag(name="basket")
     */
    #[Rest\Delete('baskets/{basketId}', requirements: ['basketId' => '\d+'])]
    public function removeBasket(int $basketId): Response
    {
        $this->assertLoggedIn();
        $basket = $this->basketGateway->getBasket($basketId);
        if (empty($basket)) {
            throw new NotFoundHttpException('Basket was not found or cannot be deleted.');
        }

        if (!$this->basketPermissions->mayDelete($basket)) {
            throw new AccessDeniedHttpException('you are not allowed to delete this basket.');
        }

        $this->basketTransactions->removeBasket($basket);

        return $this->respondOK();
    }

    /**
     * Updates the description of an existing basket. The description must not be empty. If the location
     * is not given or invalid it falls back to the user's home. Returns the updated basket.
     *
     * @OA\Tag(name="basket")
     * @OA\RequestBody(@Model(type=Basket::class))
     * @param int $basketId ID of an existing basket
     */
    #[Rest\Put('baskets/{basketId}', requirements: ['basketId' => '\d+'])]
    #[ParamConverter('basket', class: Basket::class, converter: 'fos_rest.request_body')]
    public function editBasket(int $basketId, Basket $basket, ValidatorInterface $validator): Response
    {
        $this->assertLoggedIn();
        $existingBasket = $this->basketGateway->getBasket($basketId);

        $this->verifyBasketIsAvailable($existingBasket);
        if ($existingBasket->creator->id !== $this->session->id()) {
            throw new UnauthorizedHttpException('', 'You are not the owner of the basket.');
        }

        $errors = $validator->validate($basket);
        if ($errors->count() > 0) {
            $firstError = $errors->get(0);
            throw new BadRequestHttpException(json_encode(['field' => $firstError->getPropertyPath(), 'message' => $firstError->getMessage()]));
        }

        $this->basketTransactions->editBasket($basketId, $basket);

        return $this->getBasket($basketId);
    }

    /**
     * Requests a basket.
     *
     * @OA\Tag(name="basket")
     *
     * @param int $basketId ID of an existing basket
     */
    #[Rest\Post('baskets/{basketId}/request', requirements: ['basketId' => '\d+'])]
    #[Rest\RequestParam(name: 'message', nullable: false)]
    public function requestBasket(int $basketId, ParamFetcher $paramFetcher): Response
    {
        $this->assertLoggedIn();

        $message = trim(strip_tags((string)$paramFetcher->get('message')));

        if (empty($message)) {
            throw new BadRequestHttpException('The request message should not be empty.');
        }

        $basket = $this->basketGateway->getBasket($basketId);
        $this->verifyBasketIsAvailable($basket);

        $basketCreatorId = $basket->creator->id;

        // check for existing request
        $requestStatus = $this->basketGateway->getRequestStatus($basketId, $this->session->id(), $basketCreatorId);
        if ($requestStatus && $requestStatus[self::STATUS] === RequestStatus::DENIED) {
            throw new AccessDeniedHttpException('Your request was denied by the basket creator.');
        }

        // Send the message to the creator
        $this->messageTransactions->sendMessageToUser($basketCreatorId, $this->session->id(), $message, 'basket/request');
        $this->basketGateway->setStatus($basketId, RequestStatus::REQUESTED_MESSAGE_UNREAD, $this->session->id());

        return $this->getBasket($basketId);
    }

    /**
     * Withdraw a basket request.
     *
     * @OA\Tag(name="basket")
     *
     * @param int $basketId ID of an existing basket
     */
    #[Rest\Post('baskets/{basketId}/withdraw', requirements: ['basketId' => '\d+'])]
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

    /**
     * Verifies that the basket was not deleted and is not expired. Otherwise this
     * method throws an appropriate HttpException.
     */
    private function verifyBasketIsAvailable(?Basket $basket): void
    {
        if (!$basket || $basket->status === BasketStatus::DELETED_OTHER_REASON) {
            throw new NotFoundHttpException('Basket does not exist.');
        }

        if ($basket->status === BasketStatus::DELETED_PICKED_UP) {
            throw new NotFoundHttpException('Basket was already picked up.');
        }

        if ($basket->until < time()) {
            throw new NotFoundHttpException('Basket is expired.');
        }
    }

    /**
     * Returns a location from the param fetcher in the 'lat' and 'lon' fields. If none
     * is given, it returns the default location or the user's home address, if the default
     * location is null.
     *
     * @param GeoLocation $defaultLocation a fallback value or null
     *
     * @return GeoLocation the location
     *
     * @throws BadRequestHttpException if no location and no default location were given and the user's
     * home address is not set
     */
    private function fetchLocationOrUserHome(ParamFetcher $paramFetcher, ?GeoLocation $defaultLocation = null): GeoLocation
    {
        $lat = $paramFetcher->get(self::LAT);
        $lon = $paramFetcher->get(self::LON);
        $lat = is_numeric($lat) ? (float)$lat : null;
        $lon = is_numeric($lon) ? (float)$lon : null;
        if (!$this->isValidNumber($lat, -90.0, 90.0) || !$this->isValidNumber($lon, -180.0, 180.0)) {
            if ($defaultLocation !== null) {
                return $defaultLocation;
            }
            // find user's location
            $loc = $this->session->user('location');
            if (!$loc || ($loc->lat === 0 && $loc->lon === 0)) {
                throw new BadRequestHttpException('The user profile has no address.');
            }
            $lat = $loc->lat;
            $lon = $loc->lon;
        }

        return GeoLocation::createFromArray(['lat' => $lat, 'lon' => $lon]);
    }

    #[OA2\Patch(summary: 'Updates the status of a basket request. The creator of a basket can set the
      status of requests for their basket (e.g. mark as picked up, not picked up, denied etc.).')]
    #[OA2\Tag(name: 'basket')]
    #[OA2\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA2\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid new status')]
    #[OA2\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA2\Response(response: Response::HTTP_FORBIDDEN, description: 'Not allowed to request this basket')]
    #[OA2\Response(response: Response::HTTP_NOT_FOUND, description: 'Basket does not exist')]
    #[Rest\Patch('baskets/{basketId}/requests/{requesterId}/status',
        requirements: [
            'basketId' => '\d+',
            'requesterId' => '\d+'
        ],
    )]
    #[Rest\RequestParam(name: 'status', requirements: '(2|3|4|5)', nullable: false)]
    public function updateRequestStatus(
        int $basketId,
        int $requesterId,
        ParamFetcher $paramFetcher
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

        $status = (int)$paramFetcher->get('status');

        $allowedStatuses = [
            RequestStatus::DELETED_PICKED_UP,
            RequestStatus::NOT_PICKED_UP,
            RequestStatus::DELETED_OTHER_REASON,
            RequestStatus::DENIED
        ];

        if (!in_array($status, $allowedStatuses)) {
            throw new BadRequestHttpException('Invalid status value.');
        }

        $this->basketGateway->setStatus($basketId, $status, $requesterId);

        return $this->respondOK(['status' => $status]);
    }

    /**
     * Checks if the number is a valid value in the given range.
     * TODO Duplicated in FoodSharePointRestController.php.
     */
    private function isValidNumber($value, float $lowerBound, float $upperBound): bool
    {
        return !is_null($value) && !is_nan($value)
            && ($lowerBound <= $value) && ($upperBound >= $value);
    }
}
