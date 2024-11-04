<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ChangeLimitForColumnPublicInfoInTableFsBetrieb extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('fs_betrieb');

        $table->changeColumn('public_info', 'string', [
            'limit' => 535
        ]);

        $table->update();
    }
}
