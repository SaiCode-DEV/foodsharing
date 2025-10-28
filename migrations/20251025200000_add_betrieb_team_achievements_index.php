<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class AddBetriebTeamAchievementsIndex extends AbstractMigration
{
    public function change(): void
    {
        // Speed up store team lookups

        // The query's WHERE contains betrieb_id = ? AND t.active IN (...).
        // Putting betrieb_id then active lets the database seek directly to
        // rows matching both values. Including foodsaver_id as the third column
        // makes the index covering for the JOIN to foodsaver_id (avoid
        // additional lookups).
        // Result on real data in EXPLAIN: rows for t in EXPLAIN should drop
        // by the size of the team (> 500 for very large stores).
        $this->table('fs_betrieb_team')
            ->addIndex(['betrieb_id', 'active', 'foodsaver_id'], ['name' => 'idx_betrieb_team_betrieb_active_foodsaver'])
            ->update();

        // Speed up awarded achievement lookups in store teams
        // The database currently uses foodsaver_id alone. This additional
        // composite allows the engine to also match achievement_id = ? and
        // evaluate valid_until with fewer row accesses per foodsaver. As we
        // often query by a specific achievement (like hygiene = ID 4), this
        // reduces the number of steps the database needs to take for lookups.
        $this->table('fs_foodsaver_has_achievement')
            ->addIndex(['foodsaver_id', 'achievement_id', 'valid_until'], ['name' => 'idx_fsa_foodsaver_achievement_valid'])
            ->update();
    }
}
