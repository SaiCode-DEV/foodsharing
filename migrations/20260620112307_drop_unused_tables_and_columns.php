<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropUnusedTablesAndColumns extends AbstractMigration
{
    public function change(): void
    {
        // Tables
        $this->table('fs_ipblock')->drop()->update();
        $this->table('fs_stat_abholmengen')->drop()->update();
        $this->table('fs_basket_has_art')->drop()->update();
        $this->table('fs_basket_has_types')->drop()->update();

        // Columns
        $this->table('fs_foodsaver_has_wallpost')->removeColumn('usercomment')->update();
        $this->table('fs_question_has_wallpost')->removeColumn('usercomment')->update();
        $this->table('fs_usernotes_has_wallpost')->removeColumn('usercomment')->update();
        $this->table('fs_mailbox')->removeColumn('member')->update();
        $this->table('fs_bezirk')->removeColumn('conversation_id')->update();
        $this->table('fs_foodsaver')->removeColumn('stat_fetchrate')->update();
    }
}
