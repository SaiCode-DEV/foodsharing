<?php

namespace Foodsharing\Modules\Event;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Region\RegionController;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Permissions\EventPermissions;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

class EventController extends FoodsharingController
{
    public function __construct(
        private readonly EventGateway $eventGateway,
        private readonly RegionGateway $regionGateway,
        private readonly EventPermissions $eventPermissions,
        private readonly RegionController $regionController,
    ) {
        parent::__construct();

        if (!$this->session->mayRole()) {
            $this->routeHelper->goLoginAndExit();
        }
    }

    #[Route('/event/{eventId}/edit', name: 'edit_event', requirements: ['eventId' => Requirement::DIGITS])]
    public function edit(int $eventId): Response
    {
        $event = $this->eventGateway->getEvent($eventId);

        if (!$event) {
            return $this->redirectToRoute('dashboard');
        }

        if (!$this->eventPermissions->mayEditEvent($event)) {
            return $this->redirect('/event/' . $eventId);
        }

        $regionEventsLink = '/region?sub=events&bid=' . $event->regionId;
        $this->pageHelper->addBread($this->translator->trans('events.bread'), $regionEventsLink);
        $this->pageHelper->addBread($event->name, '/event/' . $eventId);
        $this->pageHelper->addBread($this->translator->trans('events.edit'));

        $this->pageHelper->addContent($this->prepareVueComponent('event-form', 'EventForm', [
            'edit' => $event,
        ]));

        return $this->renderGlobal();
    }

    #[Route('/event/add', name: 'add_event')]
    public function addNewEvent(Request $request): Response
    {
        $this->pageHelper->addBread($this->translator->trans('events.bread'), '/event/add');
        $this->pageHelper->addBread($this->translator->trans('events.create.title'));

        $regionId = (int)$request->query->get('bid', 0);

        $this->pageHelper->addContent($this->prepareVueComponent('event-form', 'EventForm', [
            'regionId' => $regionId,
        ]));

        return $this->renderGlobal();
    }

    #[Route('/event', name: 'event_index')]
    public function index(Request $request): RedirectResponse
    {
        if ($request->query->has('id')) {
            $eventId = intval($request->query->get('id'));
        }

        if (empty($eventId)) {
            return $this->redirectToRoute('dashboard');
        }

        return $this->redirectToRoute('show_event', ['eventId' => $eventId]);
    }

    #[Route('/event/{eventId}', name: 'show_event', requirements: ['eventId' => Requirement::DIGITS])]
    public function showEvent(int $eventId): Response
    {
        $event = $this->eventGateway->getEvent($eventId);
        if (!$event || !$this->eventPermissions->maySeeEvent($event)) {
            return $this->regionController->missingMembershipRedirect($event->regionId);
        }

        // Bread
        $regionLink = '/region?bid=' . $event->regionId;
        $regionEventsLink = $regionLink . '&sub=events';
        $regionName = $this->regionGateway->getRegionName($event->regionId);
        if (empty($regionName)) {
            $regionName = '';
        }
        $this->pageHelper->addBread($regionName, $regionLink);
        $this->pageHelper->addBread($this->translator->trans('events.bread'), $regionEventsLink);
        $this->pageHelper->addBread($event->name);

        // Data for vue page
        $mayEdit = $this->eventPermissions->mayEditEvent($event);
        $attendees = $this->eventGateway->getEventAttendees($eventId);
        $inviteStatus = $this->eventGateway->getInviteStatus($eventId, $this->session->id());
        $attendees['inviteCount'] = $this->regionGateway->getRegionDetails($event->regionId)['fs_count'];

        $this->pageHelper->addContent($this->prepareVueComponent('event-page', 'EventPage', [
            'event' => $event,
            'mayEdit' => $mayEdit,
            'attendees' => $attendees,
            'inviteStatus' => $inviteStatus,
        ]));

        return $this->renderGlobal();
    }
}
