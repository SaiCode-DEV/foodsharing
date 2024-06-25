<?php

namespace Foodsharing\Modules\Event;

use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Event\DTO\Event;

class EventGateway extends BaseGateway
{
    /**
     * Gets the current and upcoming events of a specified region and returns them as array.
     *
     * @param int $regionId The identifier of the region
     */
    public function listForRegion(int $regionId): array
    {
        return $this->db->fetchAll('
			SELECT
				e.id,
				e.name,
				e.start as startDate,
				e.end as endDate
			FROM
				fs_event e
			WHERE
				e.bezirk_id = :regionId
			ORDER BY
				e.start
		', [':regionId' => $regionId]);
    }

    public function getEvent(int $eventId): ?Event
    {
        $event = $this->db->fetch('
			SELECT
				e.id, e.foodsaver_id, e.bezirk_id, e.name, e.description, e.online, e.`start`, e.`end`,
                l.name as location_details, l.lat, l.lon, l.zip, l.city, l.street
			FROM fs_event e
            LEFT OUTER JOIN fs_location l ON e.location_id = l.id
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
        ];
        foreach ($invites as $invite) {
            if ($invite['status'] == InvitationStatus::ACCEPTED) {
                $out['accepted'][] = $invite;
            } elseif ($invite['status'] == InvitationStatus::MAYBE) {
                $out['maybe'][] = $invite;
            }
        }

        return $out;
    }

    /**
     * Returns all future events with specific statuses from a foodsavers regions.
     *
     * @param int $userId The id of the user
     * @param array $statuses Array of InvitationStatus. Statuses to be included in the result
     *
     * @return array all events matching the invitation status
     */
    public function getEventsByStatus(int $userId, array $statuses): array
    {
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
			CAST(IFNULL(fhe.status, ' . InvitationStatus::INVITED . ') AS INTEGER) AS status,
			l.street,
			l.zip,
			l.city
		FROM fs_event e
		JOIN fs_foodsaver_has_bezirk fhb ON e.bezirk_id = fhb.bezirk_id AND fhb.active = 1
        LEFT OUTER JOIN fs_foodsaver_has_event fhe ON e.id = fhe.event_id AND fhe.foodsaver_id = fhb.foodsaver_id
		LEFT JOIN fs_location l ON e.location_id = l.id
		LEFT JOIN fs_bezirk r ON e.bezirk_id = r.id
		WHERE
			fhb.foodsaver_id = :fs_id
			AND e.end > NOW()
			AND IFNULL(fhe.status, ' . InvitationStatus::INVITED . ') IN (' . implode(',', $statuses) . ')
		ORDER BY e.start
		', ['fs_id' => $userId]);
    }

    public function addLocation(Event $event): int
    {
        return $this->db->insert('fs_location', [
            'name' => $event->locationDetails,
            'lat' => isset($event->location) ? round($event->location->lat, 8) : null,
            'lon' => isset($event->location) ? round($event->location->lon, 8) : null,
            'zip' => isset($event->address) ? $event->address->zipCode : null,
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

    public function setInviteStatus(int $eventId, int $foodsaverId, int $status): int
    {
        if ($status === InvitationStatus::INVITED) {
            return $this->db->delete('fs_foodsaver_has_event', [
                'event_id' => $eventId,
                'foodsaver_id' => $foodsaverId,
            ]);
        }

        return $this->db->insertOrUpdate('fs_foodsaver_has_event', [
            'event_id' => $eventId,
            'foodsaver_id' => $foodsaverId,
            'status' => $status,
        ]);
    }
}
