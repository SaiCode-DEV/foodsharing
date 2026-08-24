<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Info\InfoType;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\FoodSharePoint\FoodSharePointGateway;
use Foodsharing\Modules\FoodSharePoint\FoodSharePointTransactions;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Permissions\FoodSharePointPermissions;
use Foodsharing\Permissions\RegionPermissions;
use Foodsharing\RestApi\Models\FoodSharePoint\AddFoodSharePointResponse;
use Foodsharing\RestApi\Models\FoodSharePoint\FoodSharePointDetails;
use Foodsharing\RestApi\Models\FoodSharePoint\FoodSharePointEditData;
use Foodsharing\RestApi\Models\FoodSharePoint\FoodSharePointForCreation;
use Foodsharing\RestApi\Models\FoodSharePoint\FoodSharePointForListView;
use Foodsharing\RestApi\Models\FoodSharePoint\FoodSharePointPermission;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'foodSharePoints')]
class FoodSharePointRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        private readonly FoodSharePointGateway $foodSharePointGateway,
        private readonly FoodSharePointTransactions $foodSharePointTransactions,
        private readonly FoodSharePointPermissions $foodSharePointPermissions,
        private readonly RegionGateway $regionGateway,
        private readonly RegionPermissions $regionPermissions,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
        protected Session $session
    ) {
        parent::__construct($session);
    }

    #[OA\Get(summary: 'Returns details of the food share point with the given ID.')]
    #[Route('food-share-points/{foodSharePointId}', methods: ['GET'], requirements: ['foodSharePointId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: FoodSharePointDetails::class))]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Food share point not found')]
    public function getFoodSharePointDetails(int $foodSharePointId): Response
    {
        $this->assertFoodSharePointExists($foodSharePointId);
        $foodSharePoint = $this->foodSharePointTransactions->getFoodSharePointDetails($foodSharePointId);

        return $this->respondOK($foodSharePoint);
    }

    #[OA\Get(summary: 'Returns a list of all food share points in a region and all its subregions.')]
    #[Route('regions/{regionId}/food-share-points', methods: ['GET'], requirements: ['regionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(ref: new Model(type: FoodSharePointForListView::class))
    ))]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function listFoodSharePoints(int $regionId): Response
    {
        $this->assertLoggedIn();

        if (!$this->regionPermissions->mayListFoodSharePointsInRegion($regionId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $regionIds = $this->regionGateway->listIdsForDescendantsAndSelf($regionId);
        $foodSharePoints = $this->foodSharePointGateway->listActiveFoodSharePoints($regionIds);

        return $this->respondOK($foodSharePoints);
    }

    #[OA\Post(summary: 'Adds or suggests a new food share point.')]
    #[Route('regions/{regionId}/food-share-points', methods: ['POST'], requirements: ['regionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: AddFoodSharePointResponse::class))]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid region type for the food share point')]
    public function addFoodSharePoint(int $regionId, #[MapRequestPayload] FoodSharePointForCreation $foodSharePoint): Response
    {
        $this->assertLoggedIn();
        $this->assertRegionTypeIsAllowed($regionId);

        if (!$this->currentUserUnits->mayBezirk($regionId)) {
            throw new AccessDeniedHttpException('Not permitted, because you are not a member of the region');
        }

        $response = $this->foodSharePointTransactions->addFoodSharePoint($foodSharePoint);

        return $this->respondOK($response);
    }

    #[OA\Patch(summary: 'Edit an existing food share point.')]
    #[Route('food-share-points/{foodSharePointId}', methods: ['PATCH'], requirements: ['foodSharePointId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Food share point not found')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid region type for the food share point')]
    public function editFoodSharePoint(int $foodSharePointId, #[MapRequestPayload] FoodSharePointEditData $foodSharePointData): Response
    {
        $this->assertLoggedIn();
        $this->assertFoodSharePointExists($foodSharePointId);
        $this->assertRegionTypeIsAllowed($foodSharePointData->regionId);

        $foodSharePoint = $this->foodSharePointGateway->getFoodSharePoint($foodSharePointId);
        if (!$this->foodSharePointPermissions->mayEdit($foodSharePoint->regionId, $foodSharePointId)) {
            throw new AccessDeniedHttpException('Not permitted to edit this food share point');
        }

        $this->foodSharePointTransactions->editFoodSharePoint($foodSharePointId, $foodSharePoint, $foodSharePointData);

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Delete an existing food share point.')]
    #[Route('food-share-points/{foodSharePointId}', methods: ['DELETE'], requirements: ['foodSharePointId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function removeFoodSharePoint(int $foodSharePointId): Response
    {
        $this->assertLoggedIn();
        $this->assertFoodSharePointExists($foodSharePointId);

        $foodSharePoint = $this->foodSharePointGateway->getFoodSharePoint($foodSharePointId);
        if (!$this->foodSharePointPermissions->mayDeleteFoodSharePointOfRegion($foodSharePoint->regionId)) {
            throw new AccessDeniedHttpException('Not permitted to remove this food share point.');
        }

        $this->foodSharePointTransactions->deleteFoodSharePoint($foodSharePointId);

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Returns the permissions the logged in user has for the given food share point')]
    #[Route('food-share-points/{foodSharePointId}/permissions', methods: ['GET'], requirements: ['foodSharePointId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new Model(type: FoodSharePointPermission::class))]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Food share point not found')]
    public function foodSharePointPermissions(int $foodSharePointId): Response
    {
        $this->assertFoodSharePointExists($foodSharePointId);

        $permission = $this->foodSharePointTransactions->getPermission($foodSharePointId);

        return $this->respondOK($permission);
    }

    #[OA\Post(summary: 'Follow a food share point')]
    #[Route('food-share-points/{foodSharePointId}/followers', methods: ['POST'], requirements: ['foodSharePointId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Food share point not found')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    public function followFoodSharePoint(int $foodSharePointId, #[MapQueryParameter] bool $sendMails = false): Response
    {
        $this->assertLoggedIn();
        $this->assertFoodSharePointExists($foodSharePointId);
        $infoType = $sendMails ? InfoType::EMAIL : InfoType::BELL;
        $this->foodSharePointGateway->follow($this->session->id(), $foodSharePointId, $infoType);

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Unfollow a food share point')]
    #[Route('food-share-points/{foodSharePointId}/followers', methods: ['DELETE'], requirements: ['foodSharePointId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Food share point not found')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    public function unfollowFoodSharePoint(int $foodSharePointId): Response
    {
        $this->assertLoggedIn();
        $this->assertFoodSharePointExists($foodSharePointId);
        $this->foodSharePointGateway->unfollow($this->session->id(), $foodSharePointId);

        return $this->respondOK();
    }

    #[OA\Patch(summary: 'Accept a suggested food share point')]
    #[Route('food-share-points/{foodSharePointId}/status', methods: ['PATCH'], requirements: ['foodSharePointId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Food share point not found')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function acceptFoodSharePoint(int $foodSharePointId): Response
    {
        $this->assertLoggedIn();
        $this->assertFoodSharePointExists($foodSharePointId);
        $regionId = $this->foodSharePointGateway->getFoodSharePoint($foodSharePointId)->regionId;
        if (!$this->foodSharePointPermissions->mayApproveFoodSharePointCreation($regionId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $this->foodSharePointGateway->acceptFoodSharePoint($foodSharePointId);

        return $this->respondOK();
    }

    private function assertFoodSharePointExists(int $foodSharePointId): void
    {
        if (!$this->foodSharePointGateway->foodSharePointExists($foodSharePointId)) {
            throw new NotFoundHttpException('Food share point does not exist');
        }
    }

    private function assertRegionTypeIsAllowed(int $regionId): void
    {
        $regionType = $this->regionGateway->getType($regionId);
        if (!UnitType::isAccessibleRegion($regionType)) {
            throw new BadRequestHttpException('Invalid region type for the food share point');
        }
    }
}
