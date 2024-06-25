<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddDescriptionToPickups extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_abholzeiten')
            ->addColumn('description', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 100,
                'signed' => false,
                'collation' => 'utf8mb4_unicode_ci',
                'encoding' => 'utf8mb4',
                'comment' => 'optional description for this pickup time'
            ])
            ->update();

        $this->table('fs_fetchdate')
            ->addColumn('description', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 100,
                'signed' => false,
                'collation' => 'utf8mb4_unicode_ci',
                'encoding' => 'utf8mb4',
                'comment' => 'optional description for this pickup'
            ])
            ->update();
    }
}
