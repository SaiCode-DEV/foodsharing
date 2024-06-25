<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveContentForTeamPage extends AbstractMigration
{
    public function change()
    {
        $this->execute('DELETE FROM fs_content WHERE id IN (39, 53, 54)');
    }
}
