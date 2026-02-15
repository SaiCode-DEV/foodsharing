<?php

namespace Foodsharing\RestApi;

use Carbon\Carbon;
use Exception;
use Foodsharing\Lib\Db\Mem;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Store\StoreLogAction;
use Foodsharing\Modules\Core\DBConstants\StoreTeam\MembershipStatus;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Store\DTO\CommonStoreMetadata;
use Foodsharing\Modules\Store\DTO\PatchStore;
use Foodsharing\Modules\Store\DTO\Store;
use Foodsharing\Modules\Store\DTO\StoreApplicationMessage;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Modules\Store\StoreListFormat;
use Foodsharing\Modules\Store\StoreTransactionException;
use Foodsharing\Modules\Store\StoreTransactions;
use Foodsharing\Modules\Store\TeamStatus as TeamMembershipStatus;
use Foodsharing\Permissions\ProfilePermissions;
use Foodsharing\Permissions\StorePermissions;
use Foodsharing\RestApi\Models\Store\CreateStoreModel;
use Foodsharing\RestApi\Models\Store\MinimalStoreModel;
use Foodsharing\Utility\Requirement as FsRequirement;
use Foodsharing\Utility\TimeHelper;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OAOld;
use OpenApi\Attributes as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'stores')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
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
        private readonly ProfilePermissions $profilePermissions,
        private readonly RateLimiterFactory $locationChangeLimiterFactory,
        private readonly Mem $mem,
    ) {
    }

    #[OA\Get(summary: 'Get general store metadata')]
    #[Route('stores/meta-data', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new Model(type: CommonStoreMetadata::class))]
    #[OA\Response(response: Response::HTTP_NOT_MODIFIED, description: 'User has the current version')]
    public function getCommonStoreMetadata(#[MapQueryParameter] ?int $version, #[MapQueryParameter] ?bool $hasChains): Response
    {
        $this->assertLoggedIn();

        $userVersion = $version ?? 0;
        $hasChains ??= false;
        $currentVersion = (int)$this->mem->get(StoreTransactions::STORE_METADATA_VERSION_KEY);
        $shouldHaveChains = $this->storePermissions->mayListStores();
        if ($currentVersion === $userVersion && ($hasChains || !$shouldHaveChains)) {
            return $this->handleView($this->view(null, Response::HTTP_NOT_MODIFIED));
        }

        $metadata = $this->storeTransactions->getCommonStoreMetadataFromCache(!$shouldHaveChains, $currentVersion);

        return $this->respondOK($metadata);
    }

    #[OA\Get(summary: 'Get the stores where a user is member of')]
    #[Route('users/{userId}/stores', requirements: ['userId' => FsRequirement::USER_ID], methods: ['GET'])]
    public function getStoresOfUser(string $userId, #[MapQueryParameter] ?StoreListFormat $format, #[MapQueryParameter] ?bool $excludeInactive): Response
    {
        $this->assertLoggedIn();
        $userId = $this->resolveUserId($userId);
        $format ??= StoreListFormat::FOR_MEMBER;
        $excludeInactive ??= false;

        if ($format === StoreListFormat::LOCATION && $excludeInactive) {
            throw new BadRequestHttpException('cannot filter for inactive stores when format is location');
        }
        if (!$this->profilePermissions->maySeeStores($userId)) {
            throw new AccessDeniedHttpException('No permission see store list');
        }

        $stores = match ($format) {
            StoreListFormat::FOR_MEMBER => $this->storeTransactions->listStoresForUserAsMember($userId, $excludeInactive),
            StoreListFormat::LOCATION => $this->storeTransactions->listOverviewInformationsOfStoresFromUser($userId),
        };

        return $this->respondOK($stores);
    }

    #[OA\Get(summary: 'Get the stores of a region')]
    #[Route('regions/{regionId}/stores', requirements: ['regionId' => Requirement::POSITIVE_INT], methods: ['GET'])]
    public function getStoresOfRegion(int $regionId): Response
    {
        $this->assertLoggedIn();

        if (!$this->storePermissions->mayListStores()) {
            throw new AccessDeniedHttpException('No permission see store list');
        }

        /*
         * TODO: This deactivates store lists for Europe and countries because it needs to much memory on the server.
         * Can be removed when there is pagination.
         * Just adding pagination is not the solution though, since the frontend uses the full list for frontend filtering.
         * I wasted quite a bit of time until I realized that...
         * See also RegionPermissions:maySeeRegionMembers for the same problem with region members.
         */
        if (in_array($regionId, [RegionIDs::EUROPE, RegionIDs::GERMANY, RegionIDs::AUSTRIA, RegionIDs::SWITZERLAND])) {
            throw new AccessDeniedHttpException();
        }

        $stores = $this->storeTransactions->listOverviewInformationsOfStoresInRegion($regionId);

        return $this->respondOK($stores);
    }

    #[OA\Get(summary: 'Get detailed information about a store')]
    #[Route('/stores/{storeId}/details', methods: ['GET'], requirements: ['storeId' => Requirement::POSITIVE_INT])]
    public function getStoreInformation(int $storeId): Response
    {
        $this->assertLoggedIn();

        try {
            $mayAccess = $this->storePermissions->mayAccessStore($storeId);
        } catch (\Throwable) {
            throw new NotFoundHttpException('Store not found');
        }
        if (!$mayAccess) {
            throw new AccessDeniedHttpException('No permission see this stores information');
        }

        $maySeeSensitiveDetails = $this->storePermissions->mayEditStore($storeId);

        try {
            $result = $this->storeTransactions->getStore($storeId, $maySeeSensitiveDetails);
        } catch (DatabaseNoValueFoundException) {
            throw new NotFoundHttpException('Store not found.');
        }

        return $this->respondOK($result);
    }

    #[OA\Get(summary: 'Get the members of a store team')]
    #[Route('/stores/{storeId}/members', methods: ['GET'], requirements: ['storeId' => Requirement::POSITIVE_INT])]
    public function getStoreMembers(int $storeId): Response
    {
        $this->assertLoggedIn();

        if (!$this->storeGateway->storeExists($storeId)) {
            throw new NotFoundHttpException('Store not found.');
        }
        if (!$this->storePermissions->mayAccessStore($storeId)) {
            throw new AccessDeniedHttpException('Not allowed to see store members.');
        }

        // controls sensitive details (e.g., phone numbers) even for members
        $maySeeDetails = $this->storePermissions->maySeePhoneNumbers($storeId);
        $maySeeDistance = $this->storePermissions->maySeeMemberDistance($storeId);
        $members = $this->storeTransactions->getStoreTeamMembers($storeId, $maySeeDetails, $maySeeDistance);

        return $this->respondOK($members);
    }

    #[OA\Get(summary: 'Get the permissions of the logged in user for a store')]
    #[Route('/stores/{storeId}/permissions', methods: ['GET'], requirements: ['storeId' => Requirement::POSITIVE_INT])]
    public function getStorePermissions(int $storeId): Response
    {
        $this->assertLoggedIn();
        if (!$this->storeGateway->storeExists($storeId)) {
            throw new NotFoundHttpException('Store not found.');
        }
        if (!$this->storePermissions->mayAccessStore($storeId)) {
            throw new AccessDeniedHttpException('Not allowed to see store permissions.');
        }

        $permissions = $this->storeTransactions->getStorePermissions($storeId);

        return $this->respondOK($permissions);
    }

    // REFACTORED ENDPOINTS UNTIL HERE. ALL METHODS BELOW STILL NEED TO BE REFACTORED AFTER !4808

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
     * @OAOld\RequestBody(@Model(type=CreateStoreModel::class))
     * @OAOld\Response(response=Response::HTTP_CREATED,
     *    description="Created the new store and informed region members provides",
     *  @Model(type=MinimalStoreModel::class)
     * )
     * @OAOld\Response(response=Response::HTTP_BAD_REQUEST, description="Invalid body data")
     * @OAOld\Response(response=Response::HTTP_FORBIDDEN, description="No permission to create a store")
     * @throws StoreTransactionException
     */
    #[OA\Post(summary: 'Create a new store')]
    #[Route('regions/{regionId}/stores', methods: ['POST'], requirements: ['regionId' => Requirement::POSITIVE_INT])]
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
     * @OAOld\RequestBody(@Model(type=PatchStore::class))
     * @OAOld\Response(response=Response::HTTP_BAD_REQUEST, description="Invalid request data")
     * @OAOld\Response(response=Response::HTTP_FORBIDDEN, description="No permission to update store")
     * @OAOld\Response(response=Response::HTTP_NOT_FOUND, description="Store not found")
     * @OAOld\Response(response=Response::HTTP_OK, description="Empty response on success")
     */
    #[OA\Patch(summary: 'Edit store details')]
    #[Route('stores/{storeId}/details', methods: ['PATCH'], requirements: ['storeId' => Requirement::POSITIVE_INT])]
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

    /**
     * Request to join a store team.
     *
     * @OAOld\Parameter(name="storeId", in="path", @OAOld\Schema(type="integer"), description="for which store to apply")
     * @OAOld\RequestBody(@Model(type=StoreApplicationMessage::class))
     * @OAOld\Response(response="200", description="Success")
     * @OAOld\Response(response="403", description="Insufficient permissions to be member of a store team")
     * @OAOld\Response(response="404", description="Store does not exist")
     * @OAOld\Response(response="422", description="Already applied or already member of this store team")
     */
    #[OA\Post(summary: 'Request to join a store team')]
    #[Route('stores/{storeId}/requests', methods: ['POST'], requirements: ['storeId' => Requirement::POSITIVE_INT])]
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
     * @OAOld\Response(response="200", description="Success")
     * @OAOld\Response(response="403", description="Insufficient permissions")
     * @OAOld\Response(response="404", description="Store does not exist")
     */
    #[OA\Get(summary: 'Get the requests to a store team')]
    #[Route('stores/{storeId}/requests', methods: ['GET'], requirements: ['storeId' => Requirement::POSITIVE_INT])]
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
     * @OAOld\Parameter(name="storeId", in="path", @OAOld\Schema(type="integer"), description="for which store to accept a request")
     * @OAOld\Parameter(name="userId", in="path", @OAOld\Schema(type="integer"), description="who should be accepted")
     * @OAOld\Response(response="200", description="Success")
     * @OAOld\Response(response="403", description="Insufficient permissions to accept requests")
     * @OAOld\Response(response="404", description="Store or request does not exist")
     */
    #[OA\Patch(summary: 'Accept a request to join a store team')]
    #[Route('stores/{storeId}/requests/{userId}', methods: ['PATCH'], requirements: [
        'storeId' => Requirement::POSITIVE_INT, 'userId' => Requirement::POSITIVE_INT
    ])]
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
     * @OAOld\Parameter(name="storeId", in="path", @OAOld\Schema(type="integer"), description="for which store to remove a request")
     * @OAOld\Parameter(name="userId", in="path", @OAOld\Schema(type="integer"), description="whose request should be removed")
     * @OAOld\Response(response="200", description="Success")
     * @OAOld\Response(response="403", description="Insufficient permissions to remove the request")
     * @OAOld\Response(response="404", description="Store or request does not exist")
     */
    #[Rest\RequestParam(name: 'message', nullable: true)]
    #[OA\Delete(summary: 'Decline a request to join a store team')]
    #[Route('stores/{storeId}/requests/{userId}', methods: ['DELETE'], requirements: [
        'storeId' => Requirement::POSITIVE_INT, 'userId' => Requirement::POSITIVE_INT
    ])]
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

    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted for this store')]
    #[OA\Get(summary: 'Get the invitations to a store team')]
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
     * @OAOld\Parameter(name="storeId", in="path", @OAOld\Schema(type="integer"), description="which store to manage")
     * @OAOld\Parameter(name="userId", in="path", @OAOld\Schema(type="integer"), description="which user to add to the store team")
     * @OAOld\Response(response="200", description="Success")
     * @OAOld\Response(response="403", description="Insufficient permissions to manage this store team")
     * @OAOld\Response(response="404", description="Store does not exist")
     * @OAOld\Response(response="422", description="User is already, or cannot be, part of this store team")
     */
    #[OA\Post(summary: 'Invite a user to a store team')]
    #[Route('stores/{storeId}/invitations/{userId}', methods: ['POST'], requirements: [
        'storeId' => Requirement::POSITIVE_INT, 'userId' => Requirement::POSITIVE_INT
    ])]
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

    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted for this store')]
    #[OA\Delete(summary: 'Withdraw an invitation to a store team')]
    #[Route('stores/{storeId}/invitations/{userId}', methods: ['DELETE'], requirements: [
        'storeId' => Requirement::POSITIVE_INT, 'userId' => Requirement::POSITIVE_INT,
    ])]
    public function withdrawStoreTeamInvitation(int $storeId, int $userId): Response
    {
        $this->handleEditTeamExceptions($storeId);
        $this->storeTransactions->withdrawStoreTeamInvitation($storeId, $userId);

        return $this->respondOK();
    }

    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not invited to this store')]
    #[OA\Patch(summary: 'Accept an invitation to a store team')]
    #[Route('stores/{storeId}/invitations', methods: ['PATCH'], requirements: ['storeId' => Requirement::POSITIVE_INT])]
    public function acceptStoreTeamInvitation(int $storeId): Response
    {
        $this->assertLoggedIn();
        if ($this->storeGateway->getUserTeamStatus($this->session->id(), $storeId) !== TeamMembershipStatus::Invited) {
            throw new AccessDeniedHttpException('You are not invited to this store team.');
        }
        $this->storeTransactions->acceptStoreTeamInvitation($storeId, $this->session->id());

        return $this->respondOK();
    }

    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not invited to this store')]
    #[OA\Get(summary: 'Decline an invitation to a store team')]
    #[Route('stores/{storeId}/invitations', methods: ['DELETE'], requirements: ['storeId' => Requirement::POSITIVE_INT])]
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
     * @OAOld\Parameter(name="storeId", in="path", @OAOld\Schema(type="integer"), description="which store to manage")
     * @OAOld\Parameter(name="userId", in="path", @OAOld\Schema(type="integer"), description="which user to remove from the store team")
     * @OAOld\Response(response="200", description="Success")
     * @OAOld\Response(response="403", description="Insufficient permissions to manage this store team (if user is not yourself)")
     * @OAOld\Response(response="404", description="Store does not exists or user is not a member of it")
     * @OAOld\Response(response="422", description="User cannot currently leave this team")
     */
    #[Rest\RequestParam(name: 'message', nullable: true)]
    #[OA\Delete(summary: 'Remove a user from a store team')]
    #[Route('stores/{storeId}/members/{userId}', methods: ['DELETE'], requirements: [
        'storeId' => Requirement::POSITIVE_INT, 'userId' => Requirement::POSITIVE_INT
    ])]
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
     * @OAOld\Parameter(name="storeId", in="path", @OAOld\Schema(type="integer"), description="which store to manage")
     * @OAOld\Parameter(name="userId", in="path", @OAOld\Schema(type="integer"), description="which user to add as manager")
     * @OAOld\Response(response="200", description="Success")
     * @OAOld\Response(response="403", description="Insufficient permissions to manage this store team")
     * @OAOld\Response(response="404", description="Store does not exist")
     * @OAOld\Response(response="422", description="User cannot become manager of this store")
     */
    #[OA\Post(summary: 'Make a user a store manager')]
    #[Route('stores/{storeId}/managers/{userId}', methods: ['POST'], requirements: [
        'storeId' => Requirement::POSITIVE_INT, 'userId' => Requirement::POSITIVE_INT
    ])]
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
     * @OAOld\Parameter(name="storeId", in="path", @OAOld\Schema(type="integer"), description="which store to manage")
     * @OAOld\Parameter(name="userId", in="path", @OAOld\Schema(type="integer"), description="which user to remove as manager")
     * @OAOld\Response(response="200", description="Success")
     * @OAOld\Response(response="403", description="Insufficient permissions to manage this store team")
     * @OAOld\Response(response="404", description="Store does not exists or user is not a member of it")
     * @OAOld\Response(response="422", description="User cannot lose responsibility for this store")
     */
    #[Rest\RequestParam(name: 'message', nullable: true)]
    #[OA\Delete(summary: 'Demote a user from store manager to regular store team member')]
    #[Route('stores/{storeId}/managers/{userId}', methods: ['DELETE'], requirements: [
        'storeId' => Requirement::POSITIVE_INT, 'userId' => Requirement::POSITIVE_INT
    ])]
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
     * @OAOld\Parameter(name="storeId", in="path", @OAOld\Schema(type="integer"), description="team of which store to manage")
     * @OAOld\Parameter(name="userId", in="path", @OAOld\Schema(type="integer"), description="who should be moved to the standby team")
     * @OAOld\Response(response="200", description="Success")
     * @OAOld\Response(response="403", description="Insufficient permissions to manage this store team")
     * @OAOld\Response(response="404", description="User is not a member of this store")
     */
    #[Rest\RequestParam(name: 'message', nullable: true)]
    #[OA\Patch(summary: 'Move a store team member to the standby team')]
    #[Route('stores/{storeId}/members/{userId}/standby', methods: ['PATCH'], requirements: [
        'storeId' => Requirement::POSITIVE_INT, 'userId' => Requirement::POSITIVE_INT
    ])]
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
     * @OAOld\Parameter(name="storeId", in="path", @OAOld\Schema(type="integer"), description="team of which store to manage")
     * @OAOld\Parameter(name="userId", in="path", @OAOld\Schema(type="integer"), description="who should be moved to the regular store team")
     * @OAOld\Response(response="200", description="Success")
     * @OAOld\Response(response="403", description="Insufficient permissions to manage this store team")
     * @OAOld\Response(response="404", description="User is not a member of this store")
     */
    #[OA\Delete(summary: 'Move a store team member to the regular team')]
    #[Route('stores/{storeId}/members/{userId}/standby', methods: ['DELETE'], requirements: [
        'storeId' => Requirement::POSITIVE_INT, 'userId' => Requirement::POSITIVE_INT
    ])]
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
     * @OAOld\Parameter(name="storeId", in="path", @OAOld\Schema(type="integer"))
     * @OAOld\Parameter(name="fromDate", in="path", @OAOld\Schema(type="string"), description="The fist date from which to include actions")
     * @OAOld\Parameter(name="toDate", in="path", @OAOld\Schema(type="string"), description="The last date from which to include actions")
     * @OAOld\Parameter(name="storeLogActionIds", in="path", @OAOld\Schema(type="string"), description="The ids of the actions, seperated by commas like: 1,2,3")
     */
    #[OA\Get(summary: 'Get store log entries for the given time range and action types')]
    #[Route('stores/{storeId}/log/{fromDate}/{toDate}/{storeLogActionIds}', methods: ['GET'], requirements: [
        'storeId' => Requirement::POSITIVE_INT,
        'fromDate' => FsRequirement::ISO_DATE_TIME,
        'toDate' => FsRequirement::ISO_DATE_TIME,
        'storeLogActionIds' => FsRequirement::ID_LIST,
    ])]
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
     * @OAOld\Parameter(name="storeId", in="path", @OAOld\Schema(type="integer"), description="the store to delete")
     * @OAOld\Response(response="200", description="Success")
     * @OAOld\Response(response="403", description="Insufficient permissions to delete this store team")
     * @OAOld\Response(response="404", description="User is not a member of this store")
     */
    #[OA\Delete(summary: 'Delete a store')]
    #[Route('stores/{storeId}', methods: ['DELETE'], requirements: ['storeId' => Requirement::POSITIVE_INT])]
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
