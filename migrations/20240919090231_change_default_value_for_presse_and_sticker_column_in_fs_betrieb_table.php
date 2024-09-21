<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ChangeDefaultValueForPresseAndStickerColumnInFsBetriebTable extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('fs_betrieb');

        $table->changeColumn('sticker', 'integer', [
            'limit' => Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null' => true,
            'default' => null,
        ]);

        $table->changeColumn('presse', 'integer', [
            'limit' => Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null' => true,
            'default' => null,
        ]);

        $table->update();
    }
}
