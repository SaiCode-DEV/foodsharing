<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddLocationPointToFsBasket extends AbstractMigration
{
    public function change(): void
    {
        // composite index to speed common non-geo filters in nearby queries
        $this->execute('CREATE INDEX `idx_basket_status_until_fs` ON `fs_basket` (`status`, `until`, `foodsaver_id`);');
    }
}
