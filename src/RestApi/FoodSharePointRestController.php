<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\FoodSharePoint\FollowerType;
use Foodsharing\Modules\Core\DBConstants\Info\InfoType;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\FoodSharePoint\FoodSharePointGateway;
use Foodsharing\Modules\FoodSharePoint\FoodSharePointTransactions;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Permissions\FoodSharePointPermissions;
use Foodsharing\Permissions\RegionPermissions;
use Foodsharing\RestApi\Models\FoodSharePoint\FoodSharePointEditData;
use Foodsharing\RestApi\Models\FoodSharePoint\FoodSharePointForCreation;
use Foodsharing\RestApi\Models\FoodSharePoint\FoodSharePointPermission;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'foodSharePoints')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Must be logged in')]
#[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
#[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Food share point not found')]
final class FoodSharePointRestController extends AbstractFoodsharingRestController
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
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[Rest\Get('foodSharePoints/{foodSharePointId}', requirements: ['foodSharePointId' => Requirement::POSITIVE_INT])]
    public function getFoodSharePoint(int $foodSharePointId): Response
    {
        $this->assertFoodSharePointExists($foodSharePointId);
        $foodSharePoint = $this->foodSharePointGateway->getFoodSharePointWithManagers($foodSharePointId);

        return $this->respondOK($foodSharePoint);
    }

    #[OA\Get(summary: 'Returns a list of all food share points in a region and all its subregions.')]
    #[OA\Parameter(name: 'regionId', description: 'region for which to return food share points', in: 'path', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[Rest\Get('regions/{regionId}/foodSharePoints', requirements: ['regionId' => '\d+'])]
    public function listFoodSharePoints(int $regionId): Response
    {
        $this->assertLoggedIn();

        if (!$this->regionPermissions->mayListFoodSharePointsInRegion($regionId)) {
            throw new AccessDeniedHttpException('');
        }

        $regionIds = $this->regionGateway->listIdsForDescendantsAndSelf($regionId);
        $foodSharePoints = $this->foodSharePointGateway->listActiveFoodSharePoints($regionIds);

        return $this->respondOK($foodSharePoints);
    }

    #[OA\Post(summary: 'Adds or suggests a new food share point.')]
    #[OA\RequestBody(content: new Model(type: FoodSharePointForCreation::class))]
    #[ParamConverter('foodSharePoint', class: FoodSharePointForCreation::class, converter: 'fos_rest.request_body')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[Rest\Post('regions/{regionId}/foodSharePoints', requirements: ['regionId' => Requirement::POSITIVE_INT])]
    public function addFoodSharePoint(int $regionId, FoodSharePointForCreation $foodSharePoint, ValidatorInterface $validator): Response
    {
        $this->assertLoggedIn();
        $this->assertThereAreNoValidationErrors($validator, $foodSharePoint);

        if (!$this->currentUserUnits->mayBezirk($regionId)) {
            throw new AccessDeniedHttpException('Not a member of the region');
        }
        $regionType = $this->regionGateway->getType($regionId);
        if (!UnitType::isRegion($regionType) || !UnitType::isAccessibleRegion($regionType)) {
            throw new BadRequestHttpException('Food share points can only be added to regions');
        }
        $response = $this->foodSharePointTransactions->addFoodSharePoint($foodSharePoint);

        return $this->respondOK($response);
    }

    #[OA\Patch(summary: 'Edit an existing food share point.')]
    #[OA\RequestBody(content: new Model(type: FoodSharePointEditData::class))]
    #[ParamConverter('foodSharePointData', class: FoodSharePointEditData::class, converter: 'fos_rest.request_body')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid date')]
    #[Rest\Patch('foodSharePoints/{foodSharePointId}', requirements: ['foodSharePointId' => Requirement::POSITIVE_INT])]
    public function editFoodSharePoint(int $foodSharePointId, FoodSharePointEditData $foodSharePointData, ValidatorInterface $validator): Response
    {
        $this->assertLoggedIn();
        $this->assertThereAreNoValidationErrors($validator, $foodSharePointData);

        $foodSharePoint = $this->foodSharePointGateway->getFoodSharePoint($foodSharePointId);
        if (empty($foodSharePoint)) {
            throw new NotFoundHttpException('Food share point does not exist');
        }
        if (!$this->foodSharePointPermissions->mayEdit($foodSharePoint->regionId, $foodSharePointId)) {
            throw new AccessDeniedHttpException('Insufficient permissions to edit this foodSharePoint');
        }
        $regionType = $this->regionGateway->getType($foodSharePointData->regionId);
        if (!UnitType::isRegion($regionType) || !UnitType::isAccessibleRegion($regionType)) {
            throw new BadRequestHttpException('Food share points can only be edit to regions');
        }

        $this->foodSharePointTransactions->editFoodSharePoint($foodSharePointId, $foodSharePoint, $foodSharePointData);

        return $this->respondOK();
    }

    #[OA\Parameter(name: 'foodSharePointId', description: 'which foodSharePoint to delete', in: 'path', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[Rest\Delete('/foodSharePoints/{foodSharePointId}', name: 'remove_foodsharepoint', requirements: ['foodSharePointId' => Requirement::POSITIVE_INT])]
    public function removeFoodSharePoint(int $foodSharePointId): Response
    {
        $this->assertLoggedIn();

        $foodSharePoint = $this->foodSharePointGateway->getFoodSharePoint($foodSharePointId);
        if (empty($foodSharePoint)) {
            throw new NotFoundHttpException('Food share point does not exist');
        }

        if (!$this->foodSharePointPermissions->mayDeleteFoodSharePointOfRegion($foodSharePoint->regionId)) {
            throw new AccessDeniedHttpException('Insufficient permissions to remove this foodSharePoint.');
        }

        $this->foodSharePointTransactions->deleteFoodSharePoint($foodSharePointId);

        return $this->respondOK();
    }

    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[Rest\Get('/foodSharePoints/{foodSharePointId}/permissions', requirements: ['foodSharePointId' => Requirement::POSITIVE_INT])]
    public function foodSharePointPermissions(int $foodSharePointId): Response
    {
        $this->assertFoodSharePointExists($foodSharePointId);
        $regionId = $this->foodSharePointGateway->getFoodSharePoint($foodSharePointId)->regionId;
        $permission = new FoodSharePointPermission();
        if ($this->session->id()) {
            $followerType = $this->foodSharePointGateway->getFollowerStatus($foodSharePointId, $this->session->id());
            $permission->isFollower = $followerType >= FollowerType::FOLLOWER;
            $permission->mayEdit = $this->foodSharePointPermissions->mayEditFromManager($regionId, $followerType === FollowerType::FOOD_SHARE_POINT_MANAGER);
            $permission->mayDelete = $this->foodSharePointPermissions->mayDeleteFoodSharePointOfRegion($regionId);
        }

        return $this->respondOK($permission);
    }

    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[Rest\Post('/foodSharePoints/{foodSharePointId}/follow', requirements: ['foodSharePointId' => Requirement::POSITIVE_INT])]
    public function followFoodSharePoint(int $foodSharePointId, #[MapQueryParameter] bool $sendMails = false): Response
    {
        $this->assertLoggedIn();
        $this->assertFoodSharePointExists($foodSharePointId);
        $infoType = $sendMails ? InfoType::EMAIL : InfoType::BELL;
        $this->foodSharePointGateway->follow($this->session->id(), $foodSharePointId, $infoType);

        return $this->respondOK();
    }

    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[Rest\Delete('/foodSharePoints/{foodSharePointId}/follow', requirements: ['foodSharePointId' => Requirement::POSITIVE_INT])]
    public function unfollowFoodSharePoint(int $foodSharePointId): Response
    {
        $this->assertLoggedIn();
        $this->assertFoodSharePointExists($foodSharePointId);
        $this->foodSharePointGateway->unfollow($this->session->id(), $foodSharePointId);

        return $this->respondOK();
    }

    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[Rest\Post('/foodSharePoints/{foodSharePointId}/accept', requirements: ['foodSharePointId' => Requirement::POSITIVE_INT])]
    public function acceptFoodSharePoint(int $foodSharePointId): Response
    {
        $this->assertLoggedIn();
        $this->assertFoodSharePointExists($foodSharePointId);
        $regionId = $this->foodSharePointGateway->getFoodSharePoint($foodSharePointId)->regionId;
        if (!$this->foodSharePointPermissions->mayApproveFoodSharePointCreation($regionId)) {
            throw new AccessDeniedHttpException();
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
}
