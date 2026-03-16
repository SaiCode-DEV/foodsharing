<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddRegistrationAttemptTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_registration_attempt')
            ->addColumn('email', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('token', 'string', [
                'limit' => 60,
                'null' => true,
            ])
            ->addColumn('valid_until', 'datetime', [
                'null' => false,
            ])
            ->create();
    }
}
