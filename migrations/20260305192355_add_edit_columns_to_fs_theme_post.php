<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddEditColumnsToFsThemePost extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_theme_post')
            ->addColumn('last_edited_at', 'datetime', ['null' => true, 'comment' => 'Time of the last edit of the post (null if never edited)'])
            ->update();
    }
}
