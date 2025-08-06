<?php

namespace Foodsharing\RestApi;

use Exception;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Region\RegionPinStatus;
use Foodsharing\Modules\Event\EventGateway;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\FoodSharePoint\FoodSharePointGateway;
use Foodsharing\Modules\Map\DTO\BasketBubbleData;
use Foodsharing\Modules\Map\DTO\EventMapBubbleData;
use Foodsharing\Modules\Map\DTO\MapMarkerType;
use Foodsharing\Modules\Map\DTO\StoreMapBubbleData;
use Foodsharing\Modules\Map\DTO\StoreMarkerHelpType;
use Foodsharing\Modules\Map\DTO\StoreMarkerScopeType;
use Foodsharing\Modules\Map\DTO\StoreMarkerStatusType;
use Foodsharing\Modules\Map\DTO\UserMarkerActivityType;
use Foodsharing\Modules\Map\DTO\UserMarkerMemberType;
use Foodsharing\Modules\Map\DTO\UserMarkerRoleType;
use Foodsharing\Modules\Map\MapGateway;
use Foodsharing\Modules\Map\MapTransactions;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Permissions\EventPermissions;
use Foodsharing\Permissions\RegionPermissions;
use Foodsharing\RestApi\Models\Map\FoodSharePointBubbleData;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Requirement\Requirement;
use ValueError;

class MapRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        protected Session $session,
        protected CurrentUserUnitsInterface $currentUserUnits,
        private readonly MapGateway $mapGateway,
        private readonly RegionGateway $regionGateway,
        private readonly StoreGateway $storeGateway,
        private readonly FoodSharePointGateway $foodSharePointGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly MapTransactions $mapTransactions,
        private readonly RegionPermissions $regionPermissions,
        private readonly EventPermissions $eventPermissions,
        private readonly EventGateway $eventGateway,
    ) {
    }

    /**
     * Returns the coordinates of all baskets.
     */
    #[OA\Tag('map')]
    #[Rest\Get(path: 'map/markers/{markerType}', requirements: ['markerType' => '[a-z]+'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Successful')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid request parameters')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Marker type not found')]
    public function getMapMarkers(string $markerType, Request $request): Response
    {
        $markerType = MapMarkerType::tryFrom($markerType);
        $queryParams = $request->query->all();
        switch ($markerType) {
            case MapMarkerType::BASKETS:
                return $this->respondOK($this->mapGateway->getBasketMarkers());
            case MapMarkerType::FOOD_SHARE_POINTS:
                return $this->respondOK($this->mapGateway->getFoodSharePointMarkers());
            case MapMarkerType::COMMUNITIES:
                return $this->respondOK($this->mapGateway->getCommunityMarkers());
            case MapMarkerType::STORES:
                $this->assertLoggedIn();

                try {
                    $status = StoreMarkerStatusType::from($queryParams['status'] ?? 'all');
                    $help = StoreMarkerHelpType::from($queryParams['help'] ?? 'all');
                    $scope = StoreMarkerScopeType::from($queryParams['scope'] ?? 'all');
                } catch (ValueError) {
                    throw new BadRequestHttpException('Invalid store marker query parameters');
                }

                return $this->respondOK($this->storeGateway->getStoreMarkers($this->session->id(), $status, $help, $scope));
            case MapMarkerType::USERS:
                $this->assertLoggedIn();

                try {
                    $regionId = intval($queryParams['region']);
                    $role = UserMarkerRoleType::from($queryParams['role'] ?? 'all');
                    $activity = UserMarkerActivityType::from($queryParams['activity'] ?? 'all');
                    $member = UserMarkerMemberType::from($queryParams['member'] ?? 'all');
                } catch (Exception) {
                    throw new BadRequestHttpException('Invalid user marker query parameters');
                }

                if (!$this->regionPermissions->mayAccessUserMapMarkersForRegion($regionId)) {
                    throw new AccessDeniedHttpException('You do not have permission to access user markers for this region.');
                }

                return $this->respondOK($this->foodsaverGateway->getUserMarkers($regionId, $role, $activity, $member));
            case MapMarkerType::EVENTS:
                return $this->respondOK($this->mapGateway->getEventMarkers());
            default:
                throw new NotFoundHttpException('Marker type not found');
        }
    }

    /**
     * Returns the data for the bubble of a community marker on the map.
     */
    #[OA\Tag('map')]
    #[Rest\Get(path: 'map/regions/{regionId}')]
    #[Rest\QueryParam(name: 'regionId', requirements: '\d+', description: 'Region for which to return the description', nullable: true)]
    #[OA\Response(response: Response::HTTP_OK, description: 'Successful')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'The region does not exist or does not have a community description.')]
    public function getRegionBubble(int $regionId): Response
    {
        $region = $this->regionGateway->getRegion($regionId);
        $pin = $this->regionGateway->getRegionPin($regionId);
        if (empty($pin) || $pin->status != RegionPinStatus::ACTIVE) {
            throw new NotFoundHttpException('region does not exist or its pin is not active');
        }

        return $this->handleView($this->view([
            'id' => $region['id'],
            'name' => $region['name'],
            'description' => $pin->description,
        ], Response::HTTP_OK));
    }

    /**
     * Returns the data for a FoodSharePoint.
     */
    #[OA\Tag('map')]
    #[Rest\Get(path: 'map/foodSharePoint/{foodSharePointId}')]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Successful',
        content: new Model(type: FoodSharePointBubbleData::class)
    )]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'The Foodsharepoint does not exist')]
    public function getFoodSharePoint(int $foodSharePointId): Response
    {
        $foodSharePoint = $this->foodSharePointGateway->getFoodSharePoint($foodSharePointId);

        if (count($foodSharePoint) === 0) {
            throw new NotFoundHttpException('The Foodsharepoint does not exist');
        }

        return $this->handleView($this->view(new FoodSharePointBubbleData($foodSharePoint), Response::HTTP_OK));
    }

    /**
     * Returns the data for the bubble of a basket marker on the map.
     */
    #[OA\Tag('map')]
    #[Rest\Get(path: 'map/baskets/{basketId}')]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Successful',
        content: new Model(type: BasketBubbleData::class)
    )]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'The basket does not exist')]
    #[Rest\QueryParam(name: 'basketId', requirements: '\d+', description: 'Basket for which to return data', nullable: false)]
    public function getBasketBubble(int $basketId): Response
    {
        $basket = $this->mapGateway->getBasketBubbleData($basketId, $this->session->mayRole());
        if (empty($basket)) {
            throw new NotFoundHttpException('basket does not exist');
        }

        return $this->handleView($this->view($basket, 200));
    }

    #[OA\Get(summary: 'Returns the data for the bubble of a store marker on the map.')]
    #[OA\Tag('map')]
    #[Rest\Get(path: 'map/stores/{storeId}')]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Successful',
        content: new Model(type: StoreMapBubbleData::class)
    )]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'The store does not exist')]
    #[Rest\QueryParam(name: 'storeId', requirements: '\d+', description: 'Store for which to return data', nullable: false)]
    public function getStoreBubble(int $storeId): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('');
        }

        $store = $this->mapTransactions->getStoreMapData($storeId);

        return $this->handleView($this->view($store, 200));
    }

    #[OA\Get(summary: 'Returns the data for the bubble of a event on the map.')]
    #[OA\Tag('map')]
    #[Rest\Get(path: 'map/event/{eventId}')]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Successful',
        content: new Model(type: StoreMapBubbleData::class)
    )]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'The event is not accessible')]
    #[Rest\QueryParam(name: 'eventId', requirements: Requirement::POSITIVE_INT, description: 'Store for which to return data', nullable: false)]
    public function getEventBubble(int $eventId): Response
    {
        $event = $this->eventGateway->getEvent($eventId);
        if (!$this->eventPermissions->maySeeEvent($event)) {
            throw new AccessDeniedHttpException('');
        }

        $event = EventMapBubbleData::fromEvent($event);

        return $this->respondOK($event);
    }
}
