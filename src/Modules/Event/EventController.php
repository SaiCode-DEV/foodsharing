<?php

namespace Foodsharing\Modules\Event;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Core\DBConstants\Event\EventType;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Permissions\EventPermissions;
use Foodsharing\Utility\DataHelper;
use Foodsharing\Utility\PostHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Requirement\Requirement;

class EventController extends FoodsharingController
{
    public function __construct(
        private readonly EventView $view,
        private readonly EventGateway $eventGateway,
        private readonly RegionGateway $regionGateway,
        private readonly DataHelper $dataHelper,
        private readonly EventPermissions $eventPermissions,
        private readonly PostHelper $postHelper,
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
            return $this->routeHelper->goAndExit('/?page=dashboard');
        }

        if (!$this->eventPermissions->mayEditEvent($event)) {
            return $this->routeHelper->goAndExit('/event/' . $eventId);
        }

        $regionEventsLink = '/region?sub=events&bid=' . $event['bezirk_id'];
        $this->pageHelper->addBread($this->translator->trans('events.bread'), $regionEventsLink);
        $this->pageHelper->addBread($event['name'], '/event/' . $eventId);
        $this->pageHelper->addBread($this->translator->trans('events.edit'));

        if ($this->isSubmitted() && $data = $this->validateEvent()) {
            if ($this->eventGateway->updateEvent($eventId, $data)) {
                $this->flashMessageHelper->success($this->translator->trans('events.edited'));
                $this->routeHelper->goAndExit('/event/' . $eventId);
            }
        }

        $regions = $this->currentUserUnits->getRegions();

        if (($event['location_id'] !== null) && $loc = $this->eventGateway->getLocation($event['location_id'])) {
            $event['location_name'] = $loc['name'];
            $event['lat'] = $loc['lat'];
            $event['lon'] = $loc['lon'];
            $event['plz'] = $loc['zip'];
            $event['ort'] = $loc['city'];
            $event['anschrift'] = $loc['street'];
        }

        $this->dataHelper->setEditData($event);

        $this->pageHelper->addContent($this->view->eventForm($regions));

        return $this->renderGlobal();
    }

    #[Route('/event/add', name: 'add_event')]
    public function addNewEvent(): Response
    {
        $this->pageHelper->addBread($this->translator->trans('events.bread'), '/event/add');
        $this->pageHelper->addBread($this->translator->trans('events.create.title'));

        if ($this->isSubmitted()) {
            $data = $this->validateEvent();
            if (!$data ||
                $data['bezirk_id'] == RegionIDs::ROOT ||
                !$this->eventPermissions->mayCreateEvent($data['bezirk_id'])) {
                $this->flashMessageHelper->error($this->translator->trans('region.not-member'));
                $this->routeHelper->goAndExit('/?page=dashboard');
            } elseif ($id = $this->eventGateway->addEvent($this->session->id(), $data)) {
                $this->flashMessageHelper->success($this->translator->trans('events.created'));
                $this->routeHelper->goAndExit('/event/' . $id);
            }
        } else {
            $regions = $this->currentUserUnits->getRegions();

            $this->pageHelper->addContent($this->view->eventForm($regions));
        }

        return $this->renderGlobal();
    }

    #[Route('/event', name: 'event_index')]
    public function index(Request $request): RedirectResponse
    {
        if ($request->query->has('id')) {
            $eventId = intval($request->query->get('id'));
        }

        if (empty($eventId)) {
            return $this->redirect('/?page=dashboard');
        }

        return $this->redirectToRoute('show_event', ['eventId' => $eventId]);
    }

    #[Route('/event/{eventId}', name: 'show_event', requirements: ['eventId' => Requirement::DIGITS])]
    public function showEvent(int $eventId): Response
    {
        $event = $this->eventGateway->getEvent($eventId, true);
        if (!$event || !$this->eventPermissions->maySeeEvent($event)) {
            $this->flashMessageHelper->info($this->translator->trans('events.notFound'));

            return $this->routeHelper->goAndExit('/?page=dashboard');
        }

        $regionId = $event['bezirk_id'];
        $regionLink = '/region?bid=' . $regionId;
        $regionEventsLink = $regionLink . '&sub=events';
        $regionName = $this->regionGateway->getRegionName($regionId);
        $event['inviteCount'] = $this->regionGateway->getRegionDetails($regionId)['fs_count'];
        if (empty($regionName)) {
            $regionName = '';
        }
        $this->pageHelper->addBread($regionName, $regionLink);
        $this->pageHelper->addBread($this->translator->trans('events.bread'), $regionEventsLink);
        $this->pageHelper->addBread($event['name']);

        $status = $this->eventGateway->getInviteStatus($eventId, $this->session->id());
        $event['status'] = $status;
        $event['regionName'] = $regionName;

        $mayEdit = $this->eventPermissions->mayEditEvent($event);

        $this->pageHelper->addContent($this->view->eventPanel($event, $mayEdit), CNT_TOP);
        $this->pageHelper->addContent($this->view->event($event));
        $this->pageHelper->setContentWidth(6, 6);

        if ($event['online'] == 0 && $event['location'] != false) {
            $this->pageHelper->addContent($this->view->location($event['location']), CNT_LEFT);
        } elseif ($event['online'] == 1) {
            $this->pageHelper->addContent($this->view->locationMumble(), CNT_LEFT);
        }

        if ($event['invites']) {
            $this->pageHelper->addContent($this->view->invites($event['invites']), CNT_RIGHT);
        }

        $this->pageHelper->addContent($this->view->vueComponent('vue-wall', 'wall', [
            'target' => 'event',
            'targetId' => $eventId,
        ]));

        return $this->renderGlobal();
    }

    private function isSubmitted(): bool
    {
        return !empty($_POST);
    }

    private function validateEvent(): array
    {
        $out = [
            'name' => '',
            'description' => '',
            'location_id' => null,
            'start' => date('Y-m-d') . ' 15:00:00',
            'end' => date('Y-m-d') . ' 16:00:00',
            'bezirk_id' => 0,
            'online' => 0,
        ];

        if ($regionId = $this->postHelper->getPostInt('bezirk_id')) {
            $out['bezirk_id'] = $regionId;
        }

        if (($start_date = $this->postHelper->getPostDate('date')) && $start_time = $this->postHelper->getPostTime('time_start')) {
            if ($end_time = $this->postHelper->getPostTime('time_end')) {
                $out['start'] = date('Y-m-d', $start_date) . ' ' . sprintf('%02d', $start_time['hour']) . ':' . sprintf(
                    '%02d',
                    $start_time['min']
                ) . ':00';
                $out['end'] = date('Y-m-d', $start_date) . ' ' . sprintf('%02d', $end_time['hour']) . ':' . sprintf(
                    '%02d',
                    $end_time['min']
                ) . ':00';

                if ((int)$this->postHelper->getPostInt('addend') == 1 && ($ed = $this->postHelper->getPostDate('dateend'))) {
                    $out['end'] = date('Y-m-d', $ed) . ' ' . sprintf('%02d', $end_time['hour']) . ':' . sprintf(
                        '%02d',
                        $end_time['min']
                    ) . ':00';
                }
            }
        }

        if ($name = $this->postHelper->getPostString('name')) {
            $out['name'] = $name;
        }

        if ($description = $this->postHelper->getPostString('description')) {
            $out['description'] = $description;
        }

        $online_type = $this->postHelper->getPostInt('online_type');

        if (EventType::isOnline($online_type)) {
            $out['online'] = 1;
            $out['location_id'] = null;
        } else {
            $out['online'] = 0;
            $id = $this->eventGateway->addLocation(
                $this->postHelper->getPostString('location_name'),
                $this->postHelper->getPost('lat'),
                $this->postHelper->getPost('lon'),
                $this->postHelper->getPostString('anschrift'),
                $this->postHelper->getPostString('plz'),
                $this->postHelper->getPostString('ort')
            );
            $out['location_id'] = $id;
        }

        return $out;
    }
}
