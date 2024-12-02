<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class MakeUploadsUsersNullable extends AbstractMigration
{
    /**
     * Changes the column user_id in uplods to nullable. This is required for incoming email attachments.
     */
    public function change(): void
    {
        $this->table('uploads')
            ->changeColumn('user_id', 'integer', [
                'null' => true,
                'default' => null,
            ])
            ->update();
    }
}
