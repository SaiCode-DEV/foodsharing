<?php

use Phinx\Migration\AbstractMigration;

class AddIdxBetriebDateToFsAbholer extends AbstractMigration
{
    public function change()
    {
        $this->table('fs_abholer')
            ->addIndex(['betrieb_id', 'date'], ['name' => 'idx_betrieb_date'])
            ->update();
    }
}
