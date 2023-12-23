<?php

namespace Foodsharing\Modules\Event;

use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Region\RegionGateway;

class EventGateway extends BaseGateway
{
    private RegionGateway $regionGateway;

    public function __construct(Database $db, RegionGateway $regionGateway)
    {
        parent::__construct($db);
        $this->regionGateway = $regionGateway;
    }

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
				e.start,
				UNIX_TIMESTAMP(e.start) AS start_ts,
				e.end,
				UNIX_TIMESTAMP(e.end) AS end_ts
			FROM
				fs_event e
			WHERE
				e.bezirk_id = :regionId
			ORDER BY
				e.start
		', [':regionId' => $regionId]);
    }

    public function getEvent(int $eventId, bool $withAttendees = false): ?array
    {
        $event = $this->db->fetch('
			SELECT
				e.id,
				fs.id AS fs_id,
				fs.name AS fs_name,
				fs.photo AS fs_photo,
				e.bezirk_id,
				e.location_id,
				e.name,
				e.`start`,
				UNIX_TIMESTAMP(e.start) AS start_ts,
				e.`end`,
				UNIX_TIMESTAMP(e.end) AS end_ts,
				e.description,
				e.bot,
				e.online,
				e.public
			FROM
				fs_event e,
				fs_foodsaver fs
			WHERE
				e.foodsaver_id = fs.id
			AND
				e.id = :eventId
		', [':eventId' => $eventId]);

        if (!$event) {
            return null;
        }

        if ($withAttendees) {
            $event['invites'] = $this->getEventAttendees($eventId);
        }

        if ($event['location_id'] === null) {
            $event['location'] = false;
        } else {
            $event['location'] = $this->getLocation($event['location_id']);
        }

        return $event;
    }

    public function getLocation(int $locationId)
    {
        return $this->db->fetch('
			SELECT id, name, lat, lon, zip, city, street
			FROM   fs_location
			WHERE  id = :locationId
		', [':locationId' => $locationId]);
    }

    public function addLocation(string $locationName, float $lat, float $lon, string $address, string $zip, string $city): int
    {
        return $this->db->insert('fs_location', [
            'name' => strip_tags($locationName),
            'lat' => round($lat, 8),
            'lon' => round($lon, 8),
            'zip' => strip_tags($zip),
            'city' => strip_tags($city),
            'street' => strip_tags($address),
        ]);
    }

    private function getEventAttendees($eventId)
    {
        $invites = $this->db->fetchAll('
			SELECT 	fs.id,
					fs.name,
					fs.photo,
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
            'may' => []
        ];
        foreach ($invites as $i) {
            $out['may'][$i['id']] = true;
            if ($i['status'] == InvitationStatus::ACCEPTED) {
                $out['accepted'][] = $i;
            } elseif ($i['status'] == InvitationStatus::MAYBE) {
                $out['maybe'][] = $i;
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
		JOIN fs_foodsaver_has_bezirk fhb ON e.bezirk_id = fhb.bezirk_id
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

    public function addEvent(int $creatorId, array $event): int
    {
        $extracted_event = [
            'foodsaver_id' => $creatorId,
            'bezirk_id' => $event['bezirk_id'],
            'location_id' => $event['location_id'],
            'name' => $event['name'],
            'start' => $event['start'],
            'end' => $event['end'],
            'description' => $event['description'],
            'bot' => 0,
            'online' => $event['online'],
        ];

        return $this->db->insert('fs_event', $extracted_event);
    }

    public function updateEvent(int $eventId, array $event): bool
    {
        $extracted_event = [
            'bezirk_id' => $event['bezirk_id'],
            'location_id' => $event['location_id'],
            'name' => $event['name'],
            'start' => $event['start'],
            'end' => $event['end'],
            'description' => $event['description'],
            'online' => $event['online'],
        ];

        $this->db->requireExists('fs_event', ['id' => $eventId]);
        $this->db->update('fs_event', $extracted_event, ['id' => $eventId]);

        return true;
    }

    public function deleteInvites(int $eventId): int
    {
        return $this->db->delete('fs_foodsaver_has_event', ['event_id' => $eventId]);
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
        } catch (\Exception $e) {
            $status = -1;
        }

        return (int)$status;
    }

    /**
     * Sets the invitation status for multiple foodsavers.
     *
     * @throws \Exception if the database query fails (which should usually not happen)
     */
    public function setInviteStatus(int $eventId, array $foodsaverIds, int $status): bool
    {
        $parts = array_chunk($foodsaverIds, 100);
        foreach ($parts as $part) {
            $data = [];
            foreach ($part as $userId) {
                $data[] = [
                    'status' => $status,
                    'foodsaver_id' => $userId,
                    'event_id' => $eventId,
                ];
            }
            $this->db->insertOrUpdateMultiple(
                'fs_foodsaver_has_event',
                $data
            );
        }

        return true;
    }

    public function inviteFullRegion(int $regionId, int $eventId, bool $invite_subs = false): void
    {
        $regionIds = [$regionId];
        if ($invite_subs) {
            $regionIds = $this->regionGateway->listIdsForDescendantsAndSelf($regionId);
        }

        $foodsaverIds = $this->db->fetchAllValuesByCriteria(
            'fs_foodsaver_has_bezirk',
            'foodsaver_id',
            ['bezirk_id' => $regionIds, 'active' => 1]
        );
        $invited = $this->db->fetchAllValuesByCriteria(
            'fs_foodsaver_has_event',
            'foodsaver_id',
            ['event_id' => $eventId]
        );

        $this->setInviteStatus($eventId, array_diff($foodsaverIds, $invited), InvitationStatus::INVITED);
    }
}
