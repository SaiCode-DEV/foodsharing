<?php

namespace Foodsharing\Modules\Event;

use Carbon\Carbon;
use DateTime;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Event\DTO\Event;
use Foodsharing\Modules\Event\DTO\EventForListView;

class EventGateway extends BaseGateway
{
    /**
     * Gets the current and upcoming events of a specified region and returns them as array.
     *
     * @param int $regionId The identifier of the region
     * @param bool $onlyPublic whether only public events should be included
     * @return EventForListView[]
     */
    public function listForRegion(int $regionId, bool $onlyPublic = false): array
    {
        $publicRestriction = $onlyPublic ? 'AND e.is_public = 1' : '';
        $events = $this->db->fetchAll('SELECT
				e.id, e.name, e.start, e.end
			FROM fs_event e
			WHERE e.bezirk_id = :regionId
            ' . $publicRestriction . '
			ORDER BY e.start
		', [':regionId' => $regionId]);

        return array_map(fn ($e) => EventForListView::create(
            $e['id'],
            $e['name'],
            new Carbon($e['start']),
            new Carbon($e['end']),
        ), $events);
    }

    public function getEvent(int $eventId): ?Event
    {
        $event = $this->db->fetch('
			SELECT
				e.id, e.foodsaver_id, e.bezirk_id, e.name, e.description, e.online, e.`start`, e.`end`, e.`is_public`,
                l.name as location_details, l.lat, l.lon, l.zip as postalCode, l.city, l.street,
                b.`name` AS region_name
			FROM fs_event e
            LEFT OUTER JOIN fs_location l ON e.location_id = l.id
            INNER JOIN fs_bezirk b ON b.id = e.bezirk_id
			WHERE e.id = :eventId
		', [':eventId' => $eventId]);

        return $event ? Event::createFromArray($event) : null;
    }

    public function getEventAuthor(int $eventId): int
    {
        return $this->db->fetchValueById('fs_event', 'foodsaver_id', $eventId);
    }

    public function getEventAttendees($eventId): array
    {
        $invites = $this->db->fetchAll('
			SELECT 	fs.id,
					fs.name,
					fs.photo as avatar,
					fhe.status
			FROM
				`fs_foodsaver_has_event` fhe,
				`fs_foodsaver` fs

			WHERE
				fhe.foodsaver_id = fs.id

			AND
				fhe.event_id = :eventId
                AND fhe.status != :invitedStatus
		', [':eventId' => $eventId, ':invitedStatus' => InvitationStatus::INVITED]);

        $out = [
            'accepted' => [],
            'maybe' => [],
            'declined' => 0,
        ];
        foreach ($invites as $invite) {
            if ($invite['status'] == InvitationStatus::ACCEPTED->value) {
                $out['accepted'][] = $invite;
            } elseif ($invite['status'] == InvitationStatus::MAYBE->value) {
                $out['maybe'][] = $invite;
            } elseif ($invite['status'] == InvitationStatus::WONT_JOIN->value) {
                ++$out['declined'];
            }
        }

        return $out;
    }

    /**
     * Returns all future events with specific statuses from a foodsavers regions.
     *
     * @param int $userId The id of the user
     * @param InvitationStatus[] $statuses Array of InvitationStatus. Statuses to be included in the result
     * @param int $pastEventsBufferInDays Number of days in the past to include events
     * @param DateTime|null $date_only If set, only events on this date will be included
     *
     * @return array all events matching the invitation status
     */
    public function getEventsByStatus(int $userId, array $statuses, int $pastEventsBufferInDays = 0, ?DateTime $date_only = null): array
    {
        if (count($statuses) === 0) {
            return [];
        }
        $statuses = array_map(fn ($status) => $status->value, $statuses);

        $dateFilter = '';
        $params = [
            'fs_id' => $userId,
            'buffer' => $pastEventsBufferInDays,
            'status_invited' => InvitationStatus::INVITED->value,
        ];
        if ($date_only !== null) {
            // Include events that start, end, or span the $date_only day
            $dateFilter = 'AND DATE(e.start) <= :date_only AND DATE(e.end) >= :date_only';
            $params['date_only'] = $date_only->format('Y-m-d');
        }

        return $this->db->fetchAll('SELECT
			e.id,
			e.name,
			e.description,
			e.start,
			e.end,
			e.bezirk_id AS region_id,
			r.name AS regionName,
			UNIX_TIMESTAMP(e.start) AS start_ts,
			UNIX_TIMESTAMP(e.end) AS end_ts,
			CAST(IFNULL(fhe.status, :status_invited) AS INTEGER) AS status,
			l.street,
			l.zip,
			l.city
		FROM fs_event e
		LEFT OUTER JOIN fs_foodsaver_has_event fhe ON e.id = fhe.event_id AND fhe.foodsaver_id = :fs_id
		LEFT JOIN fs_location l ON e.location_id = l.id
		LEFT JOIN fs_bezirk r ON e.bezirk_id = r.id
		WHERE
			(
                EXISTS (
                    SELECT 1
                    FROM fs_foodsaver_has_bezirk fhb
                    WHERE fhb.bezirk_id = e.bezirk_id
                        AND fhb.foodsaver_id = :fs_id
                        AND fhb.active = 1
                )
				OR (e.is_public AND fhe.event_id IS NOT NULL)
			)
			AND e.end > DATE_SUB(NOW(), INTERVAL :buffer DAY)
			AND IFNULL(fhe.status, :status_invited) IN (' . implode(',', $statuses) . ')
			' . $dateFilter . '
		ORDER BY e.start
		', $params);
    }

    public function addLocation(Event $event): int
    {
        return $this->db->insert('fs_location', [
            'name' => $event->locationDetails,
            'lat' => isset($event->location) ? round($event->location->lat, 8) : null,
            'lon' => isset($event->location) ? round($event->location->lon, 8) : null,
            'zip' => isset($event->address) ? $event->address->postalCode : null,
            'city' => isset($event->address) ? $event->address->city : null,
            'street' => isset($event->address) ? $event->address->street : null,
        ]);
    }

    public function deleteCurrentLocation(Event $event): void
    {
        $locationId = $this->db->fetchValueById('fs_event', 'location_id', $event->id);
        $this->db->update('fs_event', ['location_id' => null], ['id' => $event->id]);
        $this->db->delete('fs_location', ['id' => $locationId]);
    }

    public function addEvent(int $creatorId, Event $event, ?int $locationId): int
    {
        return $this->db->insert('fs_event', [
            'foodsaver_id' => $creatorId,
            'bezirk_id' => $event->regionId,
            'location_id' => $locationId,
            'name' => $event->name,
            'start' => date('Y-m-d H:i:s', $event->startDate->getTimestamp()),
            'end' => date('Y-m-d H:i:s', $event->endDate->getTimestamp()),
            'description' => $event->description,
            'bot' => 0, // deprecated, remove column!
            'online' => $event->type->value,
            'is_public' => $event->isPublic,
        ]);
    }

    public function updateEvent(Event $event, ?int $locationId): void
    {
        $this->db->update('fs_event', [
            'bezirk_id' => $event->regionId,
            'location_id' => $locationId,
            'name' => $event->name,
            'start' => date('Y-m-d H:i:s', $event->startDate->getTimestamp()),
            'end' => date('Y-m-d H:i:s', $event->endDate->getTimestamp()),
            'description' => $event->description,
            'online' => $event->type->value,
            'is_public' => $event->isPublic,
        ], ['id' => $event->id]);
    }

    public function deleteInvitesForFoodSaver(int $regionId, int $foodsaverId): int
    {
        $eventIds = $this->db->fetchAllValuesByCriteria('fs_event', 'id', ['foodsaver_id' => $foodsaverId, 'bezirk_id' => $regionId]);

        return $this->db->delete('fs_foodsaver_has_event', ['foodsaver_id' => $foodsaverId, 'event_id' => $eventIds]);
    }

    public function getInviteStatus(int $eventId, int $foodsaverId): int
    {
        try {
            $status = $this->db->fetchValueByCriteria(
                'fs_foodsaver_has_event',
                'status',
                ['event_id' => $eventId, 'foodsaver_id' => $foodsaverId]
            );
        } catch (\Exception) {
            $status = 0;
        }

        return (int)$status;
    }

    public function setInviteStatus(int $eventId, int $foodsaverId, InvitationStatus $status): int
    {
        return $this->db->insertOrUpdate('fs_foodsaver_has_event', [
            'event_id' => $eventId,
            'foodsaver_id' => $foodsaverId,
            'status' => $status->value,
        ]);
    }
}
