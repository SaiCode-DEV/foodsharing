<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class IncreasePollOptionLength extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_poll_has_options')
            ->changeColumn('option_text', 'string', ['limit' => 1000])
            ->update();
    }
}
