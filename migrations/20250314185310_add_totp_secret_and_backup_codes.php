<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class AddTotpSecretAndBackupCodes extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $table = $this->table('fs_foodsaver');
        $table->addColumn('totp_secret', 'string', [
                  'null' => true,
                  'default' => null,
                  'limit' => 40,
              ])
              ->addColumn('backup_codes', 'text', [
                  'null' => true,
                  'limit' => MysqlAdapter::TEXT_LONG,
              ])
              ->update();

        $table = $this->table('fs_foodsaver_archive');
        $table->addColumn('totp_secret', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 40,
            ])
            ->addColumn('backup_codes', 'text', [
                'null' => true,
                'limit' => MysqlAdapter::TEXT_LONG,
            ])
            ->update();
    }
}
