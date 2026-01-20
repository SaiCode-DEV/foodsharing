<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddNewCountersForGivenAndEngaged extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_bezirk')
            ->addColumn('stat_givecount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('stat_engagecount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->update();
        $this->table('fs_foodsaver_archive')
            ->addColumn('stat_givecount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('stat_engagecount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->update();
        $this->table('fs_foodsaver')
            ->addColumn('stat_givecount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('stat_engagecount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->update();
    }
}
