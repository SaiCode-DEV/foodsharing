<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DeleteContactPageInContentTable extends AbstractMigration
{
    public function change(): void
    {
        $builder = $this->getDeleteBuilder();
        $builder
            ->delete('fs_content')
            ->where(['id' => 73])
            ->execute();
    }
}
