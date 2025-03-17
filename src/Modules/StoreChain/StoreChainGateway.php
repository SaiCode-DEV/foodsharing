<?php

namespace Foodsharing\Modules\StoreChain;

use Exception;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Pagination;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\StoreChain\DTO\StoreChainData;
use Foodsharing\Modules\StoreChain\DTO\StoreChainForChainList;

class StoreChainGateway extends BaseGateway
{
    /**
     * @throws Exception
     */
    public function addStoreChain(StoreChainData $storeChainData): int
    {
        $id = $this->db->insert('fs_chain', [
            'name' => $storeChainData->name,
            'headquarters_zip' => $storeChainData->headquartersZip,
            'headquarters_city' => $storeChainData->headquartersCity,
            'headquarters_country' => $storeChainData->headquartersCountry,
            'status' => StoreChainStatus::from($storeChainData->status)->value,
            'modification_date' => $this->db->now(),
            'allow_press' => $storeChainData->allowPress,
            'forum_thread' => $storeChainData->forumThread,
            'notes' => $storeChainData->notes,
            'common_store_information' => $storeChainData->commonStoreInformation,
            'estimated_store_count' => $storeChainData->estimatedStoreCount
        ]);
        $this->updateAllKeyAccountManagers($id, $storeChainData->kams);

        return $id;
    }

    /**
     * @throws Exception
     */
    public function updateStoreChain(int $chainId, StoreChainData $storeData)
    {
        $this->db->update(
            'fs_chain',
            [
                'name' => $storeData->name,
                'headquarters_zip' => $storeData->headquartersZip,
                'headquarters_city' => $storeData->headquartersCity,
                'headquarters_country' => $storeData->headquartersCountry,
                'status' => $storeData->status,
                'modification_date' => $this->db->now(),
                'allow_press' => $storeData->allowPress,
                'forum_thread' => $storeData->forumThread,
                'notes' => $storeData->notes,
                'common_store_information' => $storeData->commonStoreInformation,
                'estimated_store_count' => $storeData->estimatedStoreCount
            ],
            ['id' => $chainId]
        );
    }

    /**
     * Delete and insert all key account managers (kams).
     *
     * @throws Exception
     */
    public function updateAllKeyAccountManagers(int $chainId, array $kamIds)
    {
        //delete previous kams
        $this->db->delete('fs_key_account_manager', ['chain_id' => $chainId]);

        //add new kams
        $this->db->insertMultiple('fs_key_account_manager',
            array_map(fn ($kamId) => [
                'chain_id' => $chainId,
                'foodsaver_id' => $kamId,
            ], $kamIds));
    }

    /**
     * Check is user a key account manager for chain.
     *
     * @throws Exception
     */
    public function isUserKeyAccountManager(int $chainId, int $fs_id): bool
    {
        return $this->db->exists('fs_key_account_manager', ['foodsaver_id' => $fs_id, 'chain_id' => $chainId]);
    }

    /**
     * @return StoreChainForChainList[]
     *
     * @throws Exception
     */
    public function getStoreChains(?int $id = null, Pagination $pagination = new Pagination()): array
    {
        $where = '';
        if (!is_null($id)) {
            $where = 'WHERE c.`id` = :chainId';
        }

        $data = $this->db->fetchAll('SELECT
				c.*,
				COUNT(s.`id`) AS stores,
                ht.`bezirk_id` AS forum_region_id
			FROM `fs_chain` c
			LEFT OUTER JOIN `fs_betrieb` s ON s.`kette_id` = c.`id`
            LEFT OUTER JOIN `fs_bezirk_has_theme` ht ON ht.`theme_id` = c.`forum_thread`
			' . $where . '
			GROUP BY c.`id`
            ORDER BY c.id
		' . $this->buildPaginationSqlLimit($pagination),
            $this->addPaginationSqlLimitParameters($pagination, !is_null($id) ? ['chainId' => $id] : []));

        $chains = [];
        foreach ($data as $chain) {
            $chain['kams'] = $this->getStoreChainKeyAccountManagers($chain['id']);
            $chains[] = StoreChainForChainList::createFromArray($chain);
        }

        return $chains;
    }

    /**
     * @return Profile[]
     */
    public function getStoreChainKeyAccountManagers(int $chainId): array
    {
        $kams = $this->db->fetchAll(
            'SELECT
				k.*, f.name AS foodsaver_name, f.photo AS foodsaver_photo, f.is_sleeping AS foodsaver_is_sleeping
			FROM
				fs_key_account_manager k
			JOIN fs_foodsaver f ON f.id = k.foodsaver_id
			WHERE k.chain_id = :chainId AND f.deleted_at is NULL',
            ['chainId' => $chainId]
        );

        return array_map(fn ($kam) => new Profile($kam, 'foodsaver_'), $kams);
    }

    /**
     * @throws Exception
     */
    public function chainExists($chainId): bool
    {
        return $this->db->exists('fs_chain', ['id' => $chainId]);
    }

    /**
     * Returns the chain's description that is visible on the store page, or null if the chain does not exist.
     */
    public function getCommonStoreInformation(int $chainId): ?string
    {
        try {
            return $this->db->fetchValueByCriteria('fs_chain', 'common_store_information', ['id' => $chainId]);
        } catch (Exception) {
            return null;
        }
    }

    public function getStoreChainsManagedByFoodsaver(int $foodsaverId): array
    {
        return $this->db->fetchAll('SELECT
                c.id, c.name
            FROM fs_chain c
            JOIN fs_key_account_manager kam ON kam.chain_id = c.id
            WHERE kam.foodsaver_id = ?',
            [$foodsaverId]
        );
    }
}
