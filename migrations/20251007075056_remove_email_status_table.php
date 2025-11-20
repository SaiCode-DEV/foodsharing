<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveEmailStatusTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_email_status')->drop()->update();
        $this->table('fs_send_email')->drop()->update();
    }
}
