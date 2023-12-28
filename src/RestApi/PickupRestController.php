<?php

namespace Foodsharing\RestApi;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Store\StoreLogAction;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Message\MessageTransactions;
use Foodsharing\Modules\Store\DTO\OneTimePickup;
use Foodsharing\Modules\Store\DTO\RegularPickup;
use Foodsharing\Modules\Store\PickupGateway;
use Foodsharing\Modules\Store\PickupTransactions;
use Foodsharing\Modules\Store\PickupValidationException;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Modules\Store\StoreTransactionException;
use Foodsharing\Modules\Store\StoreTransactions;
use Foodsharing\Permissions\ProfilePermissions;
use Foodsharing\Permissions\StorePermissions;
use Foodsharing\RestApi\Models\Store\PickupLeaveMessageOptions;
use Foodsharing\Utility\TimeHelper;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\Annotations\RequestParam;
use FOS\RestBundle\Request\ParamFetcherInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class PickupRestController extends AbstractFOSRestController
{
    public function __construct(
        private FoodsaverGateway $foodsaverGateway,
        private Session $session,
        private PickupGateway $pickupGateway,
        private StoreGateway $storeGateway,
        private StorePermissions $storePermissions,
        private ProfilePermissions $profilePermissions,
        private StoreTransactions $storeTransactions,
        private MessageTransactions $messageTransactions,
        private PickupTransactions $pickupTransactions
    ) {
    }

    /**
     * @OA\Tag(name="pickup")
     * @Rest\Post("stores/{storeId}/pickups/{pickupDate}/{fsId}", requirements={"storeId" = "\d+", "pickupDate" = "[^/]+", "fsId" = "\d+"})
     */
    public function joinPickupAction(int $storeId, string $pickupDate, int $fsId): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }

        if (!$this->storePermissions->mayDoPickup($storeId)) {
            throw new AccessDeniedHttpException();
        }

        $date = TimeHelper::parsePickupDate($pickupDate);
        if (is_null($date)) {
            throw new BadRequestHttpException('Invalid date format');
        }

        try {
            $isConfirmed = $this->storeTransactions->joinPickup($storeId, $date, $fsId, $this->session->id());

            return $this->handleView($this->view([
                    'isConfirmed' => $isConfirmed
                ], 200));
        } catch (StoreTransactionException $ex) {
            throw new AccessDeniedHttpException($ex->getMessage(), $ex);
        }
    }

    /**
     * Remove a user from a pickup.
     *
     * @OA\Tag(name="pickup")
     * @Rest\Delete("stores/{storeId}/pickups/{pickupDate}/{fsId}", requirements={"storeId" = "\d+", "pickupDate" = "[^/]+", "fsId" = "\d+"})
     * @OA\RequestBody(@Model(type=PickupLeaveMessageOptions::class))
     * @ParamConverter("leaveInformation", class="Foodsharing\RestApi\Models\Store\PickupLeaveMessageOptions", converter="fos_rest.request_body")
     */
    public function leavePickupAction(int $storeId, string $pickupDate, int $fsId, PickupLeaveMessageOptions $leaveInformation, ValidatorInterface $validator): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }
        if (!$this->storePermissions->mayRemovePickupUser($storeId, $fsId)) {
            throw new AccessDeniedHttpException();
        }

        $errors = $validator->validate($leaveInformation);
        $this->throwBadRequestExceptionOnError($errors);

        $sendKickMessage = $leaveInformation->sendKickMessage || !$this->profilePermissions->mayCancelSlotsFromProfile($fsId);
        $this->leavePickup($storeId, $pickupDate, $fsId, $leaveInformation->message, $sendKickMessage);

        return $this->handleView($this->view([], 200));
    }

    /**
     * Remove a user from all his pickups.
     *
     * @OA\Tag(name="pickup")
     * @Rest\Delete("pickups/{fsId}", requirements={"fsId" = "\d+"})
     * @OA\RequestBody(@Model(type=PickupLeaveMessageOptions::class))
     * @ParamConverter("leaveInformation", class="Foodsharing\RestApi\Models\Store\PickupLeaveMessageOptions", converter="fos_rest.request_body")
     */
    public function leaveAllPickupsAction(int $fsId, PickupLeaveMessageOptions $leaveInformation, ValidatorInterface $validator)
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }
        if (!$this->profilePermissions->mayCancelSlotsFromProfile($fsId)) {
            throw new AccessDeniedHttpException();
        }

        $errors = $validator->validate($leaveInformation);
        $this->throwBadRequestExceptionOnError($errors);

        $pickups = $this->pickupGateway->getNextPickups($fsId);
        $sendKickMessage = $leaveInformation->sendKickMessage;

        foreach ($pickups as $pickup) {
            $this->leavePickup($pickup['store_id'], date(DATE_ATOM, $pickup['timestamp']), $fsId, $leaveInformation->message, $sendKickMessage);
        }

        return $this->handleView($this->view([], 200));
    }

    private function leavePickup(int $storeId, string $pickupDate, int $fsId, string $message = '', bool $sendKickMessage = true)
    {
        $message = trim($message);
        $date = TimeHelper::parsePickupDate($pickupDate);
        if (is_null($date)) {
            throw new BadRequestHttpException('Invalid date format');
        }

        if ($date < Carbon::now()) {
            throw new BadRequestHttpException('Cannot modify pickup in the past.');
        }

        if (!$this->pickupGateway->removeFetcher($fsId, $storeId, $date)) {
            throw new BadRequestHttpException('Failed to remove user from pickup');
        }

        if ($this->session->id() === $fsId) {
            $this->storeGateway->addStoreLog( // the user removed their own pickup
                $storeId,
                $fsId,
                null,
                $date,
                StoreLogAction::SIGN_OUT_SLOT
            );
        } else {
            $this->storeGateway->addStoreLog( // the user got kicked/the pickup got denied
                $storeId,
                $this->session->id(),
                $fsId,
                $date,
                StoreLogAction::REMOVED_FROM_SLOT,
                null,
                empty($message) ? null : $message
            );

            // send direct message to the user
            if ($sendKickMessage) {
                $formattedMessage = $this->storeTransactions->createKickMessage($fsId, $storeId, $date, $message);
                $this->messageTransactions->sendMessageToUser($fsId, $this->session->id(), $formattedMessage);
            }
        }
    }

    /**
     * @OA\Tag(name="pickup")
     * @Rest\Patch("stores/{storeId}/pickups/{pickupDate}/{fsId}", requirements={"storeId" = "\d+", "pickupDate" = "[^/]+", "fsId" = "\d+"})
     * @Rest\RequestParam(name="isConfirmed", nullable=true, default=null)
     */
    public function editPickupSlotAction(int $storeId, string $pickupDate, int $fsId, ParamFetcherInterface $paramFetcher): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }
        if (!$this->storePermissions->mayConfirmPickup($storeId)) {
            throw new AccessDeniedHttpException();
        }

        $date = TimeHelper::parsePickupDate($pickupDate);
        if (is_null($date)) {
            throw new BadRequestHttpException('Invalid date format');
        }

        if ($paramFetcher->get('isConfirmed')) {
            if (!$this->pickupGateway->confirmFetcher($fsId, $storeId, $date)) {
                throw new BadRequestHttpException();
            }
            $this->storeGateway->addStoreLog(
                $storeId,
                $this->session->id(),
                $fsId,
                $date,
                StoreLogAction::SLOT_CONFIRMED
            );
        }

        return $this->handleView($this->view([], 200));
    }

    /**
     * Return the regular pickups for an store.
     *
     * @OA\Tag(name="pickup")
     * @OA\Response(
     * 		response="200",
     * 		description="Success.",
     *      @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=RegularPickup::class))
     *     ))
     * @Rest\Get("stores/{storeId}/regularPickup", requirements={"storeId" = "\d+"})
     */
    public function getRegularPickup(int $storeId): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }

        if (!$this->storePermissions->maySeePickups($storeId)) {
            throw new AccessDeniedHttpException("No permission to access storeid '$storeId'");
        }

        try {
            $regularPickups = $this->pickupTransactions->getRegularPickup($storeId);
        } catch (\Exception $ex) {
            // catch invalid query
            throw new NotFoundHttpException('Store not found.', $ex);
        }

        return $this->handleView($this->view($regularPickups, 200));
    }

    /**
     * Configures the regular pickups for a store.
     *
     * @OA\Tag(name="stores")
     * @OA\RequestBody(@OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=RegularPickup::class))
     *     ))
     * @Rest\Put("stores/{storeId}/regularPickup", requirements={"storeId" = "\d+"})
     * @ParamConverter("regularPickups", class="array<Foodsharing\Modules\Store\DTO\RegularPickup>", converter="fos_rest.request_body")
     */
    public function editRegularPickupAction(int $storeId, array $regularPickups, ValidatorInterface $validator): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }
        if (!$this->storePermissions->mayEditPickups($storeId)) {
            throw new AccessDeniedHttpException();
        }

        $errors = $validator->validate($regularPickups);
        $this->throwBadRequestExceptionOnError($errors);

        try {
            $regularPickups = $this->pickupTransactions->replaceRegularPickup($storeId, $regularPickups);
        } catch (PickupValidationException $ex) {
            throw new BadRequestHttpException($ex->getMessage(), $ex);
        }

        return $this->handleView($this->view($regularPickups, 200));
    }

    /**
     * Creates or modifies a manual pick up for an store.
     *
     * @OA\Tag(name="stores")
     * @Rest\Patch("stores/{storeId}/pickups/{pickupDate}", requirements={"storeId" = "\d+", "pickupDate" = "[^/]+"})
     * @OA\Parameter(
     *         name="storeId",
     *         in="path",
     *         description="ID of store",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     )
     * @OA\Parameter(
     *         name="pickupDate",
     *         in="path",
     *         description="Pickup timestamp",
     *         required=true,
     *         example="2017-07-21T17:32:28Z",
     *         @OA\Schema(
     *             type="string",
     *             format="date-time"
     *         )
     *     )
     * @OA\Response(response="200", description="Created new pickup was successful")
     * @OA\Response(response="400", description="Bad request body")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="No permission to change pickup")
     * @OA\Response(response="404", description="Store not found")
     * @RequestParam(name="totalSlots", requirements="\d+", description="Maximum allowed user on this pickup.")
     * @RequestParam(name="description", requirements=".{0,100}", nullable=true, description="Description of this pickup.")
     */
    public function editPickupAction(int $storeId, string $pickupDate, ParamFetcherInterface $paramFetcher): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }

        if (!$this->storePermissions->mayEditPickups($storeId)) {
            $existingStore = $this->storeGateway->storeExists($storeId);
            if (!$existingStore) {
                throw new NotFoundHttpException("Store '$storeId' not found");
            } else {
                throw new AccessDeniedHttpException();
            }
        }

        $date = TimeHelper::parsePickupDate($pickupDate);
        if (is_null($date)) {
            throw new BadRequestHttpException('Invalid date format');
        }

        $totalSlots = $paramFetcher->get('totalSlots');
        if (!is_numeric($totalSlots)) {
            throw new BadRequestHttpException("Invalid 'totalSlots'");
        }

        $description = $paramFetcher->get('description');
        if (!(is_null($description) || is_string($description))) {
            throw new BadRequestHttpException("Invalid 'description'");
        }

        try {
            $pickup = new OneTimePickup();
            $pickup->date = $date;
            $pickup->slots = $totalSlots;
            $pickup->description = $description;

            $created = $this->storeTransactions->createOrUpdatePickup($storeId, $pickup);

            return $this->handleView($this->view(['created' => $created], 200));
        } catch (PickupValidationException $ex) {
            throw new BadRequestHttpException($ex->getMessage());
        }
    }

    /**
     * @OA\Tag(name="pickup")
     * @Rest\Get("stores/{storeId}/pickups", requirements={"storeId" = "\d+"})
     */
    public function listPickupsAction(int $storeId): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }
        if (!$this->storePermissions->maySeePickups($storeId)) {
            throw new AccessDeniedHttpException();
        }
        if (CarbonInterval::hours(Carbon::today()->diffInHours(Carbon::now()))->greaterThanOrEqualTo(CarbonInterval::hours(6))) {
            $fromTime = Carbon::today();
        } else {
            $fromTime = Carbon::today()->subHours(6);
        }

        $pickups = $this->pickupGateway->getPickupSlots($storeId, $fromTime);

        return $this->handleView($this->view([
            'pickups' => $this->enrichPickupSlots($pickups, $storeId)
        ]));
    }

    /**
     * @OA\Tag(name="pickup")
     * @Rest\Get("stores/{storeId}/history/{fromDate}/{toDate}", requirements={"storeId" = "\d+", "fromDate" = "[^/]+", "toDate" = "[^/]+"})
     */
    public function listPickupHistoryAction(int $storeId, string $fromDate, string $toDate): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }
        if (!$this->storePermissions->maySeePickupHistory($storeId)) {
            throw new AccessDeniedHttpException();
        }
        // convert date strings into datetime objects
        $from = TimeHelper::parsePickupDate($fromDate);
        $to = TimeHelper::parsePickupDate($toDate);
        if (is_null($from) || is_null($to)) {
            throw new BadRequestHttpException('Invalid date format');
        }
        $from = $from->min(Carbon::now());
        $to = $to->min(Carbon::now());

        $pickups = [[
            'occupiedSlots' => $this->pickupGateway->getPickupHistory($storeId, $from, $to)
        ]];

        return $this->handleView($this->view([
            'pickups' => $this->enrichPickupSlots($pickups, $storeId)
        ]));
    }

    private function enrichPickupSlots(array $pickups, int $storeId): array
    {
        $team = [];
        foreach ($this->storeGateway->getStoreTeam($storeId) as $user) {
            $team[$user['id']] = RestNormalization::normalizeStoreUser($user);
        }
        foreach ($pickups as &$pickup) {
            foreach ($pickup['occupiedSlots'] as &$slot) {
                if (isset($team[$slot['foodsaverId']])) {
                    $slot['profile'] = $team[$slot['foodsaverId']];
                } else {
                    $details = $this->foodsaverGateway->getFoodsaver($slot['foodsaverId']);
                    $slot['profile'] = RestNormalization::normalizeStoreUser($details);
                }
                unset($slot['foodsaverId']);
            }
        }
        unset($pickup);
        usort($pickups, function ($a, $b) {
            return $a['date']->lt($b['date']) ? -1 : 1;
        });

        $pickups = array_map(function ($pickup) {
            // Check required for history (does not contain dates)
            if (!empty($pickup['date'])) {
                // List of last and future and only future have a date on highest level
                $pickup['date'] = $pickup['date']->toIso8601String();
            }

            foreach ($pickup['occupiedSlots'] as &$slot) {
                // Check required for list of last and future pickups
                if (!empty($slot['date'])) {
                    // Time convertation needed for history
                    $slot['date'] = Carbon::createFromTimestamp($slot['date_ts'])->toIso8601String();
                }
            }

            return $pickup;
        }, $pickups);

        return $pickups;
    }

    /**
     * Get past pickups of a user.
     * Might be restricted to the last month depending on the permissions.
     *
     * @OA\Tag(name="pickup")
     * @Rest\Get("pickup/history")
     * @Rest\QueryParam(name="fsId", nullable=true, default=null)
     * @Rest\QueryParam(name="page", nullable=false, default=0)
     * @Rest\QueryParam(name="pageSize", nullable=false, default=50)
     */
    public function listPastPickupsAction(ParamFetcherInterface $paramFetcher): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }

        $fsId = (int)($paramFetcher->get('fsId') ?? $this->session->id());
        $page = (int)$paramFetcher->get('page');
        $pageSize = (int)$paramFetcher->get('pageSize');

        if (!$this->profilePermissions->maySeePickups($fsId)) {
            throw new AccessDeniedHttpException();
        }

        $maySeeFullHistory = $this->profilePermissions->maySeeAllPickups($fsId);

        $pickups = $this->pickupGateway->getPastPickups($fsId, $page, $pageSize, $maySeeFullHistory);

        $pickups = array_map(fn ($pickup) => [
            'date' => RestNormalization::normalizeDate($pickup['timestamp']),
            'store' => [
                'id' => $pickup['store_id'],
                'name' => $pickup['store_name'],
            ],
            'confirmed' => $pickup['confirmed'],
            'slots' => [
                'occupied' => array_map(
                    fn ($id, $name, $avatar, $confirmed) => [
                        'id' => (int)$id,
                        'name' => $name,
                        'avatar' => $avatar == '' ? null : $avatar,
                        'confirmed' => (int)$confirmed,
                    ],
                    str_getcsv($pickup['fs_ids']),
                    str_getcsv($pickup['fs_names'], ',', '\''),
                    str_getcsv($pickup['fs_avatars']),
                    str_getcsv($pickup['slot_confimations'])
                )
            ],
            'description' => $pickup['description']
        ], $pickups);

        return $this->handleView($this->view($pickups));
    }

    /**
     * Get all future pickups a user has registered.
     *
     * @OA\Tag(name="pickup")
     * @Rest\Get("pickup/registered")
     * @Rest\QueryParam(name="fsId", nullable=true, default=null)
     */
    public function listRegisteredPickupsAction(ParamFetcherInterface $paramFetcher): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }

        $fsId = (int)($paramFetcher->get('fsId') ?? $this->session->id());

        if (!$this->profilePermissions->maySeePickups($fsId)) {
            throw new AccessDeniedHttpException();
        }

        $pickups = $this->pickupGateway->getNextPickups($fsId);

        $pickups = array_map(fn ($pickup) => [
            'date' => RestNormalization::normalizeDate($pickup['timestamp']),
            'store' => [
                'id' => $pickup['store_id'],
                'name' => $pickup['store_name'],
            ],
            'confirmed' => $pickup['confirmed'],
            'slots' => [
                'occupied' => array_map(
                    fn ($id, $name, $avatar, $confirmed) => [
                        'id' => (int)$id,
                        'name' => $name,
                        'avatar' => $avatar == '' ? null : $avatar,
                        'confirmed' => (int)$confirmed,
                    ],
                    str_getcsv($pickup['fs_ids']),
                    str_getcsv($pickup['fs_names'], ',', '\''),
                    str_getcsv($pickup['fs_avatars']),
                    str_getcsv($pickup['slot_confimations'])
                ),
                'max' => $pickup['max_fetchers'],
            ],
            'description' => $pickup['description']
        ], $pickups);

        return $this->handleView($this->view($pickups));
    }

    /**
     * Get all pickup options a user has, including already registered slots.
     *
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="403", description="Insufficient permissions")
     * @OA\Tag(name="pickup")
     * @Rest\Get("pickup/options")
     * @Rest\QueryParam(name="page", nullable=false, default=0)
     * @Rest\QueryParam(name="pageSize", nullable=false, default=50)
     */
    public function listPickupOptionsAction(ParamFetcherInterface $paramFetcher): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }

        $page = (int)$paramFetcher->get('page');
        $pageSize = (int)$paramFetcher->get('pageSize');
        $id = $this->session->id();
        if (!$this->session->mayRole() || !$this->storePermissions->maySeePickupOptions($id)) {
            throw new AccessDeniedHttpException();
        }

        //fetch stores and pickup slots:
        $pickupOptions = [];
        $pickupSlots = null;

        $isConfirmed = function ($id, $users) {
            foreach ($users as $u) {
                if ($u['profile']['id'] === $id) {
                    return $u['isConfirmed'];
                }
            }

            return null;
        };

        foreach ($this->storeGateway->getStores($id) as $store) {
            $pickupSlots = $this->enrichPickupSlots(
                $this->pickupGateway->getPickupSlots($store['id']),
                $store['id']
            );

            $pickupOptions = array_merge($pickupOptions, array_map(
                fn ($slot) => [
                    'date' => RestNormalization::normalizeDate(strtotime($slot['date'])),
                    'store' => $store,
                    'confirmed' => $isConfirmed($id, $slot['occupiedSlots']),
                    'slots' => [
                        'occupied' => array_map(
                            fn ($user) => [
                                'id' => $user['profile']['id'],
                                'name' => $user['profile']['name'],
                                'avatar' => $user['profile']['avatar'],
                                'confirmed' => $user['isConfirmed'],
                            ],
                            $slot['occupiedSlots']
                        ),
                        'max' => $slot['totalSlots'],
                    ],
                    'description' => $slot['description']
                ],
                $pickupSlots
            ));
        }

        // Filtering (exclude completely filled slots without the user in them)
        $pickupOptions = array_values(array_filter(
            $pickupOptions,
            fn ($obj) => count($obj['slots']['occupied']) < $obj['slots']['max'] || !is_null($obj['confirmed'])
        ));

        usort($pickupOptions, fn ($a, $b) => strtotime($a['date']) <=> strtotime($b['date']));

        if ($page != -1 && $pageSize != -1) {
            $pickupOptions = array_slice($pickupOptions, $page * $pageSize, $pageSize);
        }

        return $this->handleView($this->view($pickupOptions));
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

    /**
     * Validation of PickupRuleCheck.
     *
     * @OA\Tag(name="pickup")
     * @Rest\Get("stores/{storeId}/pickupRuleCheck/{pickupDate}/{fsId}", requirements={"storeId" = "\d+", "pickupDate" = "[^/]+", "fsId" = "\d+"})"
     */
    public function passesPickupRule(int $storeId, string $pickupDate, int $fsId): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('');
        }

        // is it a valid pickupdate?
        $pickupSlotDate = TimeHelper::parsePickupDate($pickupDate);
        if (is_null($pickupSlotDate)) {
            throw new BadRequestHttpException('Invalid date format');
        }

        $response['result'] = $this->storeTransactions->checkPickupRule($storeId, $pickupSlotDate, $fsId);

        return $this->handleView($this->view($response));
    }
}
