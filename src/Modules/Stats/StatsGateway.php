<?php

namespace Foodsharing\Modules\Stats;

use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DBConstants\Store\CooperationStatus;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;

/**
 * Statistics update operations for foodsaver, store team and region stats.
 *
 * For performance reasons all operations are based on single SQL-queries.
 * To give everyone some understanding of how these queries work, I will explain
 * their basic structure here.
 *
 * All queries follow the following scheme:
 *
 * UPDATE table_name t                   table_name is the table to be changed, t its alias
 * LEFT OUTER JOIN (                     To update the table, other values need to be queried.
 *                                       This is done via subqueries, which are added via
 * 										 LEFT OUTER JOIN. This way, there will be a value for
 *                                       every row of table t, which may be NULL if the subquery
 *                                       finds no result for that row.
 *     SELECT                            This is a subquery, which is just a SELECT-statement to
 *                                       query the data needed for the update.
 *         any_id_column AS id,          This is the id. It is used to join the selected data with
 *                                       the table to be updated.
 *         AGG_FCT(any_column) AS column This is where most of the magic happens. The SELECT
 *                                       statements contain many results per id. All of these are
 *                                       combined using an aggregate function (here AGG_FCT), which
 *                                       is usually COUNT or SUM. These function combine all values
 *                                       from one group (see 5 lines down) into one value.
 *         [more columns]                From some subqueries more than one value is selected.
 *                                       Typically it is just one however.
 *     FROM other_table_name             The table from which the data is queried, possibly ...
 *     [more JOINed tables]              joined with some other tables if the data cannot be infered
 *                                       from just one table.
 *     WHERE <condition>                 A typical WHERE clause. This limits the data to be included.
 *     GROUP BY any_id_column            For aggregate functions to work properly, it must be defined
 *                                       which rows of data should be combined / grouped into one.
 *                                       This line does exactly that, by grouping all values, whose
 *                                       value of the any_id_column is equal, meaning all values
 *                                       corresponding to one row of the table t are grouped.
 * ) AS subquery_name                    Assign a name to the subquery
 * ON name.id = t.id                     Link the subquery to the table to be updated by their ids.
 * [more LEFT OUTER JOINS]               Depending on the values that should be updated, more queries
 *                                       might be needed. These follow the same basic structure.
 * SET                                   The SET keyword introduces the section, in which values of
 *                                       the table are acutally updated.
 *     t.some_stat = IFNULL(             The column some_stat of table t recieves a new value. This
 *         subquery_name.column,         value is the value of the subquery, if that is not NULL, and
 *         default_value                 a default value otherwise.
 *     )
 *     [more coulums to be updated]      Multiple columns might be updated at once.
 *
 *
 * Apart from all that, the queries in this class use Database::execute, which is
 * deprecated and should not be used if not necessary. Using fetch would work as
 * well, but would be misleading, since it would not be used for fetching data.
 */
class StatsGateway extends BaseGateway
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    /**
     * Update the user stats for every foodsaver.
     *
     * This includes:
     *  - number of pickups
     *  - total weight
     *  - number of posts
     *  - number of bananas recived
     *  - number of buddies (with accepted request)
     *  - rate of successful fetches
     *
     * @throws \Exception
     */
    public function updateFoodsaverStats(): void
    {
        $this->db->execute('UPDATE fs_foodsaver fs
			LEFT OUTER JOIN (
				SELECT
					a.foodsaver_id AS id,
					SUM(w.weight) AS weight,
					COUNT(a.foodsaver_id) AS fetches
				FROM fs_abholer a
				LEFT OUTER JOIN fs_betrieb b ON
					a.betrieb_id = b.id
				LEFT OUTER JOIN fs_fetchweight w ON
					b.abholmenge = w.id
				WHERE a.date < NOW()
				GROUP BY a.foodsaver_id
			) as fetches ON fetches.id = fs.id
			LEFT OUTER JOIN (
				SELECT foodsaver_id AS id, COUNT(id) AS posts FROM (
					SELECT foodsaver_id, id FROM fs_theme_post
					UNION
					SELECT foodsaver_id, id FROM fs_wallpost
				) AS posts
				GROUP by foodsaver_id
			) as posts ON posts.id = fs.id
			LEFT OUTER JOIN (
				SELECT foodsaver_id AS id, COUNT(foodsaver_id) AS bananas
				FROM fs_rating
				GROUP BY foodsaver_id
			) as bananas ON bananas.id = fs.id
			LEFT OUTER JOIN (
				SELECT foodsaver_id AS id, COUNT(foodsaver_id) AS buddies
				FROM fs_buddy
				WHERE confirmed = 1
				GROUP BY foodsaver_id
			) as buddies ON buddies.id = fs.id
			LEFT OUTER JOIN (
				SELECT foodsaver_id AS id, COUNT(foodsaver_id) AS missed
				FROM fs_report
				WHERE `reporttype` = 1 AND committed = 1 AND tvalue like \'%Ist gar nicht zum Abholen gekommen%\'
				GROUP BY foodsaver_id
			) as missed ON missed.id = fs.id
			SET
				fs.stat_fetchcount = IFNULL(fetches.fetches, 0),
				fs.stat_fetchweight = IFNULL(fetches.weight, 0),
				fs.stat_postcount = IFNULL(posts.posts, 0),
				fs.stat_bananacount = IFNULL(bananas.bananas, 0),
				fs.stat_buddycount = IFNULL(buddies.buddies, 0),
				fs.stat_fetchrate = IFNULL(ROUND(100 - IFNULL(missed.missed, 0) / fetches.fetches * 100, 2), 100)
		');
    }

    /**
     * Update the store team stats for each foodsaver in every store.
     *
     * This includes:
     *  - number of pickups
     *  - first and last pickup date
     *  - new stats update date
     *
     * @throws \Exception
     */
    public function updateStoreUsersStats(): void
    {
        $this->db->execute('UPDATE fs_betrieb_team team
			LEFT OUTER JOIN (
				SELECT
					foodsaver_id,
					betrieb_id,
					COUNT(*) as fetchcount,
					DATE_FORMAT(min(`date`),"%Y-%m-%d") as first_fetch,
					DATE_FORMAT(max(`date`),"%Y-%m-%d") as last_fetch
				FROM fs_abholer
				WHERE `date` < NOW() AND betrieb_id > 0
				GROUP BY betrieb_id, foodsaver_id
			) as stats ON stats.foodsaver_id = team.foodsaver_id AND stats.betrieb_id = team.betrieb_id
			SET
				team.stat_fetchcount = IFNULL(stats.fetchcount, 0),
				team.stat_first_fetch = stats.first_fetch,
				team.stat_last_fetch = stats.last_fetch,
				team.stat_last_update = NOW()
		');
    }

    /**
     * Update the region stats that cannot be calculated incrementally for every region.
     *
     * This includes:
     *  - number of foodsavers
     *  - number of bots
     *  - number of posts
     *  - number of sharepoints
     *  - number of stores
     *  - number of cooperating stores
     *
     * @throws \Exception
     */
    public function updateNonIncrementalRegionStats(): void
    {
        $this->db->execute('UPDATE fs_bezirk region
			LEFT OUTER JOIN (
				SELECT
					c.ancestor_id AS region_id,
					COUNT(DISTINCT f.foodsaver_id) AS foodsaver
				FROM fs_bezirk_closure c
				INNER JOIN fs_foodsaver_has_bezirk f ON f.bezirk_id = c.bezirk_id
				WHERE c.ancestor_id > 0
				GROUP BY ancestor_id
			) as foodsaver ON foodsaver.region_id = region.id
			LEFT OUTER JOIN (
				SELECT
					c.ancestor_id AS region_id,
					COUNT(DISTINCT amb.foodsaver_id) AS ambassadors
				FROM fs_bezirk_closure c
				INNER JOIN fs_bezirk b ON b.id = c.bezirk_id
				INNER JOIN fs_botschafter amb ON amb.bezirk_id = b.id AND b.type != :type_working_group
				WHERE c.ancestor_id > 0
				GROUP BY c.ancestor_id
			) as ambassadors ON ambassadors.region_id = region.id
			LEFT OUTER JOIN (
				SELECT
					c.ancestor_id AS region_id,
					COUNT(*) AS posts
				FROM fs_bezirk_closure c
				INNER JOIN fs_bezirk_has_theme t ON t.bezirk_id = c.bezirk_id
				INNER JOIN fs_theme_post p ON p.theme_id = t.theme_id
				WHERE c.ancestor_id > 0
				GROUP BY c.ancestor_id
			) as theme_posts ON theme_posts.region_id = region.id
			LEFT OUTER JOIN (
				SELECT
					c.ancestor_id AS region_id,
					COUNT(*) AS posts
				FROM fs_bezirk_closure c
				INNER JOIN fs_betrieb b ON b.bezirk_id = c.bezirk_id
				INNER JOIN fs_store_has_wallpost hp ON b.id = hp.store_id
				WHERE c.ancestor_id > 0
				GROUP BY c.ancestor_id
			) as store_posts ON store_posts.region_id = region.id
			LEFT OUTER JOIN (
				SELECT
					c.ancestor_id AS region_id,
					COUNT(*) AS sharepoints
				FROM fs_bezirk_closure c
				INNER JOIN fs_fairteiler f ON f.bezirk_id = c.bezirk_id
				WHERE c.ancestor_id > 0
				GROUP BY ancestor_id
			) as sharepoints ON sharepoints.region_id = region.id
			LEFT OUTER JOIN (
				SELECT
					c.ancestor_id AS region_id,
					COUNT(b.id) AS stores,
					COUNT(b.betrieb_status_id = :coop_established OR NULL) AS coops
				FROM fs_bezirk_closure c
				INNER JOIN fs_betrieb b ON b.bezirk_id = c.bezirk_id
				WHERE c.ancestor_id > 0
				GROUP BY ancestor_id
			) as stores ON stores.region_id = region.id
			SET
				region.stat_fscount = IFNULL(foodsaver.foodsaver, 0),
				region.stat_botcount = IFNULL(ambassadors.ambassadors, 0),
				region.stat_postcount = IFNULL(theme_posts.posts, 0) + IFNULL(store_posts.posts, 0),
				region.stat_fairteilercount = IFNULL(sharepoints.sharepoints, 0),
				region.stat_betriebcount = IFNULL(stores.stores, 0),
				region.stat_korpcount = IFNULL(stores.coops, 0)
		', [
            'type_working_group' => UnitType::WORKING_GROUP,
            'coop_established' => CooperationStatus::COOPERATION_ESTABLISHED->value,
        ]);

        // Make sure that all regions (except working groups) have an entry in fs_region_statistics
        $this->db->execute('INSERT IGNORE INTO fs_region_statistics (region_id)
            SELECT id FROM fs_bezirk b
            WHERE b.type != :type_working_group
        ', [
            'type_working_group' => UnitType::WORKING_GROUP,
        ]);

        /* Update the fs_region_statistics table for all regions. This does not need to exclude working groups because
        they do not have an entry in the fs_region_statistics table. */
        $this->db->execute('UPDATE fs_region_statistics stats
            LEFT OUTER JOIN (
                SELECT
					c.ancestor_id AS region_id,
					COUNT(*) AS foodsavers
                FROM fs_bezirk_closure c
                JOIN fs_foodsaver fs ON fs.bezirk_id = c.bezirk_id
                WHERE c.ancestor_id > 0
                AND fs.deleted_at IS NULL
                AND fs.last_login > NOW() - INTERVAL 30 DAY
                AND fs.verified = 1
                GROUP BY region_id
            ) as active_foodsavers ON active_foodsavers.region_id = stats.region_id
            LEFT OUTER JOIN (
                SELECT
					c.ancestor_id AS region_id,
					COUNT(*) AS cooperations
                FROM fs_bezirk_closure c
                JOIN fs_betrieb s ON s.bezirk_id = c.bezirk_id
                WHERE c.ancestor_id > 0
                AND s.betrieb_status_id = :cooperating_status
                GROUP BY region_id
            ) as active_coorporations ON active_coorporations.region_id = stats.region_id
            LEFT OUTER JOIN (
                SELECT
                    c.ancestor_id AS region_id,
					COUNT(*) AS count,
					SUM(w.weight) AS weight
                FROM fs_bezirk_closure c
                JOIN fs_bezirk r ON r.id = c.bezirk_id
                JOIN fs_betrieb s ON s.bezirk_id = r.id
                JOIN fs_abholer a ON a.betrieb_id = s.id
                JOIN fs_fetchweight w ON w.id = s.abholmenge
                WHERE c.ancestor_id > 0
                AND a.date >= (NOW() - INTERVAL 30 DAY) AND a.date < NOW()
                GROUP BY region_id
            ) as pickups ON pickups.region_id = stats.region_id
            LEFT OUTER JOIN (
                SELECT
                    c.ancestor_id AS region_id,
					COUNT(*) AS food_share_points
                FROM fs_bezirk_closure c
                JOIN fs_fairteiler fsp ON fsp.bezirk_id = c.bezirk_id
                WHERE c.ancestor_id > 0
                AND fsp.status = 1
                GROUP BY region_id
            ) as active_food_share_points ON active_food_share_points.region_id = stats.region_id
            LEFT OUTER JOIN (
				SELECT
					c.ancestor_id AS region_id,
					COUNT(*) AS baskets
				FROM fs_bezirk_closure c
				INNER JOIN fs_basket bas ON bas.bezirk_id = c.bezirk_id
				INNER JOIN fs_bezirk b ON b.id = c.bezirk_id
				WHERE c.ancestor_id > 0
				AND bas.time >= (NOW() - INTERVAL 30 DAY) AND bas.time < NOW()
				GROUP BY region_id
			) as baskets ON baskets.region_id = stats.region_id
			SET
				stats.last_modified = NOW(),
				stats.active_home_region_foodsavers = IFNULL(active_foodsavers.foodsavers, 0),
				stats.active_coorporations = IFNULL(active_coorporations.cooperations, 0),
				stats.pickups_last_month = IFNULL(pickups.count, 0),
                stats.saved_food_weight_last_month = IFNULL(pickups.weight, 0),
                stats.active_food_share_points = IFNULL(active_food_share_points.food_share_points, 0),
				stats.food_baskets_last_month = IFNULL(baskets.baskets, 0)
		', [
            'cooperating_status' => CooperationStatus::COOPERATION_ESTABLISHED->value
        ]);
    }

    /**
     * Update the region stats that can be calculated incrementally for every region.
     *
     * This includes:
     *  - number of pickups
     *  - first and last pickup date
     *  - new stats update date
     *
     * @throws \Exception
     */
    public function updateIncrementalRegionStats(): void
    {
        $this->db->execute('UPDATE fs_bezirk region
			LEFT OUTER JOIN (
				SELECT
					c.ancestor_id AS region_id,
					SUM(w.weight) AS weight_diff,
					COUNT(*) AS fetches_diff
				FROM fs_bezirk_closure c
				INNER JOIN fs_bezirk r ON r.id = c.bezirk_id
				INNER JOIN fs_betrieb b ON b.bezirk_id = c.bezirk_id
				INNER JOIN fs_abholer a ON a.betrieb_id = b.id
					AND a.date < NOW()
					AND a.date > r.stat_last_update
				INNER JOIN fs_fetchweight w ON w.id = b.abholmenge
				WHERE c.ancestor_id > 0
				GROUP BY ancestor_id
			) as fetches ON fetches.region_id = region.id
			SET
				region.stat_fetchcount = region.stat_fetchcount + IFNULL(fetches.fetches_diff, 0),
				region.stat_fetchweight = region.stat_fetchweight + IFNULL(fetches.weight_diff, 0),
				region.stat_last_update = NOW()

		');
    }

    /**
     * Update the region stats that could be calculated incrementally for every region, but from scratch.
     *
     * This can be used to fix previous wrong stat calculations.
     *
     * This includes:
     *  - number of pickups
     * 	- total weight
     *  - new stats update date
     *
     * @throws \Exception
     */
    public function calculateIncrementalRegionStatsFromScratch(): void
    {
        $this->db->execute('UPDATE fs_bezirk region
			LEFT OUTER JOIN (
				SELECT
					c.ancestor_id AS region_id,
					SUM(w.weight) AS weight_diff,
					COUNT(*) AS fetches_diff
				FROM fs_bezirk_closure c
				INNER JOIN fs_betrieb b ON b.bezirk_id = c.bezirk_id
				INNER JOIN fs_abholer a ON a.betrieb_id = b.id
					AND a.date < NOW()
				INNER JOIN fs_fetchweight w ON w.id = b.abholmenge
				WHERE c.ancestor_id > 0
				GROUP BY ancestor_id
			) as fetches ON fetches.region_id = region.id
			SET
				region.stat_fetchcount = IFNULL(fetches.fetches_diff, 0),
				region.stat_fetchweight = IFNULL(fetches.weight_diff, 0),
				region.stat_last_update = NOW()
		');
    }
}
