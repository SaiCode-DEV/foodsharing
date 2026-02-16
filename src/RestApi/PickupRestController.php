<?php

namespace Foodsharing\RestApi;

use Carbon\Carbon;
use DateTime;
use Exception;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Store\StoreLogAction;
use Foodsharing\Modules\Core\Pagination;
use Foodsharing\Modules\Store\DTO\EditPickupData;
use Foodsharing\Modules\Store\DTO\OneTimePickup;
use Foodsharing\Modules\Store\DTO\PickupOption;
use Foodsharing\Modules\Store\DTO\RegularPickup;
use Foodsharing\Modules\Store\DTO\RegularPickups;
use Foodsharing\Modules\Store\PickupGateway;
use Foodsharing\Modules\Store\PickupTransactions;
use Foodsharing\Modules\Store\PickupValidationException;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Modules\Store\StoreTransactionException;
use Foodsharing\Modules\Store\StoreTransactions;
use Foodsharing\Permissions\ProfilePermissions;
use Foodsharing\Permissions\StorePermissions;
use Foodsharing\RestApi\Models\Store\PickupLeaveMessageOptions;
use Foodsharing\Utility\Requirement as FSRequirement;
use Foodsharing\Utility\TimeHelper;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'pickup')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
final class PickupRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        protected Session $session,
        private readonly PickupGateway $pickupGateway,
        private readonly StoreGateway $storeGateway,
        private readonly StorePermissions $storePermissions,
        private readonly ProfilePermissions $profilePermissions,
        private readonly StoreTransactions $storeTransactions,
        private readonly PickupTransactions $pickupTransactions
    ) {
    }

    #[OA\Post(summary: 'Join a pickup slot')]
    #[Route('stores/{storeId}/pickups/{pickupDate}/users/current', methods: ['POST'], requirements: ['storeId' => Requirement::POSITIVE_INT, 'pickupDate' => FSRequirement::ISO_DATE_TIME])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'isConfirmed', type: 'boolean'),
    ]))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted to join pickup')]
    public function joinPickup(int $storeId, DateTime $pickupDate): Response
    {
        $this->assertLoggedIn();

        $reason = '';
        if (!$this->storePermissions->mayDoPickup($storeId, $pickupDate, $reason)) {
            throw new AccessDeniedHttpException($reason);
        }

        try {
            $isConfirmed = $this->storeTransactions->joinPickup($storeId, Carbon::instance($pickupDate), $this->session->id());
        } catch (StoreTransactionException $ex) {
            throw new AccessDeniedHttpException($ex->getMessage(), $ex);
        }

        return $this->respondOk(['isConfirmed' => $isConfirmed]);
    }

    #[OA\Delete(summary: 'Remove a user from a pickup')]
    #[Route('stores/{storeId}/pickups/{pickupDate}/users/{userId}', methods: ['DELETE'], requirements: ['storeId' => Requirement::POSITIVE_INT, 'pickupDate' => FSRequirement::ISO_DATE_TIME, 'userId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function leavePickup(int $storeId, DateTime $pickupDate, int $userId, #[MapRequestPayload] PickupLeaveMessageOptions $leaveInformation): Response
    {
        $this->assertLoggedIn();
        if (!$this->storePermissions->mayRemovePickupUser($storeId, $userId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $sendKickMessage = $leaveInformation->sendKickMessage || !$this->profilePermissions->mayCancelSlotsFromProfile($userId);
        $this->pickupTransactions->doLeavePickup($storeId, $pickupDate, $userId, $leaveInformation->message, $sendKickMessage);

        return $this->respondOk();
    }

    #[OA\Delete(summary: 'Remove a user from all his pickups')]
    #[Route('users/{userId}/pickups', methods: ['DELETE'], requirements: ['userId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function leaveAllPickups(int $userId, #[MapRequestPayload] PickupLeaveMessageOptions $leaveInformation): Response
    {
        $this->assertLoggedIn();
        if (!$this->profilePermissions->mayCancelSlotsFromProfile($userId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $pickups = $this->pickupGateway->getNextPickups($userId);
        $sendKickMessage = $leaveInformation->sendKickMessage;

        foreach ($pickups as $pickup) {
            $this->pickupTransactions->doLeavePickup($pickup['store_id'], Carbon::createFromTimestamp($pickup['timestamp']), $userId, $leaveInformation->message, $sendKickMessage);
        }

        return $this->respondOK();
    }

    #[OA\Patch(summary: 'Confirm a pickup slot')]
    #[Route('stores/{storeId}/pickups/{pickupDate}/users/{userId}', methods: ['PATCH'], requirements: ['storeId' => Requirement::POSITIVE_INT, 'pickupDate' => FSRequirement::ISO_DATE_TIME, 'userId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid request')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function editPickupSlot(int $storeId, DateTime $pickupDate, int $userId): Response
    {
        $this->assertLoggedIn();

        if (!$this->storePermissions->mayConfirmPickup($storeId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }
        if (!$this->pickupGateway->confirmFetcher($userId, $storeId, $pickupDate)) {
            throw new BadRequestHttpException('Could not confirm pickup slot.');
        }
        $this->storeGateway->addStoreLog(
            $storeId,
            $this->session->id(),
            $userId,
            $pickupDate,
            StoreLogAction::SLOT_CONFIRMED
        );

        return $this->respondOk();
    }

    #[OA\Get(summary: 'Get the regular pickups for a store')]
    #[Route('stores/{storeId}/regular-pickups', methods: ['GET'], requirements: ['storeId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(ref: new Model(type: RegularPickup::class))
    ))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'No permission to access pickups')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Store not found')]
    public function getRegularPickup(int $storeId): Response
    {
        $this->assertLoggedIn();

        if (!$this->storePermissions->maySeePickups($storeId)) {
            throw new AccessDeniedHttpException("No permission to access storeid '$storeId'");
        }

        try {
            $regularPickups = $this->pickupTransactions->getRegularPickup($storeId);
        } catch (Exception $ex) {
            // catch invalid query
            throw new NotFoundHttpException('Store not found.', $ex);
        }

        return $this->respondOk($regularPickups);
    }

    #[OA\Put(summary: 'Set the regular pickups for a store')]
    #[Route('stores/{storeId}/regular-pickups', methods: ['PUT'], requirements: ['storeId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid request body')]
    public function editRegularPickup(int $storeId, #[MapRequestPayload] RegularPickups $regularPickups): Response
    {
        $this->assertLoggedIn();
        if (!$this->storePermissions->mayEditPickups($storeId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        try {
            $this->pickupTransactions->replaceRegularPickup($storeId, $regularPickups->regularPickups);
        } catch (PickupValidationException $ex) {
            throw new BadRequestHttpException($ex->getMessage(), $ex);
        }

        return $this->respondOk();
    }

    #[OA\Put(summary: 'Create or modify a manual pick up for a store')]
    #[Route('stores/{storeId}/pickups/{pickupDate}', methods: ['PUT'], requirements: ['storeId' => Requirement::POSITIVE_INT, 'pickupDate' => FSRequirement::ISO_DATE_TIME])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'isNewlyCreated', type: 'boolean', description: 'Indicates whether a new pickup was created (true) or an existing one was updated (false).')
    ]))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'No permission to change pickup')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Store not found')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid request body')]
    public function editPickup(int $storeId, DateTime $pickupDate, #[MapRequestPayload] EditPickupData $editPickupData): Response
    {
        $this->assertLoggedIn();

        if (!$this->storePermissions->mayEditPickups($storeId)) {
            $existingStore = $this->storeGateway->storeExists($storeId);
            if (!$existingStore) {
                throw new NotFoundHttpException("Store '$storeId' not found");
            } else {
                throw new AccessDeniedHttpException('Not permitted');
            }
        }

        $pickup = new OneTimePickup();
        $pickup->date = $pickupDate;
        $pickup->slots = $editPickupData->totalSlots;
        $pickup->description = $editPickupData->description;

        try {
            $isNewlyCreated = $this->storeTransactions->createOrUpdatePickup($storeId, $pickup);
        } catch (PickupValidationException $ex) {
            throw new BadRequestHttpException($ex->getMessage());
        }

        return $this->respondOk(['isNewlyCreated' => $isNewlyCreated]);
    }

    #[OA\Get(summary: 'List pickups for a store')]
    #[Route('stores/{storeId}/pickups', methods: ['GET'], requirements: ['storeId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(type: 'object', properties: [
            new OA\Property(property: 'date', type: 'string', format: 'date-time'),
            new OA\Property(property: 'totalSlots', type: 'integer', example: 5),
            new OA\Property(property: 'occupiedSlots', type: 'array', items: new OA\Items(type: 'object', properties: [
                new OA\Property(property: 'isConfirmed', type: 'boolean'),
                new OA\Property(property: 'profile', type: 'object', properties: [
                    new OA\Property(property: 'id', type: 'integer'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'avatar', type: 'string', nullable: true),
                    new OA\Property(property: 'isSleeping', type: 'boolean'),
                    new OA\Property(property: 'mobile', type: 'string', nullable: true),
                    new OA\Property(property: 'landline', type: 'string', nullable: true),
                    new OA\Property(property: 'isManager', type: 'boolean'),
                ]),
            ])),
            new OA\Property(property: 'isAvailable', type: 'boolean'),
            new OA\Property(property: 'description', type: 'string'),
        ])
    ))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'No permission to access pickups')]
    public function listPickups(int $storeId): Response
    {
        $this->assertLoggedIn();
        if (!$this->storePermissions->maySeePickups($storeId)) {
            throw new AccessDeniedHttpException('You are not allowed to see pickups in this store.');
        }
        if (Carbon::today()->diffInHours(Carbon::now(), true) >= 6) {
            $fromTime = Carbon::today();
        } else {
            $fromTime = Carbon::today()->subHours(6);
        }

        // TODO: refactor to use DTOs and clean up return data
        $pickups = $this->pickupGateway->getPickupSlots($storeId, $fromTime);
        $pickups = $this->pickupTransactions->enrichPickupSlots($pickups, $storeId);

        return $this->respondOk($pickups);
    }

    #[OA\Get(summary: 'List pickup history for a store')]
    #[Route('stores/{storeId}/pickups/history/{fromDate}/{toDate}', methods: ['GET'], requirements: ['storeId' => Requirement::POSITIVE_INT, 'fromDate' => FSRequirement::ISO_DATE_TIME, 'toDate' => FSRequirement::ISO_DATE_TIME])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(type: 'object', properties: [
            new OA\Property(property: 'confirmed', type: 'integer'),
            new OA\Property(property: 'date', type: 'string', format: 'date-time'),
            new OA\Property(property: 'date_ts', type: 'integer'),
            new OA\Property(property: 'description', type: 'string', nullable: true),
            new OA\Property(property: 'profile', type: 'object', properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'avatar', type: 'string', nullable: true),
                new OA\Property(property: 'isSleeping', type: 'boolean'),
                new OA\Property(property: 'mobile', type: 'string', nullable: true),
                new OA\Property(property: 'landline', type: 'string', nullable: true),
                new OA\Property(property: 'isManager', type: 'boolean'),
            ]),
        ])
    ))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'No permission to access pickup history')]
    public function listPickupHistory(int $storeId, Carbon $fromDate, Carbon $toDate): Response
    {
        $this->assertLoggedIn();
        if (!$this->storePermissions->maySeePickupHistory($storeId)) {
            throw new AccessDeniedHttpException('You are not allowed to see pickup history in this store.');
        }

        $fromDate = $fromDate->min(Carbon::now());
        $toDate = $toDate->min(Carbon::now());

        $pickups = $this->pickupGateway->getPickupHistory($storeId, $fromDate, $toDate);
        $pickups = $this->pickupTransactions->enrichPickupSlots([['occupiedSlots' => $pickups]], $storeId);
        $pickups = $pickups[0]['occupiedSlots'];

        return $this->respondOk($pickups);
    }

    #[OA\Get(summary: 'Get past pickups for a user', description: 'Can be restricted (to the last month or one entry at least) depending on the requesting users permissions.')]
    #[Route('users/{userId}/pickups/history', methods: ['GET'], requirements: ['userId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(type: 'object', ref: new Model(type: PickupOption::class))
    ))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function listPastPickups(int $userId, #[MapQueryParameter] ?int $limit, #[MapQueryParameter] ?int $offset): Response
    {
        $this->assertLoggedIn();
        if (!$this->profilePermissions->maySeePickups($userId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $pagination = Pagination::create($limit, $offset, 50);
        $maySeeFullHistory = $this->profilePermissions->maySeeAllPickups($userId);

        $pickups = $this->pickupGateway->getPastPickups($userId, $pagination, $maySeeFullHistory);
        if (!$maySeeFullHistory && empty($pickups) && $pagination->offset === 0) {
            $pickups = $this->pickupGateway->getPastPickups($userId, Pagination::create(1, 0), true);
        }

        $pickups = array_map(fn ($pickup) => $this->pickupTransactions->createPickupOption($pickup), $pickups);

        return $this->respondOk($pickups);
    }

    #[OA\Get(summary: 'Get future registered pickups for a user')]
    #[Route('users/{userId}/pickups/registered', methods: ['GET'], requirements: ['userId' => FSRequirement::USER_ID])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(type: 'object', ref: new Model(type: PickupOption::class))
    ))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function listRegisteredPickups(string $userId): Response
    {
        $this->assertLoggedIn();
        $userId = $this->resolveUserId($userId);

        if (!$this->profilePermissions->maySeePickups($userId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $pickups = $this->pickupGateway->getNextPickups($userId, null, 30);

        $pickups = array_map(fn ($pickup) => $this->pickupTransactions->createPickupOption($pickup), $pickups);

        return $this->respondOk($pickups);
    }

    #[OA\Get(summary: 'Get all pickup options a user has, including already registered slots')]
    #[Route('users/current/pickups/options', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(type: 'object', ref: new Model(type: PickupOption::class))
    ))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function listPickupOptions(): Response
    {
        $this->assertLoggedIn();
        if (!$this->storePermissions->maySeePickupOptions()) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $pickupOptions = $this->pickupTransactions->getPickupOptions($this->session->id());

        return $this->respondOk($pickupOptions);
    }

    #[OA\Get(summary: 'Check if a user may enter a specific pickup based on pickup rules')]
    #[Route('stores/{storeId}/pickups/{pickupDate}/eligibility', methods: ['GET'], requirements: ['storeId' => Requirement::POSITIVE_INT, 'pickupDate' => FSRequirement::ISO_DATE_TIME])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'isEligible', type: 'boolean'),
    ]))]
    public function passesPickupRule(int $storeId, string $pickupDate): Response
    {
        $this->assertLoggedIn();
        $date = TimeHelper::parsePickupDate($pickupDate);
        $isEligible = $this->storeTransactions->checkPickupRule($storeId, $date, $this->session->id());

        return $this->respondOk(['isEligible' => $isEligible]);
    }
}
