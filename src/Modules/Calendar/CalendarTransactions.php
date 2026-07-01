<?php

namespace Foodsharing\Modules\Calendar;

use Carbon\Carbon;
use DateTimeZone;
use Foodsharing\Modules\Calendar\DTO\FormattingType;
use Foodsharing\Modules\Calendar\DTO\IncludeEventsType;
use Foodsharing\Modules\Categories\StoreCategoryType;
use Foodsharing\Modules\Event\EventGateway;
use Foodsharing\Modules\Event\InvitationStatus;
use Foodsharing\Modules\Settings\SettingsGateway;
use Foodsharing\Modules\Store\PickupGateway;
use Foodsharing\Utility\Sanitizer;
use Jsvrcek\ICS\CalendarExport;
use Jsvrcek\ICS\CalendarStream;
use Jsvrcek\ICS\Exception\CalendarEventException;
use Jsvrcek\ICS\Model\Calendar;
use Jsvrcek\ICS\Model\CalendarAlarm;
use Jsvrcek\ICS\Model\CalendarEvent;
use Jsvrcek\ICS\Model\Description\Location;
use Jsvrcek\ICS\Utility\Formatter;
use Symfony\Contracts\Translation\TranslatorInterface;

class CalendarTransactions
{
    final public const int TOKEN_LENGTH_IN_BYTES = 10;

    public function __construct(
        private readonly SettingsGateway $settingsGateway,
        private readonly PickupGateway $pickupGateway,
        private readonly EventGateway $eventGateway,
        private readonly TranslatorInterface $translator,
        private readonly Sanitizer $sanitizer,
    ) {
    }

    public function createToken(int $userId): string
    {
        $token = bin2hex(openssl_random_pseudo_bytes(self::TOKEN_LENGTH_IN_BYTES));
        $this->settingsGateway->removeApiToken($userId);
        $this->settingsGateway->saveApiToken($userId, $token);

        return $token;
    }

    /**
     * @param int[] $reminders
     */
    public function listAppointments(
        int $userId,
        FormattingType $formatting,
        IncludeEventsType $includedEvents,
        bool $includePickups,
        bool $includeHistory,
        array $reminders
    ): string {
        $bufferDays = $includeHistory ? 14 : 0;
        $bufferMinutes = $bufferDays * 24 * 60;

        // add all future pickup dates
        $pickups = [];
        if ($includePickups) {
            $dates = $this->pickupGateway->getNextPickups($userId, null, $bufferMinutes);
            $pickups = array_map(fn ($date) => $this->createPickupEvent($date, $userId, $formatting, $reminders), $dates);
        }

        // add all future meetings
        $statuses = match ($includedEvents) {
            IncludeEventsType::EVERY => [InvitationStatus::ACCEPTED, InvitationStatus::MAYBE, InvitationStatus::INVITED, InvitationStatus::WONT_JOIN],
            IncludeEventsType::INVITATIONS => [InvitationStatus::ACCEPTED, InvitationStatus::MAYBE, InvitationStatus::INVITED],
            IncludeEventsType::MAYBE => [InvitationStatus::ACCEPTED, InvitationStatus::MAYBE],
            IncludeEventsType::ACCEPTED => [InvitationStatus::ACCEPTED],
            IncludeEventsType::NONE => [],
        };
        $meetings = $this->eventGateway->getEventsByStatus($userId, $statuses, $bufferDays);
        $events = array_map(fn ($meeting) => $this->createMeetingEvent($meeting, $userId, $formatting, $reminders), $meetings);

        return $this->formatCalendarResponse(array_merge($pickups, $events));
    }

    /**
     * @param int[] $reminders
     */
    private function createPickupEvent(array $pickup, int $userId, FormattingType $formatting, array $reminders): CalendarEvent
    {
        $start = Carbon::createFromTimestamp($pickup['timestamp'], new DateTimeZone('Europe/Berlin'));

        if ($pickup['ktype'] === StoreCategoryType::ORGA->value) {
            $summary = $this->translator->trans('calendar.export.pickup.name.orga', ['{store}' => $pickup['store_name']]);
        } elseif ($pickup['ktype'] === StoreCategoryType::GIVING->value) {
            $summary = $this->translator->trans('calendar.export.pickup.name.giving', ['{store}' => $pickup['store_name']]);
        } else {
            $summary = $this->translator->trans('calendar.export.pickup.name.pickup', ['{store}' => $pickup['store_name']]);
        }
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
        $description = $this->translator->trans('calendar.export.pickup.description', [
            '{url}' => $store_url,
            '{store}' => $pickup['store_name'],
        ]);
        $foodsaverIds = str_getcsv((string)$pickup['fs_ids']);
        $foodsaverNames = str_getcsv((string)$pickup['fs_names'], ',', "'");

        if (count($foodsaverIds)) {
            $description .= '<br>' . $this->translator->trans('calendar.export.pickup.foodsavers');
            $description .= implode(', ', array_map(fn ($id, $name) => '<a href="' . BASE_URL . "/profile/{$id}\">{$name}</a>", $foodsaverIds, $foodsaverNames));
        }

        if ($freeSlots = $pickup['max_fetchers'] - count($foodsaverIds)) {
            $description .= '<br>' . $this->translator->trans('calendar.export.pickup.freeSlots', [
                '{count}' => $freeSlots,
            ]);
        }

        if ($pickup['description']) {
            $description .= '<br><br>' . $pickup['description'];
        }

        if (!empty($pickup['store_description'])) {
            $storeInfo = (string)$pickup['store_description'];
            if ($formatting === FormattingType::HTML) {
                $storeInfo = $this->sanitizer->markdownToHtml($storeInfo);
            } else {
                $storeInfo = str_replace(["\r\n", "\n", "\r"], '<br>', $storeInfo);
            }
            $description .= '<br><br>' . $this->translator->trans('calendar.export.pickup.storeInfo')
                . '<br>' . $storeInfo;
        }

        $this->setEventDescription($event, $description, $formatting);
        $event->setUrl($store_url);
        $event->setStatus($status);
        $event->addLocation($location);
        $this->addReminders($event, $reminders);

        return $event;
    }

    /**
     * @param int[] $reminders
     */
    private function createMeetingEvent(array $meeting, int $userId, FormattingType $formatting, array $reminders): CalendarEvent
    {
        $url = BASE_URL . '/?page=event&id=' . $meeting['id'];

        $descriptionHint = '';
        if ($meeting['status'] == InvitationStatus::INVITED) {
            $descriptionHint = '<i>' . $this->translator->trans('calendar.export.event.statusUnspecified') . '</i><br>';
        }
        $descriptionContent = (string)$meeting['description'];
        $linebreakReplacement = '<br>';
        if ($formatting === FormattingType::HTML) {
            $descriptionContent = $this->sanitizer->markdownToHtml($descriptionContent);
            $linebreakReplacement = '';
        }
        $descriptionContent = str_replace(["\r\n", "\n", "\r"], $linebreakReplacement, $descriptionContent);
        $description = '<a href="' . $url . '">' . $this->translator->trans('calendar.export.event.linkTitle') . '</a><br>'
            . $descriptionHint
            . '<br><b>' . $this->translator->trans('calendar.export.event.description') . '</b>: '
            . $descriptionContent;

        $event = new CalendarEvent();
        $event->setStart(Carbon::createFromTimestamp($meeting['start_ts'], new DateTimeZone('Europe/Berlin')));
        try {
            $event->setEnd(Carbon::createFromTimestamp($meeting['end_ts'], new DateTimeZone('Europe/Berlin')));
        } catch (CalendarEventException) {
            /* In some events the end date is before the start date because the event form accidentally allows this.
            This workaround prevents errors and can be removed after the event form was updated. */
            $newEnd = clone $event->getStart();
            $event->setEnd($newEnd->modify('+1 hour'));
        }

        $event->setSummary($meeting['name']);
        $event->setUid($userId . $meeting['id'] . '@meeting.foodsharing.de');
        $this->setEventDescription($event, $description, $formatting);
        $event->setUrl($url);
        $event->setStatus(['TENTATIVE', 'CONFIRMED', 'TENTATIVE', 'CANCELLED'][$meeting['status']]);

        if ($meeting['street']) {
            $full_address = $meeting['street'] . ', ' . $meeting['zip'] . ' ' . $meeting['city'];
            $location = (new Location())->setName($full_address);
            $event->addLocation($location);
        }
        $this->addReminders($event, $reminders);

        return $event;
    }

    private function setEventDescription(CalendarEvent &$event, string $description, FormattingType $formatting): void
    {
        $description .= $this->updateDateInfo();
        $html = $description;
        if ($formatting !== FormattingType::HTML) {
            $description = str_replace('<br>', '\n', $description);
            $description = strip_tags($description);
        }
        $event->setDescription($description);
        if ($formatting === FormattingType::ALT) {
            $event->setCustomProperties(['X-ALT-DESC;FMTTYPE=text/html' => $html]);
        }
    }

    private function updateDateInfo(): string
    {
        $updated = $this->translator->trans('calendar.export.updated', ['{date}' => date('d.m.Y H:i')]);

        return "<br><br><i>{$updated}</i>";
    }

    /**
     * @param CalendarEvent $event the event to which the reminders shall be added
     * @param int[] $reminders list of reminder intervals in minutes
     */
    private function addReminders(CalendarEvent &$event, array $reminders): void
    {
        foreach ($reminders as $reminder) {
            $alarm = new CalendarAlarm();
            $alarm->setAction('DISPLAY');
            $alarm->setDescription($event->getSummary());
            $alarm->setTrigger(new \DateInterval('PT' . $reminder . 'M'));
            $event->addAlarm($alarm);
        }
    }

    /**
     * Formats a list of events into an iCal calendar string.
     *
     * @param CalendarEvent[] $events
     */
    private function formatCalendarResponse(array $events): string
    {
        $calendar = new Calendar();
        $calendar->setTimezone(new DateTimeZone('Europe/Berlin'));
        $calendar->setProdId('-//Foodsharing//Calendar//DE');

        foreach ($events as $e) {
            $calendar->addEvent($e);
        }

        $calendarExport = new CalendarExport(new CalendarStream(), new Formatter());
        $calendarExport->addCalendar($calendar);

        return $calendarExport->getStream();
    }
}
