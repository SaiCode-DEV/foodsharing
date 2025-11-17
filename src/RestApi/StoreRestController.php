<?php

namespace Foodsharing\RestApi;

use Carbon\Carbon;
use Exception;
use Foodsharing\Lib\Db\Mem;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Core\DBConstants\Store\CooperationStatus;
use Foodsharing\Modules\Core\DBConstants\Store\StoreLogAction;
use Foodsharing\Modules\Core\DBConstants\StoreTeam\MembershipStatus;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Message\MessageGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Store\DTO\CommonStoreMetadata;
use Foodsharing\Modules\Store\DTO\PatchStore;
use Foodsharing\Modules\Store\DTO\Store;
use Foodsharing\Modules\Store\DTO\StoreApplicationMessage;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Modules\Store\StoreTransactionException;
use Foodsharing\Modules\Store\StoreTransactions;
use Foodsharing\Modules\Store\TeamStatus as TeamMembershipStatus;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Permissions\ProfilePermissions;
use Foodsharing\Permissions\StorePermissions;
use Foodsharing\RestApi\Models\Store\CreateStoreModel;
use Foodsharing\RestApi\Models\Store\MinimalStoreModel;
use Foodsharing\RestApi\Models\Store\StorePaginationResult;
use Foodsharing\RestApi\Models\Store\StoreStatusForMemberModel;
use Foodsharing\Utility\TimeHelper;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use OpenApi\Attributes as OA2;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class StoreRestController extends AbstractFoodsharingRestController
{
    // literal constants
    private const string NOT_LOGGED_IN = 'not logged in';

    public function __construct(
        protected Session $session,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly StoreGateway $storeGateway,
        private readonly StoreTransactions $storeTransactions,
        private readonly StorePermissions $storePermissions,
        private readonly RegionGateway $regionGateway,
        private readonly GroupFunctionGateway $groupFunctionGateway,
        private readonly ProfilePermissions $profilePermissions,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
        private readonly RateLimiterFactory $locationChangeLimiterFactory,
        private readonly MessageGateway $messageGateway,
        private readonly Mem $mem,
    ) {
    }

    #[OA2\Tag(name: 'stores')]
    #[OA2\Response(response: Response::HTTP_OK, description: 'Success.', content: new OA2\JsonContent(ref: new Model(type: CommonStoreMetadata::class))
    )]
    #[OA2\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA2\Response(response: Response::HTTP_NOT_MODIFIED, description: 'User has the current version')]
    #[Rest\Get('stores/meta-data')]
    #[Rest\QueryParam(name: 'version', requirements: Requirement::POSITIVE_INT, default: 0, description: 'The version of the cache that the user has saved locally')]
    #[Rest\QueryParam(name: 'hasChains', requirements: '0|1', default: 0, description: 'Whether the user has cached store chains locally')]
    public function getCommonStoreMetadata(ParamFetcher $paramFetcher): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('', self::NOT_LOGGED_IN);
        }

        $userVersion = (int)$paramFetcher->get('version');
        $currentVersion = (int)$this->mem->get(StoreTransactions::STORE_METADATA_VERSION_KEY);
        $hasChains = (bool)$paramFetcher->get('hasChains');
        $shouldHaveChains = $this->storePermissions->mayListStores();
        if ($currentVersion === $userVersion && ($hasChains || !$shouldHaveChains)) {
            return $this->handleView($this->view(null, Response::HTTP_NOT_MODIFIED));
        }

        $metadata = $this->storeTransactions->getCommonStoreMetadataFromCache(!$shouldHaveChains, $currentVersion);

        return $this->respondOK($metadata);
    }

    /**
     * Returns a list of stores where the user is a member of reduced store information.
     *
     * @OA\Tag(name="stores")
     * @OA\Tag(name="user")
     * @OA\Response(
     *        response="200",
     *        description="Success.",
     *      @Model(type=StorePaginationResult::class)
     * )
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Forbidden to access store list")
     *
     * @throws Exception
     */
    #[Rest\Get('user/{userId}/stores/details')]
    public function getStoresOfUser(int $userId): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('', self::NOT_LOGGED_IN);
        }

        if (!$this->storePermissions->mayListStores($userId)) {
            throw new AccessDeniedHttpException('No permission see store list');
        }

        $stores = $this->storeTransactions->listOverviewInformationsOfStoresFromUser($userId, true);
        $result = new StorePaginationResult();
        $result->total = count($stores);
        $result->stores = $stores;

        return $this->handleView($this->view($result, 200));
    }

    /**
     * Provides store identifiers for stores of a region.
     *
     * @OA\Tag(name="stores")
     * @OA\Tag(name="region")
     * @OA\Response(
     * 		response="200",
     * 		description="Success.",
     *      @Model(type=StorePaginationResult::class)
     * )
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Forbidden to access store list")
     */
    #[Rest\Get('region/{regionId}/stores', requirements: ['regionId' => '\d+'])]
    public function getStoresOfRegion(int $regionId): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('', self::NOT_LOGGED_IN);
        }

        if (!$this->storePermissions->mayListStores()) {
            throw new AccessDeniedHttpException('No permission see store list');
        }

        /*
         * @TODO: This deactivates store lists for Europe and countries because it needs to much memory on the server.
         * Can be remove when there is pagination.
         * See also RegionPermissions:maySeeRegionMembers for the same problem with region members.
         */
        if (in_array($regionId, [RegionIDs::EUROPE, RegionIDs::GERMANY, RegionIDs::AUSTRIA, RegionIDs::SWITZERLAND])) {
            throw new AccessDeniedHttpException();
        }

        $stores = $this->storeTransactions->listOverviewInformationsOfStoresInRegion($regionId, true);
        $result = new StorePaginationResult();
        $result->total = count($stores);
        $result->stores = $stores;

        return $this->handleView($this->view($result, 200));
    }

    /**
     * Provides store information, contacts and options for a store id.
     *
     * Depending on the access permission some information are not provided.
     *
     * @OA\Tag(name="stores")
     * @OA\Response(
     * 		response=Response::HTTP_OK,
     * 		description="Success.",
     *      @Model(type=Store::class)
     * )
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Not allowed to see/list stores")
     * @OA\Response(response="404", description="Store not found")
     */
    #[Rest\Get('/stores/{storeId}/information', requirements: ['storeId' => '\d+'])]
    public function getStoreInformation(int $storeId)
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('', self::NOT_LOGGED_IN);
        }

        if (!$this->storePermissions->mayListStores()) {
            throw new AccessDeniedHttpException('No permission see store list');
        }

        try {
            $maySeeDetails = $this->storePermissions->mayAccessStore($storeId);
            $maySeeSensitiveDetails = $this->storePermissions->mayEditStore($storeId);

            $result = $this->storeTransactions->getStore($storeId, $maySeeDetails, $maySeeSensitiveDetails);

            return $this->handleView($this->view($result, 200));
        } catch (DatabaseNoValueFoundException) {
            throw new NotFoundHttpException('Store not found.');
        }
    }

    /**
     * Provides store members.
     *
     * Depending on the access permission some information are not provided.
     *
     * @OA\Tag(name="stores")
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Not allowed to see/list stores")
     * @OA\Response(response="404", description="Store not found")
     */
    #[Rest\Get('/stores/{storeId}/member', requirements: ['storeId' => '\d+'])]
    public function getStoreMembers(int $storeId): Response
    {
        $this->assertLoggedIn();

        if (!$this->storeGateway->storeExists($storeId)) {
            throw new NotFoundHttpException('Store not found.');
        }

        // Member-only (org/coord flows are enforced elsewhere; default to deny if not a member)
        if (
            $this->storeGateway->getUserTeamStatus($this->session->id(), $storeId) === TeamMembershipStatus::NoMember
            && !$this->storePermissions->mayCoordianteRegionStores($storeId)
        ) {
            throw new AccessDeniedHttpException('Not allowed to see store members.');
        }

        try {
            // controls sensitive details (e.g., phone numbers) even for members
            $maySeeDetails = $this->storePermissions->maySeePhoneNumbers($storeId);
            $maySeeDistance = $this->storePermissions->maySeeMemberDistance($storeId);
            $result = $this->storeTransactions->getMyStoreTeam($storeId, $maySeeDetails, $maySeeDistance);

            return $this->respondOK($result);
        } catch (DatabaseNoValueFoundException) {
            throw new NotFoundHttpException('Store not found.');
        }
    }

    /**
     * Provides store permissions.
     **
     * @OA\Tag(name="stores")
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Not allowed to see/list stores")
     * @OA\Response(response="404", description="Store not found")
     */
    #[Rest\Get('/stores/{storeId}/permissions', requirements: ['storeId' => '\d+'])]
    public function getStorePermissions(int $storeId): Response
    {
        // 401 if not logged in
        $this->assertLoggedIn();

        // 404 if the store does not exist
        if (!$this->storeGateway->storeExists($storeId)) {
            throw new NotFoundHttpException('Store not found.');
        }

        // 403 if logged in but not a member (aligns with /stores/{storeId}/member)
        if (
            $this->storeGateway->getUserTeamStatus($this->session->id(), $storeId) === TeamMembershipStatus::NoMember
            && !$this->storePermissions->mayCoordianteRegionStores($storeId)
        ) {
            throw new AccessDeniedHttpException('Not allowed to see store permissions.');
        }

        try {
            $store = $this->storeGateway->getMyStore($this->session->id(), $storeId);

            $teamConversationId = null;
            if ($this->storePermissions->mayChatWithRegularTeam($store)
                && $this->messageGateway->mayConversation($this->session->id(), $store['team_conversation_id'])) {
                $teamConversationId = $store['team_conversation_id'];
            }

            $jumperConversationId = null;
            if ($this->storePermissions->mayChatWithJumperWaitingTeam($store)
                && $this->messageGateway->mayConversation($this->session->id(), $store['springer_conversation_id'])) {
                $jumperConversationId = $store['springer_conversation_id'];
            }

            $isOrgUser = $this->session->mayRole(Role::ORGA);
            $isAmbassador = false;
            $isCoordinator = false;

            if (!$isOrgUser) {
                $storeGroup = $this->groupFunctionGateway->getRegionFunctionGroupId($store['bezirk_id'], WorkgroupFunction::STORES_COORDINATION);
                if (empty($storeGroup)) {
                    if ($this->currentUserUnits->isAdminFor($store['bezirk_id'])) {
                        $isAmbassador = true;
                    }
                } elseif ($this->currentUserUnits->isAdminFor($storeGroup)) {
                    $isCoordinator = true;
                }
            }

            $params = [
                'isCoordinator' => $isCoordinator,
                'isAmbassador' => $isAmbassador,
                'isOrgUser' => $isOrgUser,
                'isJumper' => $store['jumper'],
                'isManager' => $store['verantwortlich'],
                'maySeePickup' => $this->storePermissions->maySeePickups($storeId),
                'teamConversationId' => $teamConversationId,
                'jumperConversationId' => $jumperConversationId,
                'mayEditStore' => $this->storePermissions->mayEditStore($storeId),
                'mayLeaveStoreTeam' => $this->storePermissions->mayLeaveStoreTeam($storeId, $this->session->id()),
                'storeId' => $storeId,
                'maySeePickupHistory' => $this->storePermissions->maySeePickupHistory($storeId),
                'maySeeStoreLog' => $this->storePermissions->maySeeStoreLog($storeId),
                'maySeePickups' => $this->storePermissions->maySeePickups($storeId) || $store['betrieb_status_id'] === CooperationStatus::COOPERATION_ESTABLISHED,
                'mayDeleteStore' => $this->storePermissions->mayDeleteStore($storeId),
            ];

            return $this->respondOK($params);
        } catch (DatabaseNoValueFoundException) {
            throw new NotFoundHttpException('Store not found.');
        }
    }

    /**
     * Creates a new store.
     *
     * This method creates a new store. The store will initial contains the provided information.
     * Additional the platform will prepare the chat channels for team and sprinters.
     *
     * The calling user is added as first store responsible in the store team.
     * You can add an initial first post on the store wall for all following members.
     *
     * After creation the platform informs all members of the related region about the new store.
     *
     * @OA\Tag(name="stores")
     * @OA\RequestBody(@Model(type=CreateStoreModel::class))
     * @OA\Response(response=Response::HTTP_CREATED,
     *    description="Created the new store and informed region members provides",
     *  @Model(type=MinimalStoreModel::class)
     * )
     * @OA\Response(response=Response::HTTP_BAD_REQUEST, description="Invalid body data")
     * @OA\Response(response=Response::HTTP_UNAUTHORIZED, description="Not logged in")
     * @OA\Response(response=Response::HTTP_FORBIDDEN, description="No permission to create a store")
     * @throws StoreTransactionException
     */
    #[Rest\Post('region/{regionId}/stores')]
    #[ParamConverter('storeCreateInformation', converter: 'fos_rest.request_body')]
    public function addStore(int $regionId, CreateStoreModel $storeCreateInformation, ConstraintViolationListInterface $validationErrors): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('', self::NOT_LOGGED_IN);
        }

        if (!$this->storePermissions->mayCreateStore($regionId)) {
            throw new AccessDeniedHttpException('No permission to create store for this region');
        }

        $this->throwBadRequestExceptionOnError($validationErrors);

        $storeModel = new MinimalStoreModel();
        $store = $storeCreateInformation->store->toCreateStore();
        $store->regionId = $regionId;
        $storeModel->id = $this->storeTransactions->createStore($store, $this->session->id(), $storeCreateInformation->firstPost);

        return $this->handleView($this->view($storeModel, Response::HTTP_CREATED));
    }

    /**
     * Allows to patch the store with information like the store team status.
     *
     * @OA\Tag(name="stores")
     * @OA\RequestBody(@Model(type=PatchStore::class))
     * @OA\Response(response=Response::HTTP_BAD_REQUEST, description="Invalid request data")
     * @OA\Response(response=Response::HTTP_UNAUTHORIZED, description="Not logged in")
     * @OA\Response(response=Response::HTTP_FORBIDDEN, description="No permission to update store")
     * @OA\Response(response=Response::HTTP_NOT_FOUND, description="Store not found")
     * @OA\Response(response=Response::HTTP_OK, description="Empty response on success")
     */
    #[Rest\Patch('stores/{storeId}/information', requirements: ['storeId' => '\d+'])]
    #[ParamConverter('storeModel', converter: 'fos_rest.request_body')]
    public function editStore(int $storeId, PatchStore $storeModel, ConstraintViolationListInterface $validationErrors, Request $request)
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('', self::NOT_LOGGED_IN);
        }

        if (!$this->storePermissions->mayEditStore($storeId)) {
            if ($this->storeGateway->storeExists($storeId)) {
                throw new AccessDeniedHttpException('invalid permissions');
            } else {
                throw new NotFoundHttpException('Store not found');
            }
        }

        $oldStore = $this->storeGateway->getStore($storeId);
        if (!is_null($storeModel->location) && ($oldStore->location->lat !== $storeModel->location->lat || $oldStore->location->lon !== $storeModel->location->lon)) {
            $this->checkRateLimit($request, $this->locationChangeLimiterFactory, $storeId);
        }

        $this->throwBadRequestExceptionOnError($validationErrors);

        if (!$this->session->mayRole(Role::ORGA)) {
            if (!empty($storeModel->regionId) && !$this->regionGateway->hasMember($this->session->id(), $storeModel->regionId)) {
                throw new AccessDeniedHttpException('no member of other region');
            }
        }

        try {
            $hasChanged = $this->storeTransactions->updateStore($storeId, $storeModel);
            if (!$hasChanged) {
                throw new BadRequestHttpException('No settings to change');
            }
        } catch (StoreTransactionException $ex) {
            if ($ex->getMessage() == StoreTransactionException::STORE_CATEGORY_NOT_EXISTS ||
                $ex->getMessage() == StoreTransactionException::STORE_CHAIN_NOT_EXISTS ||
                $ex->getMessage() == StoreTransactionException::INVALID_STORE_TEAM_STATUS ||
                $ex->getMessage() == StoreTransactionException::INVALID_COOPERATION_STATUS ||
                $ex->getMessage() == StoreTransactionException::INVALID_PUBLIC_TIMES) {
                throw new BadRequestHttpException($ex->getMessage());
            } else {
                throw $ex;
            }
        }

        return $this->respondOK();
    }

    #[OA2\Tag(name: 'stores')]
    #[OA2\Tag(name: 'user')]
    #[OA2\Parameter(
        name: 'activeStores',
        description: 'filter unactive stores (e.g. store that do not cooperate)?',
        in: 'query',
        required: false,
        schema: new OA2\Schema(type: 'integer', enum: [0, 1])
    )]
    #[OA2\Response(
        response: '200',
        description: 'Success.',
        content: new OA2\JsonContent(
            type: 'array',
            items: new OA2\Items(ref: new Model(type: StoreStatusForMemberModel::class))
        )
    )]
    #[OA2\Response(response: Response::HTTP_NO_CONTENT, description: 'No foodsaver related stores found.')]
    #[OA2\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[Rest\Get('user/{userId}/stores', requirements: ['userId' => '\d+'])]
    #[Rest\Get('user/current/stores')]
    #[Rest\QueryParam(name: 'activeStores')]
    public function getListOfStoreStatusForUser(ParamFetcher $paramFetcher, ?string $userId = null): Response
    {
        $this->assertLoggedIn();

        $targetUserId = $userId === null ? $this->session->id() : (int)$userId;

        if (!$this->profilePermissions->maySeeStores($targetUserId)) {
            throw new AccessDeniedHttpException('No permission see store list');
        }

        $activeStores = (bool)$paramFetcher->get('activeStores');

        $listOfStoreStatus = $this->storeTransactions->listAllStoreStatusForFoodsaver($targetUserId, $activeStores);

        if ($listOfStoreStatus === []) {
            return $this->handleView($this->view([], Response::HTTP_NO_CONTENT));
        }

        $store_team_memberships = [];
        foreach ($listOfStoreStatus as $storeStatus) {
            $store_team_memberships[] = new StoreStatusForMemberModel($storeStatus);
        }

        return $this->respondOK($store_team_memberships);
    }

    /**
     * Request to join a store team.
     *
     * @OA\Parameter(name="storeId", in="path", @OA\Schema(type="integer"), description="for which store to apply")
     * @OA\RequestBody(@Model(type=StoreApplicationMessage::class))
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Insufficient permissions to be member of a store team")
     * @OA\Response(response="404", description="Store does not exist")
     * @OA\Response(response="422", description="Already applied or already member of this store team")
     * @OA\Tag(name="stores")
     */
    #[Rest\Post('stores/{storeId}/requests')]
    #[ParamConverter(data: 'message', converter: 'fos_rest.request_body')]
    public function requestStoreTeamMembership(int $storeId, StoreApplicationMessage $message, ValidatorInterface $validator): Response
    {
        $this->assertLoggedIn();
        $this->assertThereAreNoValidationErrors($validator, $message);
        if (!$this->storeGateway->storeExists($storeId)) {
            throw new NotFoundHttpException('Store does not exist.');
        }
        if (!$this->storePermissions->mayJoinStoreRequest($storeId)) {
            throw new AccessDeniedHttpException();
        }
        if ($this->storeGateway->getUserTeamStatus($this->session->id(), $storeId) !== TeamMembershipStatus::NoMember) {
            throw new UnprocessableEntityHttpException('User has already applied or is already member of this store.');
        }

        // $message = isset($message->message) ? $message->message : null;
        $this->storeTransactions->requestStoreTeamMembership($storeId, $this->session->id(), $message->message);

        return $this->respondOK();
    }

    /**
     * Get applications to store team.
     *
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Insufficient permissions")
     * @OA\Response(response="404", description="Store does not exist")
     * @OA\Tag(name="stores")
     */
    #[Rest\Get('stores/{storeId}/requests')]
    public function listStoreTeamMembershipRequests(int $storeId): Response
    {
        $userId = $this->session->id();
        if (!$userId) {
            throw new UnauthorizedHttpException('', self::NOT_LOGGED_IN);
        }
        if (!$this->storeGateway->storeExists($storeId)) {
            throw new NotFoundHttpException('Store does not exist.');
        }
        if (!$this->storePermissions->mayEditStore($storeId)) {
            throw new AccessDeniedHttpException();
        }

        $response = $this->storeTransactions->getStoreApplications($storeId);

        return $this->handleView($this->view($response, 200));
    }

    /**
     * Accepts a user's request for joining a store.
     *
     * @OA\Parameter(name="storeId", in="path", @OA\Schema(type="integer"), description="for which store to accept a request")
     * @OA\Parameter(name="userId", in="path", @OA\Schema(type="integer"), description="who should be accepted")
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Insufficient permissions to accept requests")
     * @OA\Response(response="404", description="Store or request does not exist")
     * @OA\Tag(name="stores")
     */
    #[Rest\Patch('stores/{storeId}/requests/{userId}')]
    #[Rest\RequestParam(name: 'moveToStandby', nullable: true, description: 'whether the new member should become part of the standby team instead of the regular team')]
    public function acceptStoreRequest(int $storeId, int $userId, ParamFetcher $paramFetcher): Response
    {
        $this->handleEditTeamExceptions($storeId, $userId, true);
        if ($this->storeGateway->getUserTeamStatus($userId, $storeId) !== TeamMembershipStatus::Applied) {
            throw new NotFoundHttpException('Request does not exist.');
        }

        $moveToStandby = boolval($paramFetcher->get('moveToStandby'));
        $this->storeTransactions->acceptStoreRequest($storeId, $userId, $moveToStandby);

        return $this->handleView($this->view([], 200));
    }

    /**
     * Removes the user's own request or denies another user's request for a store.
     *
     * @OA\Parameter(name="storeId", in="path", @OA\Schema(type="integer"), description="for which store to remove a request")
     * @OA\Parameter(name="userId", in="path", @OA\Schema(type="integer"), description="whose request should be removed")
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Insufficient permissions to remove the request")
     * @OA\Response(response="404", description="Store or request does not exist")
     * @OA\Tag(name="stores")
     */
    #[Rest\RequestParam(name: 'message', nullable: true)]
    #[Rest\Delete('stores/{storeId}/requests/{userId}')]
    public function declineStoreRequest(int $storeId, int $userId, ParamFetcher $paramFetcher): Response
    {
        $this->handleEditTeamExceptions($storeId, $userId, false, true);
        if ($this->storeGateway->getUserTeamStatus($userId, $storeId) !== TeamMembershipStatus::Applied) {
            throw new NotFoundHttpException('Request does not exist.');
        }

        $message = $paramFetcher->get('message');
        $this->storeTransactions->declineStoreRequest($storeId, $userId, $message);

        if ($this->session->id() == $userId) {
            $logAction = StoreLogAction::REQUEST_CANCELLED;
        } else {
            $logAction = StoreLogAction::REQUEST_DECLINED;
        }

        $this->storeGateway->addStoreLog(
            $storeId,
            $this->session->id(),
            $userId,
            null,
            $logAction,
            reason: $message
        );

        return $this->handleView($this->view([], 200));
    }

    #[OA2\Tag(name: 'stores')]
    #[OA2\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA2\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted for this store')]
    #[Route('/stores/{storeId}/invitations', requirements: ['storeId' => Requirement::POSITIVE_INT], methods: ['GET'])]
    public function listStoreTeamInvitations(int $storeId): Response
    {
        $this->handleEditTeamExceptions($storeId);
        $invitations = $this->storeGateway->getInvitations($storeId);

        return $this->respondOK($invitations);
    }

    /**
     * Invites a user to the store team, without a request to join from that user.
     *
     * @OA\Parameter(name="storeId", in="path", @OA\Schema(type="integer"), description="which store to manage")
     * @OA\Parameter(name="userId", in="path", @OA\Schema(type="integer"), description="which user to add to the store team")
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Insufficient permissions to manage this store team")
     * @OA\Response(response="404", description="Store does not exist")
     * @OA\Response(response="422", description="User is already, or cannot be, part of this store team")
     * @OA\Tag(name="stores")
     */
    #[Route('stores/{storeId}/invitations/{userId}', methods: ['POST'])]
    public function inviteStoreMember(int $storeId, int $userId): Response
    {
        $this->handleEditTeamExceptions($storeId, $userId, true);
        $userRole = $this->foodsaverGateway->getRole($userId);
        if (!$userRole || !$this->storePermissions->mayInviteUserToStoreTeam($storeId, $userId, $userRole)) {
            throw new UnprocessableEntityHttpException();
        }

        $invitation = $this->storeTransactions->inviteStoreMember($storeId, $userId);

        return $this->respondOK($invitation);
    }

    #[OA2\Tag(name: 'stores')]
    #[OA2\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA2\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted for this store')]
    #[Route('stores/{storeId}/invitations/{userId}', requirements: [
        'storeId' => Requirement::POSITIVE_INT,
        'userId' => Requirement::POSITIVE_INT,
    ], methods: ['DELETE'])]
    public function withdrawStoreTeamInvitation(int $storeId, int $userId): Response
    {
        $this->handleEditTeamExceptions($storeId);
        $this->storeTransactions->withdrawStoreTeamInvitation($storeId, $userId);

        return $this->respondOK();
    }

    #[OA2\Tag(name: 'stores')]
    #[OA2\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA2\Response(response: Response::HTTP_FORBIDDEN, description: 'Not invited to this store')]
    #[Route('stores/{storeId}/invitations', requirements: ['storeId' => Requirement::POSITIVE_INT], methods: ['PATCH'])]
    public function acceptStoreTeamInvitation(int $storeId): Response
    {
        $this->assertLoggedIn();
        if ($this->storeGateway->getUserTeamStatus($this->session->id(), $storeId) !== TeamMembershipStatus::Invited) {
            throw new AccessDeniedHttpException('You are not invited to this store team.');
        }
        $this->storeTransactions->acceptStoreTeamInvitation($storeId, $this->session->id());

        return $this->respondOK();
    }

    #[OA2\Tag(name: 'stores')]
    #[OA2\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA2\Response(response: Response::HTTP_FORBIDDEN, description: 'Not invited to this store')]
    #[Route('stores/{storeId}/invitations', requirements: ['storeId' => Requirement::POSITIVE_INT], methods: ['DELETE'])]
    public function declineStoreTeamInvitation(int $storeId): Response
    {
        $this->assertLoggedIn();
        if ($this->storeGateway->getUserTeamStatus($this->session->id(), $storeId) !== TeamMembershipStatus::Invited) {
            throw new AccessDeniedHttpException('You are not invited to this store team.');
        }
        $this->storeTransactions->declineStoreTeamInvitation($storeId, $this->session->id());

        return $this->respondOK();
    }

    /**
     * Removes user from store team.
     *
     * @OA\Parameter(name="storeId", in="path", @OA\Schema(type="integer"), description="which store to manage")
     * @OA\Parameter(name="userId", in="path", @OA\Schema(type="integer"), description="which user to remove from the store team")
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Insufficient permissions to manage this store team (if user is not yourself)")
     * @OA\Response(response="404", description="Store does not exists or user is not a member of it")
     * @OA\Response(response="422", description="User cannot currently leave this team")
     * @OA\Tag(name="stores")
     */
    #[Rest\RequestParam(name: 'message', nullable: true)]
    #[Rest\Delete('stores/{storeId}/members/{userId}')]
    public function removeStoreMember(int $storeId, int $userId, ParamFetcher $paramFetcher): Response
    {
        $this->handleEditTeamExceptions($storeId, $userId, false, true);
        if (!$this->storePermissions->mayLeaveStoreTeam($storeId, $userId)) {
            throw new UnprocessableEntityHttpException();
        }

        $message = $paramFetcher->get('message');
        $this->storeTransactions->removeStoreMember($storeId, $userId, $message);

        return $this->handleView($this->view([], 200));
    }

    /**
     * Promotes a user to store manager.
     *
     * @OA\Parameter(name="storeId", in="path", @OA\Schema(type="integer"), description="which store to manage")
     * @OA\Parameter(name="userId", in="path", @OA\Schema(type="integer"), description="which user to add as manager")
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Insufficient permissions to manage this store team")
     * @OA\Response(response="404", description="Store does not exist")
     * @OA\Response(response="422", description="User cannot become manager of this store")
     * @OA\Tag(name="stores")
     */
    #[Rest\Patch('stores/{storeId}/managers/{userId}')]
    public function addStoreManager(int $storeId, int $userId): Response
    {
        $this->handleEditTeamExceptions($storeId, $userId, true);
        $userRole = $this->foodsaverGateway->getRole($userId);
        if (!$userRole || !$this->storePermissions->mayBecomeStoreManager($storeId, $userId, $userRole)) {
            throw new UnprocessableEntityHttpException();
        }

        $this->storeTransactions->makeMemberResponsible($storeId, $userId);

        return $this->handleView($this->view([], 200));
    }

    /**
     * Demotes a user from store manager to regular store team member.
     *
     * @OA\Parameter(name="storeId", in="path", @OA\Schema(type="integer"), description="which store to manage")
     * @OA\Parameter(name="userId", in="path", @OA\Schema(type="integer"), description="which user to remove as manager")
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Insufficient permissions to manage this store team")
     * @OA\Response(response="404", description="Store does not exists or user is not a member of it")
     * @OA\Response(response="422", description="User cannot lose responsibility for this store")
     * @OA\Tag(name="stores")
     */
    #[Rest\RequestParam(name: 'message', nullable: true)]
    #[Rest\Delete('stores/{storeId}/managers/{userId}')]
    public function removeStoreManager(int $storeId, int $userId, ParamFetcher $paramFetcher): Response
    {
        $this->handleEditTeamExceptions($storeId, $userId);
        $errorMessage = '';
        $cannotLeave = $this->storePermissions->mayLoseStoreManagement($storeId, $userId, $errorMessage);
        if (!$cannotLeave) {
            throw new UnprocessableEntityHttpException($errorMessage);
        }

        // Message is mandatory for this action
        $message = $paramFetcher->get('message');

        $this->storeTransactions->downgradeResponsibleMember($storeId, $userId, $message);

        return $this->handleView($this->view([], 200));
    }

    /**
     * Moves a store-team member from the regular team to the standby team.
     * Will also succeed if the member was already part of the standby team.
     *
     * @OA\Parameter(name="storeId", in="path", @OA\Schema(type="integer"), description="team of which store to manage")
     * @OA\Parameter(name="userId", in="path", @OA\Schema(type="integer"), description="who should be moved to the standby team")
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Insufficient permissions to manage this store team")
     * @OA\Response(response="404", description="User is not a member of this store")
     * @OA\Tag(name="stores")
     */
    #[Rest\RequestParam(name: 'message', nullable: true)]
    #[Rest\Patch('stores/{storeId}/members/{userId}/standby')]
    public function moveMemberToStandbyTeam(int $storeId, int $userId, ParamFetcher $paramFetcher): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('', self::NOT_LOGGED_IN);
        }
        if (!$this->storePermissions->mayEditStoreTeam($storeId)) {
            throw new AccessDeniedHttpException();
        }
        if ($this->storeGateway->getUserTeamStatus($userId, $storeId) === TeamMembershipStatus::NoMember) {
            throw new NotFoundHttpException('User is not a member of this store.');
        }

        $message = $paramFetcher->get('message');
        $this->storeTransactions->moveMemberToStandbyTeam($storeId, $userId, $message);

        return $this->handleView($this->view([], 200));
    }

    /**
     * Moves a store-team member from the standby team to the regular team.
     * Will also succeed if the member was already part of the regular team.
     *
     * @OA\Parameter(name="storeId", in="path", @OA\Schema(type="integer"), description="team of which store to manage")
     * @OA\Parameter(name="userId", in="path", @OA\Schema(type="integer"), description="who should be moved to the regular store team")
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Insufficient permissions to manage this store team")
     * @OA\Response(response="404", description="User is not a member of this store")
     * @OA\Tag(name="stores")
     */
    #[Rest\Delete('stores/{storeId}/members/{userId}/standby')]
    public function moveUserToRegularTeam(int $storeId, int $userId): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('', self::NOT_LOGGED_IN);
        }
        if (!$this->storePermissions->mayEditStoreTeam($storeId)) {
            throw new AccessDeniedHttpException();
        }

        if ($this->storeGateway->getUserTeamStatus($userId, $storeId) === TeamMembershipStatus::NoMember) {
            throw new NotFoundHttpException('User is not a member of this store.');
        }

        $this->storeTransactions->moveMemberToRegularTeam($storeId, $userId);

        return $this->handleView($this->view([], 200));
    }

    /**
     * Returns an array of log entries with foodsaver and log information.
     * The log contains only entries from the past 7 days.
     *
     * @OA\Parameter(name="storeId", in="path", @OA\Schema(type="integer"))
     * @OA\Parameter(name="fromDate", in="path", @OA\Schema(type="string"), description="The fist date from which to include actions")
     * @OA\Parameter(name="toDate", in="path", @OA\Schema(type="string"), description="The last date from which to include actions")
     * @OA\Parameter(name="storeLogActionIds", in="path", @OA\Schema(type="string"), description="The ids of the actions, seperated by commas like: 1,2,3")
     * @OA\Tag(name="stores")
     */
    #[Rest\Get('stores/{storeId}/log/{fromDate}/{toDate}/{storeLogActionIds}', requirements: ['storeId' => '\d+', 'fromDate' => '[^/]+', 'toDate' => '[^/]+', 'storeLogActionIds' => '(\d+,)*\d+'])]
    #[Rest\QueryParam(name: 'limit', requirements: '\d+', default: '100', description: 'How many bells to return.')]
    #[Rest\QueryParam(name: 'offset', requirements: '\d+', default: '0', description: 'Offset for returned bells.')]
    public function showStoreLogHistory(int $storeId, string $fromDate, string $toDate, string $storeLogActionIds, ParamFetcher $paramFetcher): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('', self::NOT_LOGGED_IN);
        }

        $storeLogActions = explode(',', $storeLogActionIds);

        if (count($storeLogActions) === 1 && $storeLogActions['0'] == StoreLogAction::SIGN_UP_SLOT) {
            if (!$this->storePermissions->maySeePickupSlotDateTime($storeId)) {
                throw new AccessDeniedHttpException();
            }
        } else {
            if (!$this->storePermissions->maySeeStoreLog($storeId)) {
                throw new AccessDeniedHttpException();
            }
        }

        $fromDate = TimeHelper::parsePickupDate($fromDate);
        $toDate = TimeHelper::parsePickupDate($toDate);

        if (Carbon::now()->subMonths(6)->subDay() > $fromDate) { // 6 months + 1 day for rounding
            throw new BadRequestHttpException('Cannot access store log more than 6 months back.');
        }

        $storeLogActions = explode(',', $storeLogActionIds);
        $pagination = $this->getPagination($paramFetcher);

        $storeLogEntries = $this->storeGateway->getStoreLogsByActionType($storeId, $storeLogActions, $fromDate, $toDate, $pagination);
        $extendedLogEntries = $this->extendStoreLogWithFoodsaverProfilData($storeId, $storeLogEntries);

        return $this->respondOK($extendedLogEntries);
    }

    /**
     * Deletes a store.
     * Only allowed for stores that never had any pickup.
     *
     * @OA\Parameter(name="storeId", in="path", @OA\Schema(type="integer"), description="the store to delete")
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Insufficient permissions to delete this store team")
     * @OA\Response(response="404", description="User is not a member of this store")
     * @OA\Tag(name="stores")
     */
    #[Rest\Delete('stores/{storeId}')]
    public function deleteStore(int $storeId): Response
    {
        $this->assertLoggedIn();
        if (!$this->storePermissions->mayDeleteStore($storeId)) {
            throw new AccessDeniedHttpException();
        }

        $this->storeTransactions->deleteStore($storeId);

        return $this->respondOK();
    }

    private function extendStoreLogWithFoodsaverProfilData(int $storeId, array $storeLogEntries): array
    {
        $storeTeam = [];
        foreach ($this->storeGateway->getStoreTeam($storeId, [MembershipStatus::MEMBER, MembershipStatus::JUMPER]) as $teamMember) {
            $foodsaverId = $teamMember['id'];
            $storeTeam[$foodsaverId] = new Profile($teamMember);
        }

        $mergedStoreLogEntries = [];

        foreach ($storeLogEntries as $entry) {
            $actingFoodsaverId = $entry['acting_foodsaver_id'];
            unset($entry['acting_foodsaver_id']);
            $entry['acting_foodsaver'] = $storeTeam[$actingFoodsaverId] ?? $this->foodsaverGateway->getProfile($actingFoodsaverId);

            $affectedFoodsaverId = $entry['affected_foodsaver_id'];
            unset($entry['affected_foodsaver_id']);
            if (!is_null($affectedFoodsaverId)) {
                $entry['affected_foodsaver'] = $storeTeam[$affectedFoodsaverId] ?? $this->foodsaverGateway->getProfile($affectedFoodsaverId);
            } else {
                $entry['affected_foodsaver'] = null;
            }
            $mergedStoreLogEntries[] = $entry;
        }

        return $mergedStoreLogEntries;
    }

    /**
     * Makes sure an edit to the team can be performed and throws an exception otherwise.
     * The asserted properties are:
     *  - Session is logged in
     *  - Store exists
     *  - Session may edit the store team (edits to the requesting user are additionally allowed with flag 'mayEditOneself')
     *  - Given target user is the stores team (check disabled with flag 'allowExternals').
     *
     * @param int $storeId The id of the store
     * @param int $targetId The id of the affected user
     * @param bool $allowExternals Whether to allow the targeted user to be not in the team
     * @param bool $mayEditOneself Whether to allow the action if the executing user is the target user
     *
     * @return void
     */
    private function handleEditTeamExceptions(int $storeId, ?int $targetId = null, bool $allowExternals = false, bool $mayEditOneself = false)
    {
        $sessionId = $this->session->id();
        if (!$sessionId) {
            throw new UnauthorizedHttpException('', self::NOT_LOGGED_IN);
        }
        if (!$this->storeGateway->storeExists($storeId)) {
            throw new NotFoundHttpException('Store does not exist.');
        }

        // Session may edit target user (mayEditStoreTeam OR (session is targetUser AND 'mayEditOneself' flag is set))
        if (!($mayEditOneself && $sessionId == $targetId) && !$this->storePermissions->mayEditStoreTeam($storeId)) {
            throw new AccessDeniedHttpException();
        }

        // Target user is in Team (or externals are allowed)
        if (isset($targetId) && !$allowExternals && $this->storeGateway->getUserTeamStatus($targetId, $storeId) === TeamMembershipStatus::NoMember) {
            throw new NotFoundHttpException('User is not a member of this store.');
        }
    }

    /**
     * Check if a Constraint violation is found and if it exist it throws an BadRequestExeption.
     *
     * @param ConstraintViolationListInterface $errors Validation result
     *
     * @throws BadRequestHttpException if violation is detected
     */
    private function throwBadRequestExceptionOnError(ConstraintViolationListInterface $errors): void
    {
        if ($errors->count() > 0) {
            $firstError = $errors->get(0);
            $relevantErrorContent = ['field' => $firstError->getPropertyPath(), 'message' => $firstError->getMessage()];
            throw new BadRequestHttpException(json_encode($relevantErrorContent));
        }
    }
}
