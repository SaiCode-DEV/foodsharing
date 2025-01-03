<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DeleteBodyInColumnDonationModalForTableFsContent extends AbstractMigration
{

    public function change()
    {
        $this->execute("UPDATE `fs_content` SET `body` = '' WHERE `id` = 1");
    }
}
