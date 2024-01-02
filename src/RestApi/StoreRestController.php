<?php

namespace Foodsharing\RestApi;

use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Exception;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Core\DBConstants\Store\CooperationStatus;
use Foodsharing\Modules\Core\DBConstants\Store\Milestone;
use Foodsharing\Modules\Core\DBConstants\Store\StoreLogAction;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Store\DTO\CommonStoreMetadata;
use Foodsharing\Modules\Store\DTO\PatchStore;
use Foodsharing\Modules\Store\DTO\Store;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Modules\Store\StoreTransactionException;
use Foodsharing\Modules\Store\StoreTransactions;
use Foodsharing\Modules\Store\TeamStatus as TeamMembershipStatus;
use Foodsharing\Permissions\StorePermissions;
use Foodsharing\RestApi\Models\Store\CreateStoreModel;
use Foodsharing\RestApi\Models\Store\MinimalStoreModel;
use Foodsharing\RestApi\Models\Store\StorePaginationResult;
use Foodsharing\RestApi\Models\Store\StoreStatusForMemberModel;
use Foodsharing\Utility\TimeHelper;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class StoreRestController extends AbstractFOSRestController
{
    // literal constants
    private const NOT_LOGGED_IN = 'not logged in';
    private const ID = 'id';

    public function __construct(
        private Session $session,
        private FoodsaverGateway $foodsaverGateway,
        private StoreGateway $storeGateway,
        private StoreTransactions $storeTransactions,
        private StorePermissions $storePermissions,
        private RegionGateway $regionGateway,
        private BellGateway $bellGateway,
        private GroupFunctionGateway $groupFunctionGateway
    ) {
    }

    /**
     * Returns all common metadata which are required to manage stores.
     *
     * Some system parts have limits or options which needs to be checked in the frontend.
     * This endpoint provides the information about the limits and options,
     * so that the frontend can use them but the backend is responsible for the values.
     *
     * @OA\Tag(name="stores")
     * @OA\Response(
     * 		response="200",
     * 		description="Success.",
     *      @Model(type=CommonStoreMetadata::class)
     * )
     * @OA\Response(response="401", description="Not logged in")
     *
     * @Rest\Get("stores/meta-data")
     */
    public function getCommonStoreMetadata(): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('', self::NOT_LOGGED_IN);
        }

        $result = $this->storeTransactions->getCommonStoreMetadata(
            !$this->storePermissions->mayListStores());

        return $this->handleView($this->view($result, 200));
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
     * @Rest\Get("user/current/stores/details")
     *
     * @throws Exception
     */
    public function getStoresOfUser(): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('', self::NOT_LOGGED_IN);
        }

        if (!$this->storePermissions->mayListStores($this->session->id())) {
            throw new AccessDeniedHttpException('No permission see store list');
        }

        $stores = $this->storeTransactions->listOverviewInformationsOfStoresFromUser($this->session->id(), true);
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
     * @Rest\Get("region/{regionId}/stores", requirements={"regionId" = "\d+"})
     */
    public function getStoresOfRegion(int $regionId): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('', self::NOT_LOGGED_IN);
        }

        if (!$this->storePermissions->mayListStores()) {
            throw new AccessDeniedHttpException('No permission see store list');
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
     * @Rest\Get("/stores/{storeId}/information", requirements={"storeId" = "\d+"})
     */
    public function getStoreInformationAction(int $storeId)
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
        } catch (DatabaseNoValueFoundException $ex) {
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
     * @Rest\Get("/stores/{storeId}/member", requirements={"storeId" = "\d+"})
     */
    public function getStoreMembersAction(int $storeId)
    {
        $userId = $this->session->id();
        if (!$userId) {
            throw new UnauthorizedHttpException('');
        }

        try {
            $maySeeDetails = $this->storePermissions->maySeePhoneNumbers($storeId);
            $result = $this->storeTransactions->getMyStoreTeam($userId, $storeId, $maySeeDetails);

            return $this->handleView($this->view($result, 200));
        } catch (DatabaseNoValueFoundException $ex) {
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
     * @Rest\Get("/stores/{storeId}/permissions", requirements={"storeId" = "\d+"})
     */
    public function getStorePermissionsAction(int $storeId)
    {
        $userId = $this->session->id();
        if (!$userId) {
            throw new UnauthorizedHttpException('');
        }

        try {
            $store = $this->storeGateway->getMyStore($this->session->id(), $storeId);

            $teamConversationId = null;
            if ($this->storePermissions->mayChatWithRegularTeam($store)) {
                $teamConversationId = $store['team_conversation_id'];
            }

            $jumperConversationId = null;
            if ($this->storePermissions->mayChatWithJumperWaitingTeam($store)) {
                $jumperConversationId = $store['springer_conversation_id'];
            }

            $isOrgUser = $this->session->mayRole(Role::ORGA);
            $isAmbassador = false;
            $isCoordinator = false;

            if (!$isOrgUser) {
                $storeGroup = $this->groupFunctionGateway->getRegionFunctionGroupId($store['bezirk_id'], WorkgroupFunction::STORES_COORDINATION);
                if (empty($storeGroup)) {
                    if ($this->session->isAdminFor($store['bezirk_id'])) {
                        $isAmbassador = true;
                    }
                } elseif ($this->session->isAdminFor($storeGroup)) {
                    $isCoordinator = true;
                }
            }

            $params = [
                'isCoordinator' => $isCoordinator,
                'isAmbassador' => $isAmbassador,
                'isOrgUser' => $isOrgUser,
                'isJumper' => $store['jumper'],
                'isManager' => $store['verantwortlich'],
                'mayDoPickup' => $this->storePermissions->mayDoPickup($storeId),
                'teamConversationId' => $teamConversationId,
                'jumperConversationId' => $jumperConversationId,
                'mayEditStore' => $this->storePermissions->mayEditStore($storeId),
                'mayLeaveStoreTeam' => $this->storePermissions->mayLeaveStoreTeam($storeId, $this->session->id()),
                'storeId' => $storeId,
                'maySeePickupHistory' => $this->storePermissions->maySeePickupHistory($storeId),
                'maySeeStoreLog' => $this->storePermissions->maySeeStoreLog($storeId),
                'mayReadStoreWall' => $this->storePermissions->mayReadStoreWall($storeId),
                'mayWritePost' => $this->storePermissions->mayWriteStoreWall($storeId),
                'mayDeleteEverything' => $this->storePermissions->mayDeleteStoreWall($storeId),
                'maySeePickups' => $this->storePermissions->maySeePickups($storeId) && $store['betrieb_status_id'] === CooperationStatus::COOPERATION_STARTING || $store['betrieb_status_id'] === CooperationStatus::COOPERATION_ESTABLISHED,
            ];

            return $this->handleView($this->view($params, 200));
        } catch (DatabaseNoValueFoundException $ex) {
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
     * @Rest\Post("region/{regionId}/stores")
     * @ParamConverter("storeCreateInformation", converter="fos_rest.request_body")
     * @OA\Response(response=Response::HTTP_CREATED,
     *	description="Created the new store and informed region members provides",
     *  @Model(type=MinimalStoreModel::class)
     * )
     * @OA\Response(response=Response::HTTP_BAD_REQUEST, description="Invalid body data")
     * @OA\Response(response=Response::HTTP_UNAUTHORIZED, description="Not logged in")
     * @OA\Response(response=Response::HTTP_FORBIDDEN, description="No permission to create a store")
     */
    public function addStoreAction(int $regionId, CreateStoreModel $storeCreateInformation, ConstraintViolationListInterface $validationErrors): Response
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
     * Returns details of the store with the given ID. Returns 200 and the
     * store, 404 if the store does not exist, or 401 if not logged in.
     *
     * @OA\Tag(name="stores")
     * @Rest\Get("stores/{storeId}", requirements={"storeId" = "\d+"})
     * @OA\Response(response=Response::HTTP_OK, description="Store information")
     * @OA\Response(response=Response::HTTP_UNAUTHORIZED, description="Not logged in")
     * @OA\Response(response=Response::HTTP_FORBIDDEN, description="No permission to update store")
     * @OA\Response(response=Response::HTTP_NOT_FOUND, description="Store not found")
     */
    public function getStoreAction(int $storeId): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('', self::NOT_LOGGED_IN);
        }
        if (!$this->storePermissions->mayListStores()) {
            throw new AccessDeniedHttpException('invalid permissions');
        }
        $maySeeDetails = $this->storePermissions->mayAccessStore($storeId);

        $store = $this->storeGateway->getBetrieb($storeId, $maySeeDetails);

        if (!$store || !isset($store[self::ID])) {
            throw new NotFoundHttpException('Store does not exist.');
        }

        $store = RestNormalization::normalizeStore($store, $maySeeDetails);

        return $this->handleView($this->view(['store' => $store], Response::HTTP_OK));
    }

    /**
     * Allows to patch the store with information like the store team status.
     *
     * @OA\Tag(name="stores")
     * @Rest\Patch("stores/{storeId}/information", requirements={"storeId" = "\d+"})
     * @OA\RequestBody(@Model(type=PatchStore::class))
     * @ParamConverter("storeModel", converter="fos_rest.request_body")
     * @OA\Response(response=Response::HTTP_BAD_REQUEST, description="Invalid request data")
     * @OA\Response(response=Response::HTTP_UNAUTHORIZED, description="Not logged in")
     * @OA\Response(response=Response::HTTP_FORBIDDEN, description="No permission to update store")
     * @OA\Response(response=Response::HTTP_NOT_FOUND, description="Store not found")
     * @OA\Response(response=Response::HTTP_OK, description="Store information")
     */
    public function editStoreAction(int $storeId, PatchStore $storeModel, ConstraintViolationListInterface $validationErrors)
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

        return $this->getStoreAction($storeId);
    }

    /**
     * Provides a list of all foodsaver related stores and the next picks status.
     *
     * @OA\Tag(name="stores")
     * @OA\Tag(name="user")
     * @OA\Parameter(
     *      name="activeStores",
     *      in="query",
     *      description="filter unactive stores (e.g. store that do not cooperate)?",
     *      required=false,
     *      @OA\Schema(type="integer", enum={0,1})
     * ),
     * @OA\Response(
     * 		response="200",
     * 		description="Success.",
     *      @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=StoreStatusForMemberModel::class))
     *      )
     * )
     * @OA\Response(response="204", description="No foodsaver related stores found.")
     * @OA\Response(response="401", description="Not logged in")
     * @Rest\Get("user/current/stores")
     * @Rest\QueryParam(name="activeStores")
     */
    public function getListOfStoreStatusForCurrentFoodsaver(ParamFetcher $paramFetcher): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('', self::NOT_LOGGED_IN);
        }
        $activeStores = (bool)$paramFetcher->get('activeStores');

        $listOfStoreStatus = $this->storeTransactions->listAllStoreStatusForFoodsaver($this->session->id(), $activeStores);

        if ($listOfStoreStatus === []) {
            return $this->handleView($this->view([], 204));
        }

        $store_team_memberships = [];
        foreach ($listOfStoreStatus as $storeStatus) {
            $store_team_memberships[] = new StoreStatusForMemberModel($storeStatus);
        }

        return $this->handleView($this->view($store_team_memberships, 200));
    }

    /**
     * Get "wallposts" for store with given ID. Returns 200 and the comments,
     * 401 if not logged in, or 403 if you may not view this store.
     *
     * @OA\Tag(name="stores")
     * @Rest\Get("stores/{storeId}/posts", requirements={"storeId" = "\d+"})
     */
    public function getStorePosts(int $storeId): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('', self::NOT_LOGGED_IN);
        }
        if (!$this->storePermissions->mayReadStoreWall($storeId)) {
            throw new AccessDeniedHttpException();
        }

        $notes = $this->storeGateway->getStorePosts($storeId);
        if (empty($notes)) {
            $notes = [];
        }
        $notes = array_map(function ($n) {
            return RestNormalization::normalizeStoreNote($n);
        }, $notes);

        return $this->handleView($this->view($notes, 200));
    }

    /**
     * Write a new "wallpost" for the given store. Returns 200 and the created entry,
     * 401 if not logged in, or 403 if you may not view this store.
     *
     * @OA\Tag(name="stores")
     * @Rest\Post("stores/{storeId}/posts")
     * @Rest\RequestParam(name="text")
     */
    public function addStorePostAction(int $storeId, ParamFetcher $paramFetcher): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('', self::NOT_LOGGED_IN);
        }
        if (!$this->storePermissions->mayWriteStoreWall($storeId)) {
            throw new AccessDeniedHttpException();
        }

        $author = $this->session->id();
        $text = $paramFetcher->get('text');
        $note = [
            'foodsaver_id' => $author,
            'betrieb_id' => $storeId,
            'text' => $text,
            'zeit' => date('Y-m-d H:i:s'),
            'milestone' => Milestone::NONE,
            'last' => 1
        ];
        $postId = $this->storeGateway->addStoreWallpost($note);

        $storeName = $this->storeGateway->getStoreName($storeId);
        $userName = $this->session->user('name');
        $userPhoto = $this->session->user('photo');
        $team = $this->storeGateway->getStoreTeam($storeId);

        $teamWithoutPostAuthor = array_filter($team, function ($x) use ($author) {
            return $x['id'] !== $author;
        });

        $bellData = Bell::create(
            'store_wallpost_title',
            'store_wallpost',
            'fas fa-thumbtack',
            ['href' => '/?page=fsbetrieb&id=' . $storeId],
            [
                'user' => $userName,
                'name' => $storeName
            ],
            BellType::createIdentifier(BellType::STORE_WALL_POST, $storeId)
        );

        $this->bellGateway->addBell($teamWithoutPostAuthor, $bellData);

        $note = $this->storeGateway->getStoreWallpost($storeId, $postId);
        $note['name'] = $userName;
        $note['photo'] = $userPhoto;
        $post = RestNormalization::normalizeStoreNote($note);

        return $this->handleView($this->view(['post' => $post], 200));
    }

    /**
     * Deletes a post from the wall of a store. Returns 200 upon successful deletion,
     * 401 if not logged in, or 403 if you may not remove this particular "wallpost".
     *
     * @OA\Tag(name="stores")
     * @Rest\Delete("stores/{storeId}/posts/{postId}")
     */
    public function deleteStorePostAction(int $storeId, int $postId): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('', self::NOT_LOGGED_IN);
        }
        if (!$this->storePermissions->mayDeleteStoreWallPost($storeId, $postId)) {
            throw new AccessDeniedHttpException();
        }
        $result = $this->storeGateway->getStoreWallpost($storeId, $postId);

        $this->storeGateway->addStoreLog($result['betrieb_id'], $this->session->id(), $result['foodsaver_id'], new DateTime($result['zeit']), StoreLogAction::DELETED_FROM_WALL, $result['text']);

        $this->storeGateway->deleteStoreWallpost($storeId, $postId);

        return $this->handleView($this->view([], 200));
    }

    /**
     * Request to join a store team.
     *
     * @OA\Parameter(name="storeId", in="path", @OA\Schema(type="integer"), description="for which store to apply")
     * @OA\Parameter(name="userId", in="path", @OA\Schema(type="integer"), description="user that wants to be accepted")
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Insufficient permissions to be member of a store team")
     * @OA\Response(response="404", description="Store does not exist")
     * @OA\Response(response="422", description="Already applied or already member of this store team")
     * @OA\Tag(name="stores")
     * @Rest\Post("stores/{storeId}/requests/{userId}")
     */
    public function requestStoreTeamMembershipAction(int $storeId, int $userId): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('', self::NOT_LOGGED_IN);
        }
        if (!$this->storeGateway->storeExists($storeId)) {
            throw new NotFoundHttpException('Store does not exist.');
        }
        if (!$this->storePermissions->mayJoinStoreRequest($storeId, $userId)) {
            throw new AccessDeniedHttpException();
        }
        if ($this->storeGateway->getUserTeamStatus($userId, $storeId) !== TeamMembershipStatus::NoMember) {
            throw new UnprocessableEntityHttpException('User has already applied or is already member of this store.');
        }

        $this->storeTransactions->requestStoreTeamMembership($storeId, $userId);

        return $this->handleView($this->view([], 200));
    }

    /**
     * Get applications to store team.
     *
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Insufficient permissions")
     * @OA\Response(response="404", description="Store does not exist")
     * @OA\Tag(name="stores")
     * @Rest\Get("stores/{storeId}/requests")
     */
    public function listStoreTeamMembershipRequestsAction(int $storeId): Response
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

        $response = $this->storeTransactions->getStoreApplications($userId, $storeId);

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
     * @Rest\Patch("stores/{storeId}/requests/{userId}")
     * @Rest\RequestParam(name="moveToStandby", nullable=true, description="whether the new member should become part of the standby team instead of the regular team")
     */
    public function acceptStoreRequestAction(int $storeId, int $userId, ParamFetcher $paramFetcher): Response
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
     * @Rest\Delete("stores/{storeId}/requests/{userId}")
     */
    public function declineStoreRequestAction(int $storeId, int $userId): Response
    {
        $this->handleEditTeamExceptions($storeId, $userId, false, true);
        if ($this->storeGateway->getUserTeamStatus($userId, $storeId) !== TeamMembershipStatus::Applied) {
            throw new NotFoundHttpException('Request does not exist.');
        }

        $this->storeTransactions->declineStoreRequest($storeId, $userId);

        if ($this->session->id() == $userId) {
            $LogAction = StoreLogAction::REQUEST_CANCELLED;
        } else {
            $LogAction = StoreLogAction::REQUEST_DECLINED;
        }

        $this->storeGateway->addStoreLog($storeId, $this->session->id(), $userId, null, $LogAction);

        return $this->handleView($this->view([], 200));
    }

    /**
     * Adds user to store team, without a request to join from that user.
     *
     * @OA\Parameter(name="storeId", in="path", @OA\Schema(type="integer"), description="which store to manage")
     * @OA\Parameter(name="userId", in="path", @OA\Schema(type="integer"), description="which user to add to the store team")
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Insufficient permissions to manage this store team")
     * @OA\Response(response="404", description="Store does not exist")
     * @OA\Response(response="422", description="User is already, or cannot be, part of this store team")
     * @OA\Tag(name="stores")
     * @Rest\Post("stores/{storeId}/members/{userId}")
     */
    public function addStoreMemberAction(int $storeId, int $userId): Response
    {
        $this->handleEditTeamExceptions($storeId, $userId, true);
        $userRole = $this->foodsaverGateway->getRole($userId);
        if (!$this->storePermissions->mayAddUserToStoreTeam($storeId, $userId, $userRole)) {
            throw new UnprocessableEntityHttpException();
        }

        $this->storeTransactions->addStoreMember($storeId, $userId);

        return $this->handleView($this->view([], 200));
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
     * @Rest\Delete("stores/{storeId}/members/{userId}")
     */
    public function removeStoreMemberAction(int $storeId, int $userId): Response
    {
        $this->handleEditTeamExceptions($storeId, $userId, false, true);
        if (!$this->storePermissions->mayLeaveStoreTeam($storeId, $userId)) {
            throw new UnprocessableEntityHttpException();
        }

        $this->storeTransactions->removeStoreMember($storeId, $userId);

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
     * @Rest\Patch("stores/{storeId}/managers/{userId}")
     */
    public function addStoreManagerAction(int $storeId, int $userId): Response
    {
        $this->handleEditTeamExceptions($storeId, $userId, true);
        $userRole = $this->foodsaverGateway->getRole($userId);
        if (!$this->storePermissions->mayBecomeStoreManager($storeId, $userId, $userRole)) {
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
     * @Rest\Delete("stores/{storeId}/managers/{userId}")
     */
    public function removeStoreManagerAction(int $storeId, int $userId): Response
    {
        $this->handleEditTeamExceptions($storeId, $userId);
        if (!$this->storePermissions->mayLoseStoreManagement($storeId, $userId)) {
            throw new UnprocessableEntityHttpException();
        }

        $this->storeTransactions->downgradeResponsibleMember($storeId, $userId);

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
     * @Rest\Patch("stores/{storeId}/members/{userId}/standby")
     */
    public function moveMemberToStandbyTeamAction(int $storeId, int $userId): Response
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

        $this->storeTransactions->moveMemberToStandbyTeam($storeId, $userId);

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
     * @Rest\Delete("stores/{storeId}/members/{userId}/standby")
     */
    public function moveUserToRegularTeamAction(int $storeId, int $userId): Response
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
     * @Rest\Get("stores/{storeId}/log/{fromDate}/{toDate}/{storeLogActionIds}", requirements={
     *      "storeId" = "\d+",
     *      "fromDate" = "[^/]+",
     *      "toDate" = "[^/]+",
     *      "storeLogActionIds" = "(\d+,)*\d+"
     * })
     */
    public function showStoreLogHistoryAction(int $storeId, string $fromDate, string $toDate, string $storeLogActionIds): Response
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
        if (is_null($fromDate) || is_null($toDate)) {
            throw new BadRequestHttpException('Invalid date format');
        }

        if (Carbon::now()->subMonths(6)->subDay() > $fromDate) { // 6 months + 1 day for rounding
            throw new BadRequestHttpException('Cannot access store log more than 6 months back.');
        }

        $storeLogActions = explode(',', $storeLogActionIds);
        $storeLogEntries = $this->storeGateway->getStoreLogsByActionType($storeId, $storeLogActions, $fromDate, $toDate);
        $extendedLogEntries = $this->extendStoreLogWithFoodsaverProfilData($storeId, $storeLogEntries);

        $timeZone = new DateTimeZone('Europe/Berlin');
        $timeZoneOffset = $timeZone->getOffset(new DateTime('now', $timeZone));

        $extendedLogEntries = array_map(function ($logEntry) use ($timeZoneOffset) {
            $correctedSlotDate = new DateTime($logEntry['date_reference']);
            $correctedSlotDate->add(new \DateInterval("PT{$timeZoneOffset}S"));
            $logEntry['date_reference'] = $correctedSlotDate->format(DATE_ATOM);

            $correctedPerformedAtDate = new DateTime($logEntry['performed_at']);
            $logEntry['performed_at'] = $correctedPerformedAtDate->format(DATE_ATOM);

            return $logEntry;
        }, $extendedLogEntries);

        return $this->handleView($this->view($extendedLogEntries, 200));
    }

    private function extendStoreLogWithFoodsaverProfilData(int $storeId, array $storeLogEntries): array
    {
        $storeTeam = [];
        foreach ($this->storeGateway->getStoreTeam($storeId) as $teamMember) {
            $foodsaverId = $teamMember['id'];
            $storeTeam[$foodsaverId] = RestNormalization::normalizeUser($teamMember);
        }

        $mergedStoreLogEntries = [];

        foreach ($storeLogEntries as $entry) {
            $actingFoodsaverId = $entry['acting_foodsaver_id'];
            unset($entry['acting_foodsaver_id']);
            $entry['acting_foodsaver'] = $storeTeam[$actingFoodsaverId] ?? RestNormalization::normalizeUser($this->foodsaverGateway->getFoodsaver($actingFoodsaverId));

            $affectedFoodsaverId = $entry['affected_foodsaver_id'];
            unset($entry['affected_foodsaver_id']);
            if (!is_null($affectedFoodsaverId)) {
                $entry['affected_foodsaver'] = $storeTeam[$affectedFoodsaverId] ?? RestNormalization::normalizeUser($this->foodsaverGateway->getFoodsaver($affectedFoodsaverId));
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
    private function handleEditTeamExceptions(int $storeId, int $targetId, bool $allowExternals = false, bool $mayEditOneself = false)
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
        if (!$allowExternals && $this->storeGateway->getUserTeamStatus($targetId, $storeId) === TeamMembershipStatus::NoMember) {
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
