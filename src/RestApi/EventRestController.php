<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Event\DTO\Event;
use Foodsharing\Modules\Event\DTO\EventForListView;
use Foodsharing\Modules\Event\EventGateway;
use Foodsharing\Modules\Event\EventTransactions;
use Foodsharing\Modules\Event\InvitationStatus;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Permissions\EventPermissions;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'events')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
class EventRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        protected Session $session,
        private readonly EventGateway $eventGateway,
        private readonly EventPermissions $eventPermissions,
        private readonly EventTransactions $eventTransactions,
        protected readonly CurrentUserUnitsInterface $currentUserUnits
    ) {
        parent::__construct($this->session);
    }

    #[OA\Put(summary: 'Updates the user\'s response to an invitation.')]
    #[Route('events/{eventId}/invitation', methods: ['PUT'], requirements: ['eventId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Event doesn\'t exist')]
    public function setResponse(int $eventId, #[MapQueryParameter] InvitationStatus $status): Response
    {
        $this->assertLoggedIn();

        // check that the event exists
        $event = $this->eventGateway->getEvent($eventId);
        if (empty($event)) {
            throw new NotFoundHttpException('Event doesn\'t exist');
        }
        if (!$this->eventPermissions->mayJoinEvent($event)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $this->eventGateway->setInviteStatus($eventId, $this->session->id(), $status);

        return $this->respondOK();
    }

    #[OA\Get(summary: 'List events for region and groups.')]
    #[Route('region/{regionId}/events', methods: ['GET'], requirements: ['regionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(ref: new Model(type: EventForListView::class))
    ))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function listEvents(int $regionId): Response
    {
        $this->assertLoggedIn();

        if (!$this->currentUserUnits->mayBezirk($regionId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $events = $this->eventGateway->listForRegion($regionId);

        return $this->respondOK($events);
    }

    #[OA\Post(summary: 'Add a new event.')]
    #[Route('events', methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'id', type: 'integer', description: 'Id of the newly created event')
    ]))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions for this region')]
    public function addEvents(#[MapRequestPayload] Event $event): Response
    {
        $this->assertLoggedIn();

        if (!$this->currentUserUnits->mayBezirk($event->regionId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $eventId = $this->eventTransactions->addEvent($this->session->id(), $event);

        return $this->respondOK(['id' => $eventId]);
    }

    #[OA\Patch(summary: 'Edit an event.')]
    #[Route('events/{eventId}', methods: ['PATCH'], requirements: ['eventId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Event doesn\'t exist')]
    public function editEvents(int $eventId, #[MapRequestPayload] Event $event): Response
    {
        $this->assertLoggedIn();
        $event->id = $eventId;
        $currentEvent = $this->eventGateway->getEvent($eventId);
        if (empty($currentEvent)) {
            throw new NotFoundHttpException('Event doesn\'t exist');
        }
        if (!$this->eventPermissions->mayEditEvent($currentEvent)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $this->eventTransactions->editEvent($event);

        return $this->respondOK();
    }
}
