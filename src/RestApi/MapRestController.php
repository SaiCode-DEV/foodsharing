<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Region\RegionPinStatus;
use Foodsharing\Modules\FoodSharePoint\FoodSharePointGateway;
use Foodsharing\Modules\Map\DTO\BasketBubbleData;
use Foodsharing\Modules\Map\DTO\MapMarkerType;
use Foodsharing\Modules\Map\DTO\StoreMapBubbleData;
use Foodsharing\Modules\Map\DTO\StoreMarkerHelpType;
use Foodsharing\Modules\Map\DTO\StoreMarkerScopeType;
use Foodsharing\Modules\Map\DTO\StoreMarkerStatusType;
use Foodsharing\Modules\Map\MapGateway;
use Foodsharing\Modules\Map\MapTransactions;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\RestApi\Models\Map\FoodSharePointBubbleData;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use ValueError;

class MapRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        protected Session $session,
        private readonly MapGateway $mapGateway,
        private readonly RegionGateway $regionGateway,
        private readonly StoreGateway $storeGateway,
        private readonly FoodSharePointGateway $foodSharePointGateway,
        private readonly MapTransactions $mapTransactions,
    ) {
    }

    /**
     * Returns the coordinates of all baskets.
     */
    #[OA\Tag('map')]
    #[Rest\Get(path: 'map/markers/{markerType}', requirements: ['markerType' => '[a-z]+'])]
    #[Rest\QueryParam(name: 'status', default: 'all')]
    #[Rest\QueryParam(name: 'help', default: 'all')]
    #[Rest\QueryParam(name: 'scope', default: 'all')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Successful')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
    public function getMapMarkers(string $markerType, ParamFetcher $paramFetcher): Response
    {
        try {
            $markerType = MapMarkerType::from($markerType);
        } catch (ValueError $error) {
            throw new NotFoundHttpException();
        }
        if ($markerType === MapMarkerType::BASKETS) {
            return $this->respondOK($this->mapGateway->getBasketMarkers());
        } elseif ($markerType === MapMarkerType::FOOD_SHARE_POINTS) {
            return $this->respondOK($this->mapGateway->getFoodSharePointMarkers());
        } elseif ($markerType === MapMarkerType::COMMUNITIES) {
            return $this->respondOK($this->mapGateway->getCommunityMarkers());
        } elseif ($markerType === MapMarkerType::STORES) {
            $this->assertLoggedIn();

            try {
                $status = StoreMarkerStatusType::from($paramFetcher->get('status'));
                $help = StoreMarkerHelpType::from($paramFetcher->get('help'));
                $scope = StoreMarkerScopeType::from($paramFetcher->get('scope'));
            } catch (ValueError $error) {
                throw new BadRequestHttpException();
            }

            return $this->respondOK($this->storeGateway->getStoreMarkers($this->session->id(), $status, $help, $scope));
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
        if (empty($pin) || $pin['status'] != RegionPinStatus::ACTIVE) {
            throw new NotFoundHttpException('region does not exist or its pin is not active');
        }

        return $this->handleView($this->view([
            'name' => $region['name'],
            'description' => $pin['desc'],
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
    public function getStoreBubbleAction(int $storeId): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('');
        }

        $store = $this->mapTransactions->getStoreMapData($storeId);

        return $this->handleView($this->view($store, 200));
    }
}
