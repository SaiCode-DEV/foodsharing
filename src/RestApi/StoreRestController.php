<?php

namespace Foodsharing\RestApi;

use Carbon\Carbon;
use DateTime;
use Exception;
use Foodsharing\Lib\Db\Mem;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Store\StoreLogAction;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Core\Pagination;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Store\DTO\CommonStoreMetadata;
use Foodsharing\Modules\Store\DTO\PatchStore;
use Foodsharing\Modules\Store\DTO\Store;
use Foodsharing\Modules\Store\DTO\StoreApplication;
use Foodsharing\Modules\Store\DTO\StoreApplicationMessage;
use Foodsharing\Modules\Store\DTO\StoreInvitation;
use Foodsharing\Modules\Store\DTO\StoreListInformation;
use Foodsharing\Modules\Store\DTO\StoreLogEntry;
use Foodsharing\Modules\Store\DTO\StorePermissions as DTOStorePermissions;
use Foodsharing\Modules\Store\DTO\StoreStandbyTeamMember;
use Foodsharing\Modules\Store\DTO\StoreTeamMembershipWithPickupStatus;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Modules\Store\StoreListFormat;
use Foodsharing\Modules\Store\StoreTransactionException;
use Foodsharing\Modules\Store\StoreTransactions;
use Foodsharing\Modules\Store\TeamStatus as TeamMembershipStatus;
use Foodsharing\Permissions\ProfilePermissions;
use Foodsharing\Permissions\StorePermissions;
use Foodsharing\RestApi\DTO\OptionalMessage;
use Foodsharing\RestApi\Models\Store\CreateStoreModel;
use Foodsharing\Utility\Requirement as FsRequirement;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'stores')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
class StoreRestController extends AbstractFoodsharingRestController
{
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
        parent::__construct($session);
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
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new OA\JsonContent(oneOf: [
        new OA\Schema(type: 'array', items: new OA\Items(ref: new Model(type: StoreTeamMembershipWithPickupStatus::class))),
        new OA\Schema(type: 'array', items: new OA\Items(ref: new Model(type: StoreListInformation::class))),
    ]))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'No permission to see store list')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'User not found')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Cannot filter for inactive stores when format is location')]
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
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(ref: new Model(type: StoreListInformation::class))
    ))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'No permission to see store list')]
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
            throw new AccessDeniedHttpException('Currently not permitted to see the store list in this region.');
        }

        $stores = $this->storeTransactions->listOverviewInformationsOfStoresInRegion($regionId);

        return $this->respondOK($stores);
    }

    #[OA\Get(summary: 'Get detailed information about a store')]
    #[Route('/stores/{storeId}/details', methods: ['GET'], requirements: ['storeId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new Model(type: Store::class))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'No permission to access this store')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Store not found')]
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
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(ref: new Model(type: StoreStandbyTeamMember::class))
    ))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'No permission to access this store')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Store not found')]
    public function getStoreMembers(int $storeId): Response
    {
        $this->assertLoggedIn();
        $this->assertStoreExists($storeId);
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
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new Model(type: DTOStorePermissions::class))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'No permission to access this store')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Store not found')]
    public function getStorePermissions(int $storeId): Response
    {
        $this->assertLoggedIn();
        $this->assertStoreExists($storeId);
        if (!$this->storePermissions->mayAccessStore($storeId)) {
            throw new AccessDeniedHttpException('Not allowed to see store permissions.');
        }

        $permissions = $this->storeTransactions->getStorePermissions($storeId);

        return $this->respondOK($permissions);
    }

    #[OA\Post(summary: 'Create a new store', description: 'Chat channels for the team and standby team members will
        be created alongside the store. The calling user will be added as the only member and be made store manager.
        All members of the region are notified via bell about the new store.')]
    #[Route('regions/{regionId}/stores', methods: ['POST'], requirements: ['regionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'id', type: 'integer', description: 'Id of the newly created store')
    ]))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'No permission to create store for this region')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Region not found')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid region type for stores')]
    public function addStore(int $regionId, #[MapRequestPayload] CreateStoreModel $storeCreateInformation): Response
    {
        $this->assertLoggedIn();

        if (!$this->storePermissions->mayCreateStore($regionId)) {
            throw new AccessDeniedHttpException('No permission to create store for this region');
        }
        try {
            $regionType = $this->regionGateway->getType($regionId);
        } catch (Exception) {
            throw new NotFoundHttpException('Region not found');
        }
        if (!UnitType::isAccessibleRegion($regionType)) {
            throw new BadRequestHttpException('Stores are not allowed in this type of region.');
        }

        $store = $storeCreateInformation->store->toCreateStore();
        $store->regionId = $regionId;
        $storeId = $this->storeTransactions->createStore($store, $this->session->id(), $storeCreateInformation->firstPost);

        return $this->respondOK(['id' => $storeId]);
    }

    #[OA\Patch(summary: 'Edit store details')]
    #[Route('stores/{storeId}/details', methods: ['PATCH'], requirements: ['storeId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'No permission to edit this store')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Store not found')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid input or no settings to change')]
    public function editStore(int $storeId, #[MapRequestPayload] PatchStore $storeModel, Request $request)
    {
        $this->assertLoggedIn();
        $this->assertStoreExists($storeId);
        if (!$this->storePermissions->mayEditStore($storeId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $oldStore = $this->storeGateway->getStore($storeId);
        if (!is_null($storeModel->location) && ($oldStore->location->lat !== $storeModel->location->lat || $oldStore->location->lon !== $storeModel->location->lon)) {
            $this->checkRateLimit($request, $this->locationChangeLimiterFactory, $storeId);
        }

        if (!$this->session->mayRole(Role::ORGA)
            && !empty($storeModel->regionId)
            && !$this->regionGateway->hasMember($this->session->id(), $storeModel->regionId)
        ) {
            throw new AccessDeniedHttpException('Not permitted to move the store to a region you are not a member of.');
        }

        try {
            $hasChanged = $this->storeTransactions->updateStore($storeId, $storeModel);
        } catch (StoreTransactionException $ex) {
            throw new BadRequestHttpException($ex->getMessage());
        }
        if (!$hasChanged) {
            throw new BadRequestHttpException('No settings to change');
        }

        return $this->respondOK();
    }

    #[OA\Post(summary: 'Request to join a store team')]
    #[Route('stores/{storeId}/requests', methods: ['POST'], requirements: ['storeId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'No permission to join this store')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Store not found')]
    #[OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'User has already applied or is already a member')]
    public function requestStoreTeamMembership(int $storeId, #[MapRequestPayload] StoreApplicationMessage $message): Response
    {
        $this->assertLoggedIn();
        $this->assertStoreExists($storeId);
        if (!$this->storePermissions->mayJoinStore($storeId, false)) {
            throw new AccessDeniedHttpException('Not permitted');
        }
        if ($this->storeGateway->getUserTeamStatus($this->session->id(), $storeId) !== TeamMembershipStatus::NoMember) {
            throw new UnprocessableEntityHttpException('User has already applied or is already member of this store.');
        }

        $this->storeTransactions->requestStoreTeamMembership($storeId, $this->session->id(), $message->message);

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Get the requests to a store team')]
    #[Route('stores/{storeId}/requests', methods: ['GET'], requirements: ['storeId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(ref: new Model(type: StoreApplication::class))
    ))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Store not found')]
    public function listStoreTeamMembershipRequests(int $storeId): Response
    {
        $this->assertLoggedIn();
        $this->assertStoreExists($storeId);
        if (!$this->storePermissions->mayEditStore($storeId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $membershipRequests = $this->storeTransactions->getStoreApplications($storeId);

        return $this->respondOK($membershipRequests);
    }

    #[OA\Patch(summary: 'Accept a request to join a store team')]
    #[Route('stores/{storeId}/requests/{userId}', methods: ['PATCH'], requirements: [
        'storeId' => Requirement::POSITIVE_INT, 'userId' => Requirement::POSITIVE_INT
    ])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted for this store')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Store not found or request does not exist')]
    public function acceptStoreRequest(int $storeId, int $userId, #[MapQueryParameter] ?bool $moveToStandby = null): Response
    {
        $this->handleEditTeamExceptions($storeId, $userId, true);
        if ($this->storeGateway->getUserTeamStatus($userId, $storeId) !== TeamMembershipStatus::Applied) {
            throw new NotFoundHttpException('Request does not exist.');
        }
        $moveToStandby ??= false;

        $this->storeTransactions->acceptStoreRequest($storeId, $userId, $moveToStandby);

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Decline a request to join a store team')]
    #[Route('stores/{storeId}/requests/{userId}', methods: ['DELETE'], requirements: [
        'storeId' => Requirement::POSITIVE_INT, 'userId' => Requirement::POSITIVE_INT
    ])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted for this store')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Store not found or request does not exist')]
    public function declineStoreRequest(int $storeId, int $userId, #[MapRequestPayload] OptionalMessage $message): Response
    {
        $this->handleEditTeamExceptions($storeId, $userId, false, true);
        if ($this->storeGateway->getUserTeamStatus($userId, $storeId) !== TeamMembershipStatus::Applied) {
            throw new NotFoundHttpException('Request does not exist.');
        }

        $this->storeTransactions->declineStoreRequest($storeId, $userId, $message->message);

        $isActorTarget = $this->session->id() === $userId;
        $logAction = $isActorTarget ? StoreLogAction::REQUEST_CANCELLED : StoreLogAction::REQUEST_DECLINED;

        $this->storeGateway->addStoreLog(
            $storeId,
            $this->session->id(),
            $userId,
            null,
            $logAction,
            reason: $message->message
        );

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Get the invitations to a store team')]
    #[Route('stores/{storeId}/invitations', requirements: ['storeId' => Requirement::POSITIVE_INT], methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(ref: new Model(type: StoreInvitation::class))
    ))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Store not found')]
    public function listStoreTeamInvitations(int $storeId): Response
    {
        $this->handleEditTeamExceptions($storeId);
        $invitations = $this->storeGateway->getInvitations($storeId);

        return $this->respondOK($invitations);
    }

    #[OA\Post(summary: 'Invite a user to a store team')]
    #[Route('stores/{storeId}/invitations/{userId}', methods: ['POST'], requirements: [
        'storeId' => Requirement::POSITIVE_INT, 'userId' => Requirement::POSITIVE_INT
    ])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new Model(type: StoreInvitation::class))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted for this store')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Store or user not found')]
    #[OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'User cannot be invited to this store')]
    public function inviteStoreMember(int $storeId, int $userId): Response
    {
        $this->handleEditTeamExceptions($storeId, $userId, true);
        $userRole = $this->foodsaverGateway->getRole($userId);
        if (is_null($userRole)) {
            throw new NotFoundHttpException('User user not found');
        }
        if (!$this->storePermissions->mayInviteUserToStoreTeam($storeId, $userId, $userRole)) {
            throw new UnprocessableEntityHttpException('User cannot be invited to this store');
        }

        $invitation = $this->storeTransactions->inviteStoreMember($storeId, $userId);

        return $this->respondOK($invitation);
    }

    #[OA\Delete(summary: 'Withdraw an invitation to a store team')]
    #[Route('stores/{storeId}/invitations/{userId}', methods: ['DELETE'], requirements: [
        'storeId' => Requirement::POSITIVE_INT, 'userId' => Requirement::POSITIVE_INT,
    ])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Store not found')]
    public function withdrawStoreTeamInvitation(int $storeId, int $userId): Response
    {
        $this->handleEditTeamExceptions($storeId);
        $this->storeTransactions->withdrawStoreTeamInvitation($storeId, $userId);

        return $this->respondOK();
    }

    #[OA\Patch(summary: 'Accept an invitation to a store team')]
    #[Route('stores/{storeId}/invitations/current', methods: ['PATCH'], requirements: ['storeId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted to accept the invitation')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Not invited to this store')]
    public function acceptStoreTeamInvitation(int $storeId): Response
    {
        $this->assertLoggedIn();
        if ($this->storeGateway->getUserTeamStatus($this->session->id(), $storeId) !== TeamMembershipStatus::Invited) {
            throw new NotFoundHttpException('You are not invited to this store team.');
        }

        // Check if user is allowed to accept the invitation
        if (!$this->storePermissions->mayJoinStore($storeId, true)) {
            throw new AccessDeniedHttpException('You are not allowed to accept the invitation due to missing requirements.');
        }

        $this->storeTransactions->acceptStoreTeamInvitation($storeId, $this->session->id());

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Decline an invitation to a store team')]
    #[Route('stores/{storeId}/invitations/current', methods: ['DELETE'], requirements: ['storeId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Not invited to this store')]
    public function declineStoreTeamInvitation(int $storeId): Response
    {
        $this->assertLoggedIn();
        if ($this->storeGateway->getUserTeamStatus($this->session->id(), $storeId) !== TeamMembershipStatus::Invited) {
            throw new NotFoundHttpException('You are not invited to this store team.');
        }
        $this->storeTransactions->declineStoreTeamInvitation($storeId, $this->session->id());

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Remove a user from a store team')]
    #[Route('stores/{storeId}/members/{userId}', methods: ['DELETE'], requirements: [
        'storeId' => Requirement::POSITIVE_INT, 'userId' => Requirement::POSITIVE_INT
    ])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to manage this store team')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Store does not exist or user is not a member of it')]
    #[OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'User cannot currently leave this team')]
    public function removeStoreMember(int $storeId, int $userId, #[MapRequestPayload] OptionalMessage $message): Response
    {
        $this->handleEditTeamExceptions($storeId, $userId, false, true);
        if (!$this->storePermissions->mayLeaveStoreTeam($storeId, $userId)) {
            throw new UnprocessableEntityHttpException('You can\'t leave the store team while being store manager');
        }

        $this->storeTransactions->removeStoreMember($storeId, $userId, $message->message);

        return $this->respondOK();
    }

    #[OA\Post(summary: 'Make a user a store manager')]
    #[Route('stores/{storeId}/managers/{userId}', methods: ['POST'], requirements: [
        'storeId' => Requirement::POSITIVE_INT, 'userId' => Requirement::POSITIVE_INT
    ])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to manage this store team')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Store does not exist')]
    #[OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'User cannot become manager of this store')]
    public function addStoreManager(int $storeId, int $userId): Response
    {
        $this->handleEditTeamExceptions($storeId, $userId, true);
        $userRole = $this->foodsaverGateway->getRole($userId);
        if (!$userRole || !$this->storePermissions->mayBecomeStoreManager($storeId, $userId, $userRole)) {
            throw new UnprocessableEntityHttpException('User ' . $userId . 'is currently not permitted to be store manager.');
        }

        $this->storeTransactions->makeMemberResponsible($storeId, $userId);

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Demote a user from store manager to regular store team member')]
    #[Route('stores/{storeId}/managers/{userId}', methods: ['DELETE'], requirements: [
        'storeId' => Requirement::POSITIVE_INT, 'userId' => Requirement::POSITIVE_INT
    ])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to manage this store team')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Store does not exist or user is not a member of it')]
    #[OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'User cannot lose responsibility for this store')]
    public function removeStoreManager(int $storeId, int $userId, #[MapRequestPayload] OptionalMessage $message): Response
    {
        $this->handleEditTeamExceptions($storeId, $userId);
        $errorMessage = '';
        if (!$this->storePermissions->mayLoseStoreManagement($storeId, $userId, $errorMessage)) {
            throw new UnprocessableEntityHttpException($errorMessage);
        }

        $this->storeTransactions->downgradeResponsibleMember($storeId, $userId, $message->message);

        return $this->respondOK();
    }

    #[OA\Patch(summary: 'Move a store team member to the standby team')]
    #[Route('stores/{storeId}/members/{userId}/standby', methods: ['PATCH'], requirements: [
        'storeId' => Requirement::POSITIVE_INT, 'userId' => Requirement::POSITIVE_INT
    ])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to manage this store team')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Store not found or user is not a member of this store')]
    public function moveMemberToStandbyTeam(int $storeId, int $userId, #[MapRequestPayload] OptionalMessage $message): Response
    {
        $this->assertLoggedIn();
        $this->assertStoreExists($storeId);
        if (!$this->storePermissions->mayEditStoreTeam($storeId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }
        if ($this->storeGateway->getUserTeamStatus($userId, $storeId) === TeamMembershipStatus::NoMember) {
            throw new NotFoundHttpException('User is not a member of this store.');
        }

        $this->storeTransactions->moveMemberToStandbyTeam($storeId, $userId, $message->message);

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Move a store team member to the regular team')]
    #[Route('stores/{storeId}/members/{userId}/standby', methods: ['DELETE'], requirements: [
        'storeId' => Requirement::POSITIVE_INT, 'userId' => Requirement::POSITIVE_INT
    ])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to manage this store team')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Store not found or user is not a member of this store')]
    public function moveUserToRegularTeam(int $storeId, int $userId): Response
    {
        $this->assertLoggedIn();
        $this->assertStoreExists($storeId);
        if (!$this->storePermissions->mayEditStoreTeam($storeId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        if ($this->storeGateway->getUserTeamStatus($userId, $storeId) === TeamMembershipStatus::NoMember) {
            throw new NotFoundHttpException('User is not a member of this store.');
        }

        $this->storeTransactions->moveMemberToRegularTeam($storeId, $userId);

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Get store log entries for the given time range and action types')]
    #[Route('stores/{storeId}/log/{fromDate}/{toDate}/actions/{storeLogActionIds}', methods: ['GET'], requirements: [
        'storeId' => Requirement::POSITIVE_INT,
        'fromDate' => FsRequirement::ISO_DATE_TIME,
        'toDate' => FsRequirement::ISO_DATE_TIME,
        'storeLogActionIds' => FsRequirement::ID_LIST,
    ])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(ref: new Model(type: StoreLogEntry::class))
    ))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to access store log')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Cannot access store log more than 6 months back')]
    public function showStoreLogHistory(
        int $storeId, DateTime $fromDate, DateTime $toDate, string $storeLogActionIds,
        #[MapQueryParameter] ?int $limit = null, #[MapQueryParameter] ?int $offset = null
    ): Response {
        $this->assertLoggedIn();
        $fromDate = $this->normalizeDateToServerTimezone($fromDate);
        $toDate = $this->normalizeDateToServerTimezone($toDate);

        $storeLogActions = explode(',', $storeLogActionIds);
        if (!$this->storePermissions->maySeeStoreLog($storeId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        if (Carbon::now()->subMonths(6)->subDay() > $fromDate) { // 6 months + 1 day for rounding
            throw new BadRequestHttpException('Cannot access store log more than 6 months back.');
        }

        $pagination = Pagination::create($limit, $offset, 100);

        $storeLogEntries = $this->storeGateway->getStoreLogsByActionType($storeId, $storeLogActions, $fromDate, $toDate, $pagination);

        return $this->respondOK($storeLogEntries);
    }

    #[OA\Delete(summary: 'Delete a store', description: 'Only allowed for stores that never had any pickup.')]
    #[Route('stores/{storeId}', methods: ['DELETE'], requirements: ['storeId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to delete this store')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Store not found')]
    public function deleteStore(int $storeId): Response
    {
        $this->assertLoggedIn();
        if (!$this->storePermissions->mayDeleteStore($storeId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $this->storeTransactions->deleteStore($storeId);

        return $this->respondOK();
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
     * @throws UnauthorizedHttpException if the user is not logged in
     * @throws NotFoundHttpException if the store doesn't exist
     * @throws NotFoundHttpException if the targeted should be but isn't a  team member
     * @throws AccessDeniedHttpException if the user is not permitted to make the requested team change
     */
    private function handleEditTeamExceptions(int $storeId, ?int $targetId = null, bool $allowExternals = false, bool $mayEditOneself = false)
    {
        $this->assertLoggedIn();
        $this->assertStoreExists($storeId);

        // Session may edit target user (mayEditStoreTeam OR (session is targetUser AND 'mayEditOneself' flag is set))
        if (!($mayEditOneself && $this->session->id() === $targetId) && !$this->storePermissions->mayEditStoreTeam($storeId)) {
            throw new AccessDeniedHttpException('Not permitted to edit the team of the store.');
        }

        // Target user is in Team (or externals are allowed)
        if (isset($targetId) && !$allowExternals && $this->storeGateway->getUserTeamStatus($targetId, $storeId) === TeamMembershipStatus::NoMember) {
            throw new NotFoundHttpException('User is not a member of this store.');
        }
    }

    /** @throws NotFoundHttpException if the store doesn't exist */
    private function assertStoreExists(int $storeId): void
    {
        if (!$this->storeGateway->storeExists($storeId)) {
            throw new NotFoundHttpException('Store not found.');
        }
    }
}
