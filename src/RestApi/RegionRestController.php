<?php

namespace Foodsharing\RestApi;

use Exception;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\RegionOptionType;
use Foodsharing\Modules\Core\DBConstants\Region\RegionPinStatus;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Event\EventGateway;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Region\RegionTransactions;
use Foodsharing\Modules\Settings\SettingsGateway;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Modules\Unit\DTO\UserUnit;
use Foodsharing\Modules\WorkGroup\WorkGroupTransactions;
use Foodsharing\Permissions\RegionPermissions;
use Foodsharing\Permissions\WorkGroupPermissions;
use Foodsharing\RestApi\Models\Region\RegionForAdministration;
use Foodsharing\RestApi\Models\Region\UserRegionModel;
use Foodsharing\Utility\ImageHelper;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use OpenApi\Attributes as OA2;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA2\Tag(name: 'region')]
class RegionRestController extends AbstractFoodsharingRestController
{
    // literal constants
    private const LAT = 'lat';
    private const LON = 'lon';
    private const DESC = 'desc';
    private const STATUS = 'status';

    public function __construct(
        private readonly SettingsGateway $settingsGateway,
        private readonly BellGateway $bellGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly RegionPermissions $regionPermissions,
        private readonly RegionGateway $regionGateway,
        private readonly StoreGateway $storeGateway,
        private readonly ImageHelper $imageHelper,
        private readonly GroupFunctionGateway $groupFunctionGateway,
        private readonly RegionTransactions $regionTransactions,
        private readonly WorkGroupPermissions $workGroupPermissions,
        private readonly WorkGroupTransactions $workGroupTransactions,
        private readonly EventGateway $eventGateway,
        protected Session $session,
    ) {
    }

    #[Rest\Post('region/{regionId}/join', requirements: ['regionId' => '\d+'])]
    public function joinRegion(int $regionId): Response
    {
        $sessionId = $this->session->id();
        if ($sessionId === null) {
            throw new UnauthorizedHttpException('');
        }

        $region = $this->regionGateway->getRegion($regionId);
        if (!$region) {
            throw new NotFoundHttpException();
        }
        if (!$this->regionPermissions->mayJoinRegion($regionId)) {
            throw new AccessDeniedHttpException();
        }

        $this->regionGateway->linkBezirk($sessionId, $regionId);

        if (!$this->session->getCurrentRegionId()) {
            $this->settingsGateway->logChangedSetting($sessionId, ['bezirk_id' => 0], ['bezirk_id' => $regionId], ['bezirk_id']);
            $this->foodsaverGateway->updateProfile($sessionId, ['bezirk_id' => $regionId]);
        }

        $regionWelcomeGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId($regionId, WorkgroupFunction::WELCOME);
        if ($regionWelcomeGroupId) {
            $welcomeBellRecipients = $this->foodsaverGateway->getAdminsOrAmbassadors($regionWelcomeGroupId);
        } else {
            $welcomeBellRecipients = $this->foodsaverGateway->getAdminsOrAmbassadors($regionId);
        }

        $bellData = Bell::create(
            'new_foodsaver_title',
            $this->regionTransactions->getJoinMessage($this->session->id(), $this->session->isVerified()),
            $this->imageHelper->img($this->session->user('photo'), 50),
            ['href' => '/profile/' . (int)$sessionId . ''],
            [
                'name' => $this->session->user('name') . ' ' . $this->session->user('nachname'),
                'bezirk' => $region['name']
            ],
            BellType::createIdentifier(BellType::NEW_FOODSAVER_IN_REGION, $sessionId),
            true
        );
        $this->bellGateway->addBell($welcomeBellRecipients, $bellData);

        return $this->handleView($this->view([], 200));
    }

    /**
     * Returns a list of all region of the user.
     *
     * @OA\Tag(name="my")
     * @OA\Response(
     * 		response="200",
     * 		description="Success returns list of related regions of user",
     *      @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=UserRegionModel::class))
     *      )
     * )
     * @OA\Response(response="401", description="Not logged in.")
     */
    #[Rest\Get('user/current/regions')]
    public function listMyRegion(): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('');
        }
        $fsId = $this->session->id();

        $regions = $this->regionTransactions->getUserRegions($fsId);

        $rspRegions = array_map(fn (UserUnit $region): UserRegionModel => UserRegionModel::createFrom($region), $regions);

        return $this->handleView($this->view($rspRegions, 200));
    }

    /**
     * Removes the current user from a region. Returns 403 if not logged in, 400 if the region does not exist, 409 if
     * the user is still an active store manager in the region, or 200 if the user was removed from the region or was
     * not a member of that region. That means that after a 200 result the user will definitely not be a member of that
     * region anymore.
     *
     * @OA\Parameter(name="regionId", in="path", @OA\Schema(type="integer"), description="which region or group to leave")
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="400", description="Region or group does not exist")
     * @OA\Response(response="403", description="Insufficient permissions")
     * @OA\Response(response="409", description="User is still an active manager in the region")
     */
    #[Rest\Post('region/{regionId}/leave', requirements: ['regionId' => '\d+'])]
    public function leaveRegion(int $regionId): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('');
        }
        /** @var int $sessionId */
        $sessionId = $this->session->id();
        if (empty($this->regionGateway->getRegion($regionId))) {
            throw new BadRequestHttpException('region does not exist or is root region.');
        }

        if (in_array($this->session->id(), $this->storeGateway->getStoreManagersOf($regionId))) {
            throw new ConflictHttpException('still an active store manager in that region');
        }

        $this->eventGateway->deleteInvitesForFoodSaver($regionId, $sessionId);
        $this->foodsaverGateway->deleteFromRegion($regionId, $sessionId, $sessionId);

        return $this->handleView($this->view([], 200));
    }

    /**
     * Sets the options for region.
     *
     * @OA\Parameter(name="regionId", in="path", @OA\Schema(type="integer"), description="which region to set options for")
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Insufficient permissions")
     */
    #[Rest\Post('region/{regionId}/options', requirements: ['regionId' => '\d+'])]
    #[Rest\RequestParam(name: 'enableReportButton')]
    #[Rest\RequestParam(name: 'enableMediationButton')]
    #[Rest\RequestParam(name: 'regionPickupRuleActive')]
    #[Rest\RequestParam(name: 'regionPickupRuleTimespan')]
    #[Rest\RequestParam(name: 'regionPickupRuleLimit')]
    #[Rest\RequestParam(name: 'regionPickupRuleLimitDay')]
    #[Rest\RequestParam(name: 'regionPickupRuleInactive')]
    public function setRegionOptions(ParamFetcher $paramFetcher, int $regionId): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('');
        }
        if (!$this->regionPermissions->maySetRegionOptionsReportButtons($regionId) && !$this->regionPermissions->maySetRegionOptionsRegionPickupRule($regionId)) {
            throw new AccessDeniedHttpException();
        }

        $params = $paramFetcher->all();
        if ($this->regionPermissions->maySetRegionOptionsReportButtons($regionId)) {
            if (isset($params['enableReportButton'])) {
                $this->regionGateway->setRegionOption($regionId, RegionOptionType::ENABLE_REPORT_BUTTON, strval(intval($params['enableReportButton'])));
            }
            if (isset($params['enableMediationButton'])) {
                $this->regionGateway->setRegionOption($regionId, RegionOptionType::ENABLE_MEDIATION_BUTTON, strval(intval($params['enableMediationButton'])));
            }
        }
        if ($this->regionPermissions->maySetRegionOptionsRegionPickupRule($regionId)) {
            if (isset($params['regionPickupRuleActive'])) {
                $this->regionGateway->setRegionOption($regionId, RegionOptionType::REGION_PICKUP_RULE_ACTIVE, strval(intval($params['regionPickupRuleActive'])));
            }
            if (isset($params['regionPickupRuleTimespan'])) {
                $this->regionGateway->setRegionOption($regionId, RegionOptionType::REGION_PICKUP_RULE_TIMESPAN_DAYS, strval(intval($params['regionPickupRuleTimespan'])));
            }
            if (isset($params['regionPickupRuleLimit'])) {
                $this->regionGateway->setRegionOption($regionId, RegionOptionType::REGION_PICKUP_RULE_LIMIT_NUMBER, strval(intval($params['regionPickupRuleLimit'])));
            }
            if (isset($params['regionPickupRuleLimitDay'])) {
                $this->regionGateway->setRegionOption($regionId, RegionOptionType::REGION_PICKUP_RULE_LIMIT_DAY_NUMBER, strval(intval($params['regionPickupRuleLimitDay'])));
            }
            if (isset($params['regionPickupRuleInactive'])) {
                $this->regionGateway->setRegionOption($regionId, RegionOptionType::REGION_PICKUP_RULE_INACTIVE_HOURS, strval(intval($params['regionPickupRuleInactive'])));
            }
        }

        return $this->handleView($this->view([], 200));
    }

    /**
     * Returns the region options for a specific region.
     *
     * @OA\Parameter(name="regionId", in="path", @OA\Schema(type="integer"), description="ID of the region")
     * @OA\Response(response="200", description="Success", @OA\Schema(type="array", @OA\Items(
     *     @OA\Property(property="regionPickupRuleActive", type="boolean"),
     *     @OA\Property(property="regionPickupRuleTimespan", type="integer"),
     *     @OA\Property(property="regionPickupRuleLimit", type="integer"),
     *     @OA\Property(property="regionPickupRuleLimitDay", type="integer"),
     *     @OA\Property(property="regionPickupRuleInactive", type="integer"),
     * )))
     * @OA\Response(response="401", description="Not logged in")
     *
     * @throws Exception
     */
    #[Rest\Get('region/{regionId}/options', requirements: ['regionId' => '\d+'])]
    public function getRegionOptions(int $regionId): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('');
        }

        $options = $this->regionGateway->getRegionOptions($regionId);

        return $this->handleView($this->view($options, 200));
    }

    private function isValidNumber($value, float $lowerBound, float $upperBound): bool
    {
        return !is_null($value) && !is_nan($value)
            && ($lowerBound <= $value) && ($upperBound >= $value);
    }

    /**
     * Sets the pin for region.
     *
     * @OA\Parameter(name="regionId", in="path", @OA\Schema(type="integer"), description="which region to set pin for")
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Insufficient permissions")
     */
    #[Rest\Post('region/{regionId}/pin', requirements: ['regionId' => '\d+'])]
    #[Rest\RequestParam(name: 'lat')]
    #[Rest\RequestParam(name: 'lon')]
    #[Rest\RequestParam(name: 'desc')]
    #[Rest\RequestParam(name: 'status', requirements: '\d+')]
    public function setRegionPin(ParamFetcher $paramFetcher, int $regionId): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('');
        }

        if ($regionId < 0) {
            throw new AccessDeniedHttpException();
        }

        if (!$this->regionPermissions->maySetRegionPin($regionId)) {
            throw new AccessDeniedHttpException();
        }

        $lat = $paramFetcher->get(self::LAT);
        $lon = $paramFetcher->get(self::LON);
        $desc = $paramFetcher->get(self::DESC);
        $status = $paramFetcher->get(self::STATUS);
        if (!$this->isValidNumber($lat, -90.0, 90.0) || !$this->isValidNumber($lon, -180.0, 180.0)) {
            throw new BadRequestHttpException('Invalid Latitude or Longitude');
        }
        if (!RegionPinStatus::isValid($status)) {
            throw new BadRequestHttpException('Invalid status');
        }

        $this->regionGateway->setRegionPin($regionId, $lat, $lon, $desc, $status);

        return $this->handleView($this->view([], 200));
    }

    #[OA2\Get(
        summary: 'Returns a list of all subregions including working groups of a region.',
        description: 'The result is empty if the region does not exist.'
    )]
    #[OA2\Parameter(name: 'regionId', in: 'path', schema: new OA2\Schema(type: 'integer'), description: 'ID of the region or 0 for the root region')]
    #[OA2\Response(response: Response::HTTP_OK, description: 'success')]
    #[OA2\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[Rest\Get('region/{regionId}/children', requirements: ['regionId' => '\d+'])]
    #[Rest\QueryParam(name: 'includeWorkingGroups', nullable: true)]
    public function listRegionChildren(int $regionId, ParamFetcher $paramFetcher): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('');
        }
        $includeWorkingGroups = !is_null($paramFetcher->get('includeWorkingGroups'));
        if ($includeWorkingGroups && !$this->session->mayBezirk($regionId)) {
            throw new UnauthorizedHttpException('');
        }

        $children = $this->regionGateway->getRegionByParent($regionId, $includeWorkingGroups);

        return $this->handleView($this->view($children, Response::HTTP_OK));
    }

    #[OA2\Get(summary: 'Returns a list of all members for a region.')]
    #[OA2\Parameter(name: 'regionId', in: 'path', schema: new OA2\Schema(type: 'integer'), description: 'ID of the region or 0 for the root region')]
    #[OA2\Response(response: Response::HTTP_OK, description: 'success')]
    #[OA2\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA2\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    #[Rest\Get('region/{regionId}/members', requirements: ['regionId' => '\d+'])]
    public function listMembers(int $regionId): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('');
        }

        if (!$this->regionPermissions->maySeeRegionMembers($regionId)) {
            throw new AccessDeniedHttpException();
        }

        $region = $this->regionGateway->getRegion($regionId);
        if ($region['type'] === UnitType::WORKING_GROUP) {
            $maySeeDetails = $this->workGroupPermissions->mayEdit($region);
        } else {
            $maySeeDetails = $this->regionPermissions->mayHandleFoodsaverRegionMenu($regionId);
        }
        $response = $this->foodsaverGateway->listActiveFoodsaversByRegion($regionId, $maySeeDetails);

        return $this->handleView($this->view($response, 200));
    }

    #[OA2\Get(
        summary: 'Removes a member from a region or working group.',
        description: 'If the user was not a member of the region/group, nothing happens.'
    )]
    #[OA2\Parameter(name: 'regionId', in: 'path', schema: new OA2\Schema(type: 'integer'), description: 'ID of the region or 0 for the root region')]
    #[OA2\Response(response: Response::HTTP_OK, description: 'success')]
    #[OA2\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA2\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    #[OA2\Response(response: Response::HTTP_NOT_FOUND, description: 'Region not found')]
    #[Rest\Delete('region/{regionId}/members/{memberId}', requirements: ['regionId' => '\d+', 'memberId' => '\d+'])]
    public function removeMember(int $regionId, int $memberId): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('');
        }

        $region = $this->regionGateway->getRegion($regionId);

        if (empty($region)) {
            throw new NotFoundHttpException();
        }

        if (UnitType::isGroup($region['type'])) {
            if (!$this->workGroupPermissions->mayEdit($region)) {
                throw new AccessDeniedHttpException();
            }
            $this->regionGateway->removeRegionAdmin($regionId, $memberId);
            $this->workGroupTransactions->removeMemberFromGroup($regionId, $memberId);
        } else {
            if (!$this->regionPermissions->mayDeleteFoodsaverFromRegion($regionId)) {
                throw new AccessDeniedHttpException();
            }
            $this->foodsaverGateway->deleteFromRegion($regionId, $memberId, $this->session->id());
        }

        return $this->handleView($this->view([], 200));
    }

    /**
     * Sets an user as Admin / Ambassador of a region / workgroup.
     *
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Insufficient permissions")
     * @OA\Response(response="404", description="Region not found")
     */
    #[Rest\Post('region/{regionId}/members/{memberId}/admin', requirements: ['regionId' => '\d+', 'memberId' => '\d+'])]
    public function setAdminOrAmbassador(int $regionId, int $memberId): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('');
        }

        $region = $this->regionGateway->getRegion($regionId);

        if (empty($region)) {
            throw new NotFoundHttpException();
        }

        if (UnitType::isGroup($region['type'])) {
            if (!$this->workGroupPermissions->mayEdit($region)) {
                throw new AccessDeniedHttpException();
            }
        } else {
            if (!$this->regionPermissions->maySetRegionAdmin()) {
                throw new AccessDeniedHttpException();
            }
            $memberRole = $this->foodsaverGateway->getRole($memberId);
            if ($memberRole && $memberRole->isLower(Role::AMBASSADOR)) {
                throw new AccessDeniedHttpException();
            }
        }

        $this->regionGateway->setRegionAdmin($regionId, $memberId);

        return $this->handleView($this->view([], 200));
    }

    /**
     * Sets an user as Admin / Ambassador of a region / workgroup.
     *
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Insufficient permissions")
     * @OA\Response(response="404", description="Region not found")
     */
    #[Rest\Delete('region/{regionId}/members/{memberId}/admin', requirements: ['regionId' => '\d+', 'memberId' => '\d+'])]
    public function removeAdminOrAmbassador(int $regionId, int $memberId): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('');
        }

        $region = $this->regionGateway->getRegion($regionId);

        if (empty($region)) {
            throw new NotFoundHttpException();
        }

        if (UnitType::isGroup($region['type'])) {
            if (!$this->workGroupPermissions->mayEdit($region)) {
                throw new AccessDeniedHttpException();
            }
        } else {
            if (!$this->regionPermissions->mayRemoveRegionAdmin()) {
                throw new AccessDeniedHttpException();
            }
            $member_role = $this->foodsaverGateway->getRole($memberId);
            if ($member_role && $member_role->isLower(Role::AMBASSADOR)) {
                throw new AccessDeniedHttpException();
            }
        }

        $this->regionGateway->removeRegionAdmin($regionId, $memberId);

        return $this->handleView($this->view([], 200));
    }

    #[OA2\Get(summary: 'Returns the properties of a specific region.')]
    #[OA2\Parameter(name: 'regionId', in: 'path', schema: new OA2\Schema(type: 'integer'), description: 'ID of the region or 0 for the root region')]
    #[OA2\Response(response: Response::HTTP_OK, description: 'success')]
    #[OA2\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA2\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    #[Rest\Get('region/{regionId}', requirements: ['regionId' => '\d+'])]
    public function getRegion(int $regionId): Response
    {
        if (!$this->regionPermissions->mayAdministrateRegions()) {
            throw new AccessDeniedHttpException('');
        }

        $region = $this->regionTransactions->getRegionForEditing($regionId);

        return $this->handleView($this->view($region, 200));
    }

    #[OA2\Get(summary: 'Edits the region using the given data.')]
    #[OA2\Parameter(name: 'regionId', in: 'path', schema: new OA2\Schema(type: 'integer'), description: 'ID of the region')]
    #[OA2\Response(response: Response::HTTP_OK, description: 'success')]
    #[OA2\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA2\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    #[Rest\Patch('region/{regionId}', requirements: ['regionId' => '\d+'])]
    #[ParamConverter(data: 'region', class: RegionForAdministration::class, converter: 'fos_rest.request_body')]
    public function editRegion(int $regionId, RegionForAdministration $region, ValidatorInterface $validator)
    {
        if (!$this->regionPermissions->mayAdministrateRegions()) {
            throw new AccessDeniedHttpException('');
        }

        $errors = $validator->validate($region);
        if ($errors->count() > 0) {
            $firstError = $errors->get(0);
            throw new BadRequestHttpException(json_encode(['field' => $firstError->getPropertyPath(), 'message' => $firstError->getMessage()]));
        }
        $region->id = $regionId;

        if ($region->type !== UnitType::WORKING_GROUP && $region->workgroupFunction) {
            throw new BadRequestHttpException('Only Working groups can have a workgroup function.');
        }

        if ($region->type === UnitType::WORKING_GROUP) {
            if (!$this->regionPermissions->mayAdministrateWorkgroupFunction($region->workgroupFunction)) {
                throw new AccessDeniedHttpException('You cannot set restricted workgroup functions.');
            }
        }

        $this->regionTransactions->editRegion($region);

        return $this->handleView($this->view($region, 200));
    }

    #[OA2\Get(summary: 'Adds a region using the given data.')]
    #[OA2\Response(response: Response::HTTP_OK, description: 'success')]
    #[OA2\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid data')]
    #[OA2\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA2\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    #[Rest\Post('region', requirements: ['regionId' => '\d+'])]
    #[ParamConverter(data: 'region', class: RegionForAdministration::class, converter: 'fos_rest.request_body')]
    public function addRegion(RegionForAdministration $region, ValidatorInterface $validator)
    {
        if (!$this->regionPermissions->mayAdministrateRegions()) {
            throw new AccessDeniedHttpException('');
        }

        $this->assertThereAreNoValidationErrors($validator, $region);

        if ($region->type !== UnitType::WORKING_GROUP && $region->workgroupFunction) {
            throw new BadRequestHttpException('Only Working groups can have a workgroup function.');
        }
        if ($region->type === UnitType::WORKING_GROUP) {
            if (!$this->regionPermissions->mayAdministrateWorkgroupFunction($region->workgroupFunction)) {
                throw new AccessDeniedHttpException('You cannot set restricted workgroup functions.');
            }
        }

        $this->regionTransactions->addRegion($region);

        return $this->respondOK();
    }
}
