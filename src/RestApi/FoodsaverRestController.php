<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Event\EventGateway;
use Foodsharing\Modules\Event\InvitationStatus;
use Foodsharing\Modules\Store\PickupGateway;
use Foodsharing\Permissions\ProfilePermissions;
use Foodsharing\Utility\TimeHelper;
use FOS\RestBundle\Controller\Annotations as Rest;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class FoodsaverRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        private readonly PickupGateway $pickupGateway,
        private readonly ProfilePermissions $profilePermissions,
        private readonly EventGateway $eventGateway,
        protected Session $session
    ) {
        parent::__construct($this->session);
    }

    #[OA\Tag(name: 'foodsaver')]
    #[Rest\Get('foodsaver/{fsId}/agenda/{onDate}', requirements: ['fsId' => '\d+', 'onDate' => '[^/]+'])]
    #[OA\Get(summary: 'Lists user agenda on a specific day, including pickups and events. The only works for future pickups and events.')]
    public function listSameDayAgenda(int $fsId, string $onDate): Response
    {
        $agenda = [];
        $this->assertLoggedIn();

        if (!$this->profilePermissions->maySeePickups($fsId)) {
            throw new AccessDeniedHttpException();
        }

        // convert date string into datetime object
        $day = TimeHelper::parsePickupDate($onDate);
        $pickups = $this->pickupGateway->getSameDayPickupsForUser($fsId, $day);

        foreach ($pickups as &$pickup) {
            $formattedPickup = [
                'name' => $pickup['storeName'],
                'id' => $pickup['storeId'],
                'isConfirmed' => boolval($pickup['isConfirmed']),
                'date' => $pickup['date'],
                'type' => 'store',
            ];
            // Add the pickup to the agenda
            $agenda[] = $formattedPickup;
        }

        $events = $this->eventGateway->getEventsByStatus($fsId, [InvitationStatus::INVITED, InvitationStatus::ACCEPTED, InvitationStatus::MAYBE], 0, $day);
        // Extend the pickups array by same-day events
        foreach ($events as $event) {
            $formattedEvent = [
                'name' => $event['name'],
                'id' => $event['id'],
                'status' => strtolower(InvitationStatus::from($event['status'])->name),
                'date' => $event['start'],
                'end' => $event['end'],
                'type' => 'event',
            ];
            $agenda[] = $formattedEvent;
        }

        // Insert the current pickup into the agenda
        $currentPickup = [
            'name' => null,
            'id' => -1,
            'isConfirmed' => false,
            'date' => $day->format('Y-m-d H:i:s'),
            'type' => 'proposal',
        ];
        $agenda[] = $currentPickup;

        // Sort the agenda by date
        usort($agenda, function ($a, $b) {
            return strtotime($a['date']) <=> strtotime($b['date']);
        });

        return $this->respondOK($agenda);
    }
}
