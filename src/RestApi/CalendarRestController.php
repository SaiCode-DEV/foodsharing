<?php

namespace Foodsharing\RestApi;

use Carbon\Carbon;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Event\EventGateway;
use Foodsharing\Modules\Event\InvitationStatus;
use Foodsharing\Modules\Settings\SettingsGateway;
use Foodsharing\Modules\Store\PickupGateway;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use Jsvrcek\ICS\CalendarExport;
use Jsvrcek\ICS\CalendarStream;
use Jsvrcek\ICS\Exception\CalendarEventException;
use Jsvrcek\ICS\Model\Calendar;
use Jsvrcek\ICS\Model\CalendarEvent;
use Jsvrcek\ICS\Model\Description\Location;
use Jsvrcek\ICS\Utility\Formatter;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Provides endpoints for exporting pickup dates and other events to iCal and managing access tokens.
 */
class CalendarRestController extends AbstractFOSRestController
{
    private readonly Session $session;
    private readonly SettingsGateway $settingsGateway;
    private readonly PickupGateway $pickupGateway;
    private readonly EventGateway $eventGateway;
    private readonly TranslatorInterface $translator;

    private const TOKEN_LENGTH_IN_BYTES = 10;

    public function __construct(
        Session $session,
        SettingsGateway $settingsGateway,
        PickupGateway $pickupGateway,
        EventGateway $eventGateway,
        TranslatorInterface $translator
    ) {
        $this->session = $session;
        $this->settingsGateway = $settingsGateway;
        $this->pickupGateway = $pickupGateway;
        $this->eventGateway = $eventGateway;
        $this->translator = $translator;
    }

    /**
     * Returns the user's current access token.
     *
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Tag(name="calendar")
     */
    #[Rest\Get('calendar/token')]
    public function getToken(): Response
    {
        $userId = $this->session->id();
        if (!$userId) {
            throw new UnauthorizedHttpException('');
        }

        $token = $this->settingsGateway->getApiToken($userId);

        return $this->handleView($this->view(['token' => $token]));
    }

    /**
     * Creates a new random access token for the user. An existing token will be overwritten. Returns
     * the created token.
     *
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Tag(name="calendar")
     */
    #[Rest\Put('calendar/token')]
    public function createToken(): Response
    {
        $userId = $this->session->id();
        if (!$userId) {
            throw new UnauthorizedHttpException('');
        }

        $token = bin2hex(openssl_random_pseudo_bytes(self::TOKEN_LENGTH_IN_BYTES));
        $this->settingsGateway->removeApiToken($userId);
        $this->settingsGateway->saveApiToken($userId, $token);

        return $this->handleView($this->view(['token' => $token]));
    }

    /**
     * Removes the user's token. If the user does not have a token nothing will happen.
     *
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Tag(name="calendar")
     */
    #[Rest\Delete('calendar/token')]
    public function deleteToken(): Response
    {
        $userId = $this->session->id();
        if (!$userId) {
            throw new UnauthorizedHttpException('');
        }

        $this->settingsGateway->removeApiToken($userId);

        return $this->handleView($this->view());
    }

    /**
     * Returns the user's future foodsharing dates as iCal.
     *
     * This includes pickups and meetings / events.
     *
     * @OA\Parameter(name="token", in="path", @OA\Schema(type="string"), description="Access token")
     * @OA\Response(response="200", description="Success.")
     * @OA\Response(response="403", description="Insufficient permissions or invalid token.")
     * @OA\Tag(name="calendar")
     */
    #[Rest\Get('calendar/{token}')]
    #[Rest\QueryParam(name: 'events', requirements: '(all|answered)', default: 'all', description: 'Include all or only answered invitations to events')]
    public function listAppointments(string $token, ParamFetcher $paramFetcher): Response
    {
        // check access token
        $userId = $this->settingsGateway->getUserForToken($token);
        if (!$userId) {
            throw new AccessDeniedHttpException();
        }

        // add all future pickup dates
        $dates = $this->pickupGateway->getNextPickups($userId);
        $pickups = array_map(fn ($date) => $this->createPickupEvent($date, $userId), $dates);

        // add all future meetings
        $statuses = match ($paramFetcher->get('events')) {
            'answered' => [InvitationStatus::ACCEPTED, InvitationStatus::MAYBE],
            default => [InvitationStatus::ACCEPTED, InvitationStatus::MAYBE, InvitationStatus::INVITED],
        };
        $meetings = $this->eventGateway->getEventsByStatus($userId, $statuses);
        $events = array_map(fn ($meeting) => $this->createMeetingEvent($meeting, $userId), $meetings);

        return new Response($this->formatCalendarResponse(array_merge($pickups, $events)), Response::HTTP_OK, [
            'content-type' => 'text/calendar',
            'content-disposition' => 'attachment; filename="calendar.ics"'
        ]);
    }

    private function createPickupEvent(array $pickup, int $userId): CalendarEvent
    {
        $start = Carbon::createFromTimestamp($pickup['timestamp']);

        $summary = $this->translator->trans('calendar.export.pickup.name', ['{store}' => $pickup['store_name']]);
        $status = 'CONFIRMED';
        if (!$pickup['confirmed']) {
            $summary .= ' (' . $this->translator->trans('calendar.export.pickup.unconfirmed') . ')';
            $status = 'TENTATIVE';
        }

        $location = (new Location())->setName($pickup['address']);
        $store_url = BASE_URL . '/?page=fsbetrieb&id=' . $pickup['store_id'];

        $event = new CalendarEvent();
        $event->setStart($start);
        $event->setEnd($start->clone()->addMinutes(30));
        $event->setSummary($summary);
        $event->setUid($userId . $pickup['store_id'] . $pickup['timestamp'] . '@fetch.foodsharing.de');
        $event->setDescription($this->translator->trans(
            'calendar.export.pickup.description',
            [
                '{url}' => $store_url,
                '{store}' => $pickup['store_name'],
            ]
        ));
        $event->setUrl($store_url);
        $event->setStatus($status);
        $event->addLocation($location);

        return $event;
    }

    private function createMeetingEvent(array $meeting, int $userId): CalendarEvent
    {
        $url = BASE_URL . '/?page=event&id=' . $meeting['id'];

        $descriptionHint = '';
        if ($meeting['status'] == InvitationStatus::INVITED) {
            $descriptionHint = '<i>' . $this->translator->trans('calendar.export.event.statusUnspecified') . '</i><br>';
        }
        $description = '<a href="' . $url . '">' . $this->translator->trans('calendar.export.event.linkTitle') . '</a><br>'
            . $descriptionHint
            . '<b>' . $this->translator->trans('calendar.export.event.description') . '</b>: '
            . str_replace(["\r\n", "\n", "\r"], '<br>', (string)$meeting['description']);

        $event = new CalendarEvent();
        $event->setStart(Carbon::createFromTimestamp($meeting['start_ts']));
        try {
            $event->setEnd(Carbon::createFromTimestamp($meeting['end_ts']));
        } catch (CalendarEventException) {
            /* In some events the end date is before the start date because the event form accidentally allows this.
            This workaround prevents errors and can be removed after the event form was updated. */
            $newEnd = clone $event->getStart();
            $event->setEnd($newEnd->modify('+1 hour'));
        }
        $event->setSummary($meeting['name']);
        $event->setUid($userId . $meeting['id'] . '@meeting.foodsharing.de');
        $event->setDescription($description);
        $event->setUrl($url);
        $event->setStatus(['TENTATIVE', 'CONFIRMED', 'TENTATIVE'][$meeting['status']]);

        if ($meeting['street']) {
            $full_address = $meeting['street'] . ', ' . $meeting['zip'] . ' ' . $meeting['city'];
            $location = (new Location())->setName($full_address);
            $event->addLocation($location);
        }

        return $event;
    }

    /**
     * Formats a list of events into an iCal calendar string.
     *
     * @param CalendarEvent[] $events
     */
    private function formatCalendarResponse(array $events): string
    {
        $calendar = new Calendar();
        $calendar->setTimezone(new \DateTimeZone('Europe/Berlin'));
        $calendar->setProdId('-//Foodsharing//Calendar//DE');

        foreach ($events as $e) {
            $calendar->addEvent($e);
        }

        $calendarExport = new CalendarExport(new CalendarStream(), new Formatter());
        $calendarExport->addCalendar($calendar);

        return $calendarExport->getStream();
    }
}
