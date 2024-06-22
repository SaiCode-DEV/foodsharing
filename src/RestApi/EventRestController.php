<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Event\DTO\Event;
use Foodsharing\Modules\Event\EventGateway;
use Foodsharing\Modules\Event\EventTransactions;
use Foodsharing\Modules\Event\InvitationStatus;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Permissions\EventPermissions;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'events')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
class EventRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        private readonly EventGateway $eventGateway,
        private readonly EventPermissions $eventPermissions,
        private readonly EventTransactions $eventTransactions,
        protected Session $session,
        protected readonly CurrentUserUnitsInterface $currentUserUnits
    ) {
    }

    #[OA\Patch(summary: 'Updates the user\'s response to an invitation.')]
    #[Rest\Patch('users/current/events/{eventId}/invitation', requirements: ['eventId' => Requirement::POSITIVE_INT])]
    #[Rest\RequestParam(name: 'status', requirements: Requirement::POSITIVE_INT, nullable: false)]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid status code')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to join the event')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Event doesn\'t exist')]
    public function setResponse(int $eventId, ParamFetcher $paramFetcher): Response
    {
        $this->assertLoggedIn();
        $fsId = $this->session->id();

        // check that the event exists
        $event = $this->eventGateway->getEvent($eventId);
        if (empty($event)) {
            throw new NotFoundHttpException();
        }
        if (!$this->eventPermissions->mayJoinEvent($event)) {
            throw new AccessDeniedHttpException();
        }
        // check that the status is valid
        $status = (int)$paramFetcher->get('status');
        if (!InvitationStatus::isValidStatus($status)) {
            throw new BadRequestHttpException();
        }

        $this->eventGateway->setInviteStatus($eventId, $fsId, $status);

        return $this->respondOK();
    }

    #[OA\Get(summary: 'List events for region and groups.')]
    #[Rest\Get('region/{regionId}/events', requirements: ['regionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions for this region')]
    public function listEventsAction(int $regionId): Response
    {
        $this->assertLoggedIn();

        if (!$this->currentUserUnits->mayBezirk($regionId)) {
            throw new AccessDeniedHttpException();
        }

        $events = $this->eventGateway->listForRegion($regionId);

        return $this->respondOK($events);
    }

    #[OA\Post(summary: 'Add a new event.')]
    #[Rest\Post('events')]
    #[OA\RequestBody(content: new Model(type: Event::class))]
    #[ParamConverter('event', class: Event::class, converter: 'fos_rest.request_body')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions for this region')]
    public function addEventsAction(Event $event, ValidatorInterface $validator): Response
    {
        $this->assertLoggedIn();
        $this->assertThereAreNoValidationErrors($validator, $event);

        $eventId = $this->eventTransactions->addEvent($this->session->id(), $event);

        return $this->respondOK($eventId);
    }

    #[OA\Post(summary: 'Edit an event.')]
    #[Rest\Patch('events/{eventId}', requirements: ['eventId' => Requirement::POSITIVE_INT])]
    #[OA\RequestBody(content: new Model(type: Event::class))]
    #[ParamConverter('event', class: Event::class, converter: 'fos_rest.request_body')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to edit this event')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid request parameters')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Event doesn\'t exist')]
    public function editEventsAction(int $eventId, Event $event, ValidatorInterface $validator): Response
    {
        $this->assertLoggedIn();
        $this->assertThereAreNoValidationErrors($validator, $event);
        $event->id = $eventId;
        $currentEvent = $this->eventGateway->getEvent($eventId);
        if (empty($currentEvent)) {
            throw new NotFoundHttpException();
        }
        $this->eventPermissions->mayEditEvent($currentEvent);

        $this->eventTransactions->editEvent($event);

        return $this->respondOK();
    }
}
