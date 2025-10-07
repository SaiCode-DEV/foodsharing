<?php

use Phinx\Migration\AbstractMigration;

class RemoveLastaccessAtFromUploads extends AbstractMigration
{
    public function change()
    {
        $this->table('uploads')
            ->removeColumn('lastaccess_at')
            ->update();
    }
}
