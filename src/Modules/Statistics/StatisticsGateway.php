<?php

namespace Foodsharing\Modules\Statistics;

use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Statistics\DTO\ActivityStatisticItem;
use Foodsharing\Modules\Statistics\DTO\PickupItem;
use Foodsharing\Modules\Statistics\DTO\StatisticsAgeBand;
use Foodsharing\Modules\Statistics\DTO\StatisticsGender;
use Foodsharing\Modules\Statistics\DTO\TotalStatisticItem;

class StatisticsGateway extends BaseGateway
{
    public function listTotalStat(): TotalStatisticItem
    {
        $stm = '
			SELECT
				SUM(`stat_fetchweight`) AS fetchWeight,
				SUM(`stat_fetchcount`) AS fetchCount,
				SUM(`stat_korpcount`) AS cooperationsCount,
				SUM(`stat_botcount`) AS botCount,
				SUM(`stat_fscount`) AS foodsaverCount,
				SUM(`stat_fairteilercount`) AS fairteilerCount
			FROM
				fs_bezirk
			WHERE
				`id` = :region_id
		';

        $result = $this->db->fetch($stm, [':region_id' => RegionIDs::EUROPE]);

        return TotalStatisticItem::create(
            (float)$result['fetchWeight'],
            (int)$result['fetchCount'],
            (int)$result['cooperationsCount'],
            (int)$result['botCount'],
            (int)$result['foodsaverCount'],
            (int)$result['fairteilerCount']
        );
    }

    /**
     * @return array<ActivityStatisticItem>
     */
    public function listStatRegions(): array
    {
        $stm = '
			SELECT
				`name`,
				`stat_fetchweight` AS fetchWeight,
				`stat_fetchcount` AS fetchCount,
				`type`
			FROM
				fs_bezirk
			WHERE
				`type` IN(:city, :bigCity)
			ORDER BY fetchCount DESC
			LIMIT 30
		';

        $result = $this->db->fetchAll($stm, [':city' => UnitType::CITY, ':bigCity' => UnitType::BIG_CITY]);

        return array_map(fn ($regionsStats) => ActivityStatisticItem::create(
            $regionsStats['name'],
            floatval($regionsStats['fetchWeight']),
            intval($regionsStats['fetchCount'])
        ),
            $result);
    }

    /**
     * @return array<ActivityStatisticItem>
     */
    public function listStatFoodsaver(): array
    {
        $stm = '
			SELECT
				`id`,
				`name`,
				`stat_fetchweight` AS fetchWeight,
				`stat_fetchcount` AS fetchCount
			FROM
				fs_foodsaver
			WHERE
				deleted_at IS NULL
			ORDER BY fetchCount DESC
			LIMIT 10
		';

        $result = $this->db->fetchAll($stm);

        return array_map(fn ($foodsaverStats) => ActivityStatisticItem::create(
            $foodsaverStats['name'],
            $foodsaverStats['fetchWeight'],
            $foodsaverStats['fetchCount']
        ),
            $result);
    }

    public function countAllFoodsharers(): int
    {
        return $this->db->count('fs_foodsaver', ['active' => 1, 'deleted_at' => null]);
    }

    public function avgDailyFetchCount(): int
    {
        // get number of all fetches within time range
        $q = '
			SELECT
				COUNT(*) as fetchCount
			FROM
				fs_abholer
			WHERE
				CAST(`date` as date) > DATE_ADD(CURDATE(), INTERVAL -100 DAY) AND
				CAST(`date` as date) < CURDATE()
		';
        $fetchCount = (int)$this->db->fetch($q)['fetchCount'];
        // time range to average over in days
        $diffDays = 99;

        // divide number of fetches by time difference
        return (int)($fetchCount / $diffDays);
    }

    /**
     * @return array<PickupItem>
     */
    public function getFoodsaverStatsCurrentMonth(): array
    {
        $query = <<<SQL
            SELECT
                foodsaver.`name`,
                COUNT(abholer.`id`) as fetchCount
            FROM
                fs_foodsaver AS foodsaver
            RIGHT JOIN fs_abholer AS abholer ON (abholer.`foodsaver_id` = foodsaver.`id`)
            WHERE
                foodsaver.`deleted_at` IS NULL
            AND MONTH(abholer.`date`) = MONTH(CURDATE())
            AND YEAR(abholer.`date`) = YEAR(CURDATE())

            GROUP BY foodsaver.`id`
            ORDER BY fetchCount DESC
            LIMIT 10
        SQL;

        $result = $this->db->fetchAll($query);

        return array_map(fn ($foodsaverStats) => PickupItem::create(
            $foodsaverStats['name'],
            intval($foodsaverStats['fetchCount'])
        ), $result);
    }

    /**
     * @return array<PickupItem>
     */
    public function getFoodsaverStatsNumberMonths(int $numberMonths): array
    {
        $query = <<<SQL
            SELECT
                foodsaver.`name`,
                COUNT(abholer.`id`) as fetchCount
            FROM
                fs_foodsaver AS foodsaver
            RIGHT JOIN fs_abholer AS abholer ON (abholer.`foodsaver_id` = foodsaver.`id`)
            WHERE
                foodsaver.`deleted_at` IS NULL
            AND abholer.`date` > DATE_ADD(CURDATE(), INTERVAL -$numberMonths MONTH)

            GROUP BY foodsaver.`id`
            ORDER BY fetchCount DESC
            LIMIT 10
        SQL;

        $result = $this->db->fetchAll($query);

        return array_map(fn ($foodsaverStats) => PickupItem::create(
            $foodsaverStats['name'],
            intval($foodsaverStats['fetchCount'])
        ), $result);
    }

    /**
     * @return array<PickupItem>
     */
    public function getRegionStatsCurrentMonth(): array
    {
        $query = <<<SQL
            SELECT
                bezirk.`name`,
                COUNT(abholer.`id`) as fetchCount
            FROM
                fs_bezirk AS bezirk
            INNER JOIN fs_foodsaver AS foodsaver ON (bezirk.`id` = foodsaver.`bezirk_id`)
            RIGHT JOIN fs_abholer AS abholer ON (abholer.`foodsaver_id` = foodsaver.`id`)
            WHERE
                    MONTH(abholer.`date`) = MONTH(CURDATE())
                AND
                    YEAR(abholer.`date`) = YEAR(CURDATE())
            GROUP BY bezirk.`id`
            ORDER BY fetchCount DESC
            LIMIT 10
        SQL;

        $result = $this->db->fetchAll($query);

        return array_map(fn ($regionStats) => PickupItem::create(
            $regionStats['name'],
            intval($regionStats['fetchCount'])
        ), $result);
    }

    /**
     * @return array<PickupItem>
     */
    public function getRegionStatsNumberMonth(int $numberMonths): array
    {
        $query = <<<SQL
            SELECT
                bezirk.`name`,
                COUNT(abholer.`id`) as fetchCount
            FROM
                fs_bezirk AS bezirk
            INNER JOIN fs_foodsaver AS foodsaver ON (bezirk.`id` = foodsaver.`bezirk_id`)
            RIGHT JOIN fs_abholer AS abholer ON (abholer.`foodsaver_id` = foodsaver.`id`)
            WHERE
                abholer.`date` > DATE_ADD(CURDATE(), INTERVAL -$numberMonths MONTH)
            GROUP BY bezirk.`id`
            ORDER BY fetchCount DESC
            LIMIT 10
        SQL;

        $result = $this->db->fetchAll($query);

        return array_map(fn ($regionStats) => PickupItem::create(
            $regionStats['name'],
            intval($regionStats['fetchCount'])
        ), $result);
    }

    public function countAllBaskets(): int
    {
        // Count all entries in fs_basket
        return $this->db->count('fs_basket', []);
    }

    public function avgWeeklyBaskets(int $diffWeeks = 4): int
    {
        // query
        $q = '
			SELECT
				COUNT(*)
			FROM
				fs_basket
			WHERE
				time > DATE_ADD(CURDATE(), INTERVAL - :diffWeeks*7-1 DAY) AND
				time < CURDATE()
		';
        // get count from db
        $basketCount = (int)$this->db->fetchValue($q, [':diffWeeks' => $diffWeeks]);

        // divide number of fetches by time difference
        return (int)($basketCount / $diffWeeks);
    }

    public function countActiveFoodSharePoints(): int
    {
        return $this->db->count('fs_fairteiler', ['status' => 1]);
    }

    /**
     * @return array<StatisticsGender>
     */
    public function genderCountRegion(int $regionId): array
    {
        $list = $this->db->fetchAll(
            'select  fs.geschlecht as gender,
						   count(*) as numberOfGender
					from fs_foodsaver_has_bezirk fb
		 			left outer join fs_foodsaver fs on fb.foodsaver_id=fs.id
					where fb.bezirk_id = :regionId
					and fs.deleted_at is null
					group by geschlecht',
            [':regionId' => $regionId]
        );

        return array_map(fn ($StatisticsGender) => StatisticsGender::create($StatisticsGender['gender'], $StatisticsGender['numberOfGender']), $list);
    }

    /**
     * @return array<StatisticsGender>
     */
    public function genderCountHomeRegion(int $regionId): array
    {
        $list = $this->db->fetchAll(
            'select  fs.geschlecht as gender,
						   count(*) as numberOfGender
                    from fs_foodsaver_has_bezirk fb
		 			left outer join fs_foodsaver fs on fb.foodsaver_id=fs.id
					where fs.bezirk_id = :regionId
					and fs.deleted_at is null
                    and fs.bezirk_id = fb.bezirk_id
					group by geschlecht',
            [':regionId' => $regionId]
        );

        return array_map(fn ($StatisticsGender) => StatisticsGender::create($StatisticsGender['gender'], $StatisticsGender['numberOfGender']), $list);
    }

    /**
     * @return array<StatisticsAgeBand>
     */
    public function ageBandHomeDistrict(int $districtId): array
    {
        $list = $this->db->fetchAll(
            'SELECT
				CASE
				WHEN age >=18 AND age <=25 THEN \'18-25\'
				WHEN age >=26 AND age <=33 THEN \'26-33\'
				WHEN age >=34 AND age <=41 THEN \'34-41\'
				WHEN age >=42 AND age <=49 THEN \'42-49\'
				WHEN age >=50 AND age <=57 THEN \'50-57\'
				WHEN age >=58 AND age <=65 THEN \'58-65\'
				WHEN age >=66 AND age <=73 THEN \'66-73\'
				WHEN age >=74 AND age < 100 THEN \'74+\'
				WHEN age >= 100 or age < 18 THEN \'invalid\'
				WHEN age IS NULL THEN \'unknown\'
				END AS ageBand,
				COUNT(*) AS numberOfAgeBand
				FROM
				(
				 SELECT DATE_FORMAT(NOW(), \'%Y\') - DATE_FORMAT(geb_datum, \'%Y\') - (DATE_FORMAT(NOW(), \'00-%m-%d\') < DATE_FORMAT(geb_datum, \'00-%m-%d\')) AS age,
				 id from fs_foodsaver_has_bezirk fb
		 			left outer join fs_foodsaver fs on fb.foodsaver_id=fs.id
                 WHERE fs.bezirk_id = fb.bezirk_id AND fs.rolle >= :rolle AND fs.bezirk_id = :id and deleted_at is null
				) AS tbl
				GROUP BY ageBand',
            ['rolle' => Role::FOODSAVER->value, ':id' => $districtId]
        );

        return array_map(fn ($StatisticsAgeBand) => StatisticsAgeBand::create($StatisticsAgeBand['ageBand'], $StatisticsAgeBand['numberOfAgeBand']), $list);
    }

    /**
     * @return array<StatisticsAgeBand>
     */
    public function ageBandDistrict(int $districtId): array
    {
        $list = $this->db->fetchAll(
            'SELECT
				CASE
				WHEN age >=18 AND age <=25 THEN \'18-25\'
				WHEN age >=26 AND age <=33 THEN \'26-33\'
				WHEN age >=34 AND age <=41 THEN \'34-41\'
				WHEN age >=42 AND age <=49 THEN \'42-49\'
				WHEN age >=50 AND age <=57 THEN \'50-57\'
				WHEN age >=58 AND age <=65 THEN \'58-65\'
				WHEN age >=66 AND age <=73 THEN \'66-73\'
				WHEN age >=74 AND age < 100 THEN \'74+\'
				WHEN age >= 100 or age < 18 THEN \'invalid\'
				WHEN age IS NULL THEN \'unknown\'
				END AS ageBand,
				COUNT(*) AS numberOfAgeBand
				FROM
				(
				 SELECT DATE_FORMAT(NOW(), \'%Y\') - DATE_FORMAT(geb_datum, \'%Y\') - (DATE_FORMAT(NOW(), \'00-%m-%d\') < DATE_FORMAT(geb_datum, \'00-%m-%d\')) AS age,
				 		fs.id
				 FROM fs_foodsaver_has_bezirk fb
					 left outer join fs_foodsaver fs on fb.foodsaver_id=fs.id
					 WHERE fs.rolle >= :rolle AND fb.bezirk_id = :id and fs.deleted_at is null
				) AS tbl
				GROUP BY ageBand',
            ['rolle' => Role::FOODSAVER->value, ':id' => $districtId]
        );

        return array_map(fn ($StatisticsAgeBand) => StatisticsAgeBand::create($StatisticsAgeBand['ageBand'], $StatisticsAgeBand['numberOfAgeBand']), $list);
    }
}
