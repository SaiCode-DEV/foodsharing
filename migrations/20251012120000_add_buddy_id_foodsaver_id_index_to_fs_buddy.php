<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class AddBuddyIdFoodsaverIdIndexToFsBuddy extends AbstractMigration
{
    public function change(): void
    {
        // This index improves the performance of queries that check for
        // existing buddy relationships or requests between two users. The
        // combination (foodsaver_id, buddy_id) is already convered by the
        // primary key.
        $this->table('fs_buddy')
            ->addIndex(['buddy_id', 'foodsaver_id'], ['name' => 'idx_buddy_id_foodsaver_id'])
            ->update();
    }
}
