<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddForumThreadIndices extends AbstractMigration
{
    public function change(): void
    {
        // Improve performance of forum update queries by adding composite indexes
        // used for filtering by active/bot_theme/bezirk and ordering by last_post_id.

        // Index to allow scanning fs_theme rows with active=1 ordered by last_post_id
        $this->table('fs_theme')
            ->addIndex(['active', 'last_post_id'], ['name' => 'idx_theme_active_lastpost'])
            ->update();

        // Index to speed up filtering and joining on fs_bezirk_has_theme when
        // queries filter by bot_theme and bezirk_id and then join by theme_id.
        $this->table('fs_bezirk_has_theme')
            ->addIndex(['bot_theme', 'bezirk_id', 'theme_id'], ['name' => 'idx_bt_bot_bezirk_theme'])
            ->update();
    }
}
