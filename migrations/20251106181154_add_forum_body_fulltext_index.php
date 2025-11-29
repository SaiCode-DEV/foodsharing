<?php

use Phinx\Migration\AbstractMigration;

class AddForumBodyFulltextIndex extends AbstractMigration
{
    public function change()
    {
        $this->table('fs_theme_post')
            ->addIndex(['body'], [
                'name' => 'fs_theme_post_body_fulltext',
                'unique' => false,
                'type' => 'fulltext',
            ])
            ->save();
    }
}
