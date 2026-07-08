<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Categories\StoreCategoryType;
use Foodsharing\Modules\Core\DBConstants\Region\RegionPinStatus;
use Foodsharing\Modules\Event\EventGateway;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\FoodSharePoint\FoodSharePointGateway;
use Foodsharing\Modules\Map\DTO\BasketBubbleData;
use Foodsharing\Modules\Map\DTO\EventMapBubbleData;
use Foodsharing\Modules\Map\DTO\FoodSharePointMapBubbleData;
use Foodsharing\Modules\Map\DTO\MapMarker;
use Foodsharing\Modules\Map\DTO\RegionMapBubbleData;
use Foodsharing\Modules\Map\DTO\StoreMapBubbleData;
use Foodsharing\Modules\Map\DTO\StoreMarkerHelpType;
use Foodsharing\Modules\Map\DTO\StoreMarkerScopeType;
use Foodsharing\Modules\Map\DTO\StoreMarkerStatusType;
use Foodsharing\Modules\Map\DTO\UserMapBubbleData;
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
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag('map')]
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
        parent::__construct($session);
    }

    /* Endpoints for getting MapMarker lists */

    #[OA\Get(summary: 'Returns all basket markers.')]
    #[Route('map/markers/baskets', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(ref: new Model(type: MapMarker::class))
    ))]
    public function getBasketMarkers(): Response
    {
        $baskets = $this->mapGateway->getBasketMarkers();

        return $this->respondOK($baskets);
    }

    #[OA\Get(summary: 'Returns all food share point markers.')]
    #[Route('map/markers/food-share-points', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(ref: new Model(type: MapMarker::class))
    ))]
    public function getFoodSharePointMarkers(): Response
    {
        $foodSharePoints = $this->mapGateway->getFoodSharePointMarkers();

        return $this->respondOK($foodSharePoints);
    }

    #[OA\Get(summary: 'Returns all region markers.')]
    #[Route('map/markers/regions', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(ref: new Model(type: MapMarker::class))
    ))]
    public function getRegionMarkers(): Response
    {
        $regions = $this->mapGateway->getRegionMarkers();

        return $this->respondOK($regions);
    }

    #[OA\Get(summary: 'Returns all store markers.')]
    #[Route('map/markers/stores', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(ref: new Model(type: MapMarker::class))
    ))]
    public function getStoreMarkers(
        #[MapQueryParameter] ?StoreMarkerStatusType $status,
        #[MapQueryParameter] ?StoreMarkerHelpType $help,
        #[MapQueryParameter] ?StoreMarkerScopeType $scope,
        #[MapQueryParameter] ?StoreCategoryType $type = null,
    ): Response {
        $this->assertLoggedIn();
        $markers = $this->storeGateway->getStoreMarkers(
            $this->session->id(),
            $status ?? StoreMarkerStatusType::ALL,
            $help ?? StoreMarkerHelpType::ALL,
            $scope ?? StoreMarkerScopeType::ALL,
            $type
        );

        return $this->respondOK($markers);
    }

    #[OA\Get(summary: 'Returns all user markers.')]
    #[Route('map/markers/users', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(ref: new Model(type: MapMarker::class))
    ))]
    public function getUserMarkers(
        #[MapQueryParameter] int $regionId,
        #[MapQueryParameter] ?UserMarkerRoleType $role,
        #[MapQueryParameter] ?UserMarkerActivityType $activity,
        #[MapQueryParameter] ?UserMarkerMemberType $member,
    ): Response {
        $this->assertLoggedIn();
        if (!$this->regionPermissions->mayAccessUserMapMarkersForRegion($regionId)) {
            throw new AccessDeniedHttpException('You do not have permission to access user markers for this region.');
        }

        $markers = $this->foodsaverGateway->getUserMarkers(
            $regionId,
            $role ?? UserMarkerRoleType::ALL,
            $activity ?? UserMarkerActivityType::ALL,
            $member ?? UserMarkerMemberType::ALL
        );

        return $this->respondOK($markers);
    }

    #[OA\Get(summary: 'Returns of all event markers.')]
    #[Route('map/markers/events', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(ref: new Model(type: MapMarker::class))
    ))]
    public function getEventMarkers(): Response
    {
        $events = $this->mapGateway->getEventMarkers();

        return $this->respondOK($events);
    }

    /* Endpoints for getting BubbleData details */

    #[OA\Get(summary: 'Returns details on a region marker')]
    #[Route('map/markers/regions/{regionId}', methods: ['GET'], requirements: ['regionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: RegionMapBubbleData::class))]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'The region does not exist or does not have a region pin.')]
    public function getRegionBubble(int $regionId): Response
    {
        $region = $this->regionGateway->getRegion($regionId);
        $pin = $this->regionGateway->getRegionPin($regionId);
        if (empty($pin) || $pin->status != RegionPinStatus::ACTIVE) {
            throw new NotFoundHttpException('region does not exist or its pin is not active');
        }

        return $this->respondOK(RegionMapBubbleData::create(
            $region['id'],
            $region['name'],
            $pin->description
        ));
    }

    #[OA\Get(summary: 'Returns details on a food share point marker')]
    #[Route('map/markers/food-share-points/{foodSharePointId}', methods: ['GET'], requirements: ['foodSharePointId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: FoodSharePointMapBubbleData::class))]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'The food share point does not exist')]
    public function getFoodSharePoint(int $foodSharePointId): Response
    {
        $foodSharePoint = $this->foodSharePointGateway->getFoodSharePoint($foodSharePointId);

        if (is_null($foodSharePoint)) {
            throw new NotFoundHttpException('The food share point does not exist');
        }

        return $this->respondOK(new FoodSharePointMapBubbleData($foodSharePoint));
    }

    #[OA\Get(summary: 'Returns details on a basket marker')]
    #[Route('map/markers/baskets/{basketId}', methods: ['GET'], requirements: ['basketId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: BasketBubbleData::class))]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'The basket does not exist')]
    public function getBasketBubble(int $basketId): Response
    {
        $basket = $this->mapGateway->getBasketBubbleData($basketId, $this->session->mayRole());
        if (empty($basket)) {
            throw new NotFoundHttpException('basket does not exist');
        }

        return $this->respondOK($basket);
    }

    #[OA\Get(summary: 'Returns details on a store marker')]
    #[Route('map/markers/stores/{storeId}', methods: ['GET'], requirements: ['storeId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: StoreMapBubbleData::class))]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'The store does not exist')]
    public function getStoreBubble(int $storeId): Response
    {
        $this->assertLoggedIn();

        $store = $this->mapTransactions->getStoreMapData($storeId);

        return $this->respondOK($store);
    }

    #[OA\Get(summary: 'Returns details on a event marker')]
    #[Route('map/markers/events/{eventId}', methods: ['GET'], requirements: ['eventId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: EventMapBubbleData::class))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'The event does not exist')]
    public function getEventBubble(int $eventId): Response
    {
        $event = $this->eventGateway->getEvent($eventId);
        if (empty($event)) {
            throw new NotFoundHttpException('event does not exist');
        }
        if (!$this->eventPermissions->maySeeEvent($event)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $event = EventMapBubbleData::fromEvent($event);

        return $this->respondOK($event);
    }

    #[OA\Get(summary: 'Returns details on a user marker')]
    #[Route('map/markers/users/{userId}', methods: ['GET'], requirements: ['userId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: UserMapBubbleData::class))]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'The user does not exist')]
    public function getUserBubble(int $userId): Response
    {
        $this->assertLoggedIn();
        $user = $this->foodsaverGateway->getFoodsaverDetails($userId);
        if (empty($user)) {
            throw new NotFoundHttpException('The user does not exist');
        }
        $user = UserMapBubbleData::create(
            new Profile($user['id'], $user['name'], $user['photo'], (bool)$user['is_sleeping']),
            $user['about_me_intern'] ?? null
        );

        return $this->respondOK($user);
    }
}
