<?php

namespace Foodsharing\Modules\Bell;

use Carbon\Carbon;
use Foodsharing\Lib\WebSocketConnection;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Bell\DTO\BellForExpirationUpdates;
use Foodsharing\Modules\Bell\DTO\BellForList;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\Pagination;

class BellGateway extends BaseGateway
{
    private readonly WebSocketConnection $webSocketConnection;

    public function __construct(Database $db, WebSocketConnection $webSocketConnection)
    {
        parent::__construct($db);

        $this->webSocketConnection = $webSocketConnection;
    }

    /**
     * Creates a new bell in the database.
     *
     * @param Bell $bellData Bell notification data
     * @return int Identifer of the created bell
     */
    private function insertBell(Bell $bellData): int
    {
        return $this->db->insert(
            'fs_bell',
            [
                'name' => $bellData->title,
                'body' => $bellData->body,
                'vars' => $bellData->vars ? serialize($bellData->vars) : null,
                'attr' => $bellData->link_attributes ? serialize($bellData->link_attributes) : null,
                'icon' => $bellData->icon,
                'identifier' => $bellData->identifier,
                'time' => $bellData->time ? $bellData->time->format('Y-m-d H:i:s') : (new \DateTime())->format('Y-m-d H:i:s'),
                'closeable' => $bellData->closeable,
                'expiration' => $bellData->expiration ? $bellData->expiration->format('Y-m-d H:i:s') : null
            ]
        );
    }

    /**
     * Stores the bell to the related users bell read list.
     *
     * @param int[] $userIds List of user ids to inform
     * @param int $bellId Identifier of bell which the user be informed about
     */
    private function distributeBellToUsers(array $userIds, int $bellId): void
    {
        // add the bell for all foodsavers (100 per query)
        $parts = array_chunk($userIds, 100);
        foreach ($parts as $part) {
            $data = array_map(fn ($userId) => [
                'foodsaver_id' => $userId,
                'bell_id' => $bellId,
                'seen' => 0,
            ], $part);

            $this->db->insertMultiple('fs_foodsaver_has_bell', $data, ['ignore' => true]);
        }
    }

    /**
     * Inserts a bell and distributes it to related users as non-read new bell.
     * The insert will also inform the clients via WebSocket.
     *
     * @param int[] $userIds List of user ids to inform
     * @param Bell $bellData Bell notification data
     */
    public function addBellForUsers(array $userIds, Bell $bellData): void
    {
        $userIds = array_unique($userIds, SORT_NUMERIC);
        $bellId = $this->insertBell($bellData);
        $this->distributeBellToUsers($userIds, $bellId);
        $this->updateMultipleFoodsaverClients($userIds);
    }

    /**
     * @deprecated please use typed method `addBellForUsers()` instead
     */
    public function addBell($foodsavers, Bell $bellData): void
    {
        if (!is_array($foodsavers)) {
            $foodsavers = [$foodsavers];
        }
        $userIds = array_map(fn ($fs) => is_array($fs) ? $fs['id'] : $fs, $foodsavers);
        $this->addBellForUsers($userIds, $bellData);
    }

    /**
     * @param array $data - the data to be updated. $data['var'] and data['attr'] must not be serialized.
     */
    public function updateBell(int $bellId, array $data, bool $setUnseen = false, bool $updateClients = true): void
    {
        if (isset($data['attr'])) {
            $data['attr'] = serialize($data['attr']);
        }

        if (isset($data['vars'])) {
            $data['vars'] = serialize($data['vars']);
        }

        if (isset($data['time']) && is_a($data['time'], \DateTime::class)) {
            $data['time'] = $data['time']->format('Y-m-d H:i:s');
        }

        if (isset($data['expiration']) && is_a($data['expiration'], \DateTime::class)) {
            $data['expiration'] = $data['expiration']->format('Y-m-d H:i:s');
        }

        $this->db->update('fs_bell', $data, ['id' => $bellId]);

        $foodsaverIds = $this->db->fetchAllValuesByCriteria('fs_foodsaver_has_bell', 'foodsaver_id', ['bell_id' => $bellId]);

        if ($setUnseen && !empty($foodsaverIds)) {
            $this->db->update('fs_foodsaver_has_bell', ['seen' => 0], ['foodsaver_id' => $foodsaverIds, 'bell_id' => $bellId]);
        }

        if ($updateClients) {
            $this->updateMultipleFoodsaverClients($foodsaverIds);
        }
    }

    /**
     * Method returns an array of all bells a user sees.
     *
     * @return BellForList[]
     */
    public function listBells(int $fsId, ?Pagination $pagination = null)
    {
        $stm = 'SELECT
				b.`id`,
				b.`name`,
				b.`body`,
				b.`vars`,
				b.`attr`,
				b.`icon`,
				b.`time`,
				hb.seen,
				b.closeable
			FROM
				fs_bell b,
				`fs_foodsaver_has_bell` hb
			WHERE
				hb.bell_id = b.id
			    AND hb.foodsaver_id = :foodsaver_id
			ORDER BY
                hb.seen ASC,
                b.`time` DESC
			' . $this->buildPaginationSqlLimit($pagination);
        $params = $this->addPaginationSqlLimitParameters($pagination, ['foodsaver_id' => $fsId]);
        $rows = $this->db->fetchAll($stm, $params);

        if (!$rows) {
            return [];
        }

        return $this->createBellsForListFromDatabaseRows($rows);
    }

    /**
     * @param string $identifier - can contain SQL wildcards
     *
     * @return int - id of the bell
     */
    public function getOneByIdentifier(string $identifier): int
    {
        return $this->db->fetchValueByCriteria('fs_bell', 'id', ['identifier like' => $identifier]);
    }

    /**
     * @param string $identifier - can contain SQL wildcards
     *
     * @return BellForExpirationUpdates[]
     */
    public function getExpiredByIdentifier(string $identifier): array
    {
        $bells = $this->db->fetchAll('
            SELECT
                `id`,
				`identifier`
            FROM `fs_bell`
            WHERE `identifier` LIKE :identifier
            AND `expiration` < NOW()',
            [':identifier' => $identifier]
        );

        return $this->createBellsForExpirationUpdatesFromDatabaseRows($bells);
    }

    public function bellWithIdentifierExists(string $identifier): bool
    {
        return $this->db->exists('fs_bell', ['identifier' => $identifier]);
    }

    /**
     * Deletes the bell with the given ID for a specific foodsaver. Returns whether the bell was succesfully
     * deleted.
     */
    public function delBellsForFoodsaver(array $bellIds, int $fsId): int
    {
        $deleted = $this->db->execute('DELETE hb
            FROM fs_foodsaver_has_bell hb
            JOIN fs_bell b ON b.id = hb.bell_id
			WHERE hb.foodsaver_id = ?
                AND b.closeable
                AND b.id IN (' . $this->db->generatePlaceholders(count($bellIds)) . ')
            ', [$fsId, ...$bellIds])->rowCount();
        $this->updateFoodsaverClient($fsId);

        return $deleted;
    }

    public function deleteBellForFoodsavers(int $bellId, array $foodsaverIds): void
    {
        // add the bell for all foodsavers (100 per query)
        $parts = array_chunk($foodsaverIds, 100);
        foreach ($parts as $part) {
            $this->db->delete('fs_foodsaver_has_bell', [
                'foodsaver_id' => $part,
                'bell_id' => $bellId,
            ]);
        }

        $this->updateMultipleFoodsaverClients($foodsaverIds);
    }

    public function deleteBellsForFoodsaversByIdentifier(array $foodsaverIds, string $identifier, bool $seenOnly = false): void
    {
        // add the bell for all foodsavers (100 per query)
        $parts = array_chunk($foodsaverIds, 100);
        $seenClause = $seenOnly ? 'AND foodsaver_has_bell.seen = 1' : '';
        foreach ($parts as $part) {
            $this->db->execute("DELETE foodsaver_has_bell
                FROM fs_foodsaver_has_bell foodsaver_has_bell
                JOIN fs_bell bell ON bell.id = foodsaver_has_bell.bell_id
                WHERE foodsaver_has_bell.foodsaver_id IN ({$this->db->generatePlaceholders(count($part))})
                {$seenClause}
                AND bell.identifier LIKE ?",
                [...$part, $identifier]);
        }
    }

    public function delBellsByIdentifier(string $identifier): void
    {
        $foodsaverIds = $this->db->fetchAllValues(
            'SELECT DISTINCT `foodsaver_id`
			FROM `fs_foodsaver_has_bell` JOIN `fs_bell`
			ON `fs_foodsaver_has_bell`.bell_id = `fs_bell`.id
			WHERE `identifier` = :identifier',
            [':identifier' => $identifier]
        );

        $this->db->delete('fs_bell', ['identifier' => $identifier]);

        $this->updateMultipleFoodsaverClients($foodsaverIds);
    }

    /**
     * Marks the bells specified by a list of IDs and the owner's ID as read/unread. Returns the number of
     * bells that were successfully changed.
     */
    public function setReadStatus(array $bellIds, int $foodsaverId, int $isRead): int
    {
        return $this->db->update('fs_foodsaver_has_bell',
            ['seen' => $isRead],
            [
                'bell_id' => array_map('intval', $bellIds),
                'foodsaver_id' => $foodsaverId
            ]
        );
    }

    public function doesFoodsaverHaveBells(array $bellIds, int $foodsaverId): bool
    {
        return $this->db->count('fs_foodsaver_has_bell', [
            'bell_id' => array_map('intval', $bellIds),
            'foodsaver_id' => $foodsaverId
        ]) === count($bellIds);
    }

    private function updateFoodsaverClient(int $foodsaverId): void
    {
        $this->webSocketConnection->sendSock($foodsaverId, 'bell', 'update', []);
    }

    /**
     * @param int[] $foodsaverIds
     */
    private function updateMultipleFoodsaverClients(array $foodsaverIds): void
    {
        $this->webSocketConnection->sendSockMulti($foodsaverIds, 'bell', 'update', []);
    }

    /**
     * @param array $databaseRows - 2D-array with bell data, expects indexes []['vars'] and []['attr'] to contain serialized data
     *
     * @return BellForList[] - BellData objects with with unserialized $ball->vars and $bell->attr
     */
    private function createBellsForListFromDatabaseRows(array $databaseRows): array
    {
        $output = [];
        foreach ($databaseRows as $row) {
            $bellDTO = new BellForList();

            // This onclick-to-href conversion is probably not needed anymore
            if (isset($row['attr']['onclick'])) {
                preg_match('/profile\((.*?)\)/', (string)$row['attr']['onclick'], $matches);
                if ($matches) {
                    $row['attr']['href'] = '/profile/' . $matches[1];
                }
            }

            $bellDTO->id = $row['id'];
            $bellDTO->key = $row['body'];
            $bellDTO->title = $row['name'];
            $bellDTO->payload = unserialize($row['vars'], ['allowed_classes' => false]) ?: [];
            $bellDTO->href = unserialize($row['attr'], ['allowed_classes' => false])['href'];
            $bellDTO->icon = $this->isIconCssIdentifier($row['icon']) ? $row['icon'] : null;
            $bellDTO->image = $this->isImagePath($row['icon']) ? $row['icon'] : null;
            $bellDTO->createdAt = new Carbon($row['time']);
            $bellDTO->isRead = $row['seen'];
            $bellDTO->isCloseable = $row['closeable'];

            $output[] = $bellDTO;
        }

        return $output;
    }

    private function containsPath(?string $path): bool
    {
        if ($path == null) {
            return false;
        }

        return strlen($path) !== 0;
    }

    private function isIconCssIdentifier(?string $path): bool
    {
        return $this->containsPath($path) && $path[0] !== '/';
    }

    private function isImagePath(?string $path): bool
    {
        return $this->containsPath($path) && $path[0] === '/';
    }

    /**
     * @param array $databaseRows - 2D-array with bell data, expects indexes []['vars'] and []['attr'] to contain serialized data
     *
     * @return BellForExpirationUpdates[] - BellData objects with with unserialized $ball->vars and $bell->attr
     */
    private function createBellsForExpirationUpdatesFromDatabaseRows(array $databaseRows): array
    {
        $output = [];
        foreach ($databaseRows as $row) {
            $bellDTO = new BellForExpirationUpdates();

            $bellDTO->id = $row['id'];
            $bellDTO->identifier = $row['identifier'];

            $output[] = $bellDTO;
        }

        return $output;
    }

    public function groupFoodsaversByUnreadBell(array $foodsaverIds, string $searchBellIdentifier, bool $selectNewest): array
    {
        // Fetch data for groups of 100 foodsavers:
        $idChucks = array_chunk($foodsaverIds, 100);
        $groupedLists = [];
        $order = $selectNewest ? 'DESC' : 'ASC';
        foreach ($idChucks as $idChuck) {
            $idChuck = implode(',', $idChuck);
            $groupedLists[] = $this->db->fetchAll("SELECT
                    bell.id AS bellId,
                    bell.attr,
                    bell.vars,
                    GROUP_CONCAT(foodsaver_has_bell.foodsaver_id) as foodsaverIds
                FROM fs_foodsaver_has_bell foodsaver_has_bell
                JOIN fs_bell bell ON bell.id = foodsaver_has_bell.bell_id
                WHERE bell.identifier LIKE '{$searchBellIdentifier}%'
                AND foodsaver_has_bell.seen = 0
                AND foodsaver_has_bell.foodsaver_id IN ({$idChuck})
                GROUP BY bell.id
                ORDER BY bell.time {$order}");
        }

        // Merge groups and format data
        $bells = [];
        foreach ($groupedLists as &$groupedList) {
            foreach ($groupedList as &$group) {
                $bell = &$bells[$group['bellId']];
                $foundFoodsaverIds = array_map('intval', explode(',', (string)$group['foodsaverIds']));

                // Make sure foodsavers end up in only one group:
                $group['foodsaverIds'] = array_values(array_intersect($foundFoodsaverIds, $foodsaverIds));
                $foodsaverIds = array_diff($foodsaverIds, $foundFoodsaverIds);

                if ($bell) {
                    $bell['foodsaverIds'] = array_merge($bell['foodsaverIds'], $group['foodsaverIds']);
                } else {
                    $bells[$group['bellId']] = $group;
                }
            }
        }

        $unfoundUsers = array_values(array_diff($foodsaverIds, ...array_column($bells, 'foodsaverIds')));
        if (!empty($unfoundUsers)) {
            $bells[] = [
                'bellId' => null,
                'foodsaverIds' => $unfoundUsers,
            ];
        }

        return $bells;
    }
}
