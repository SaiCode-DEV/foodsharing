<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Adds an inheritable timezone to regions (#2761). A region without an own value inherits
 * the timezone of its closest ancestor in the region tree; without any ancestor value the
 * platform default Europe/Berlin applies. Only non-CET subtrees need the field set.
 */
final class AddRegionTimezone extends AbstractMigration
{
    public function up(): void
    {
        $this->table('fs_bezirk')
            ->addColumn('timezone', 'string', [
                'limit' => 64,
                'null' => true,
                'default' => null,
                'comment' => 'IANA timezone; NULL inherits from the closest ancestor (platform default Europe/Berlin)',
            ])
            ->update();
    }

    public function down(): void
    {
        $this->table('fs_bezirk')
            ->removeColumn('timezone')
            ->update();
    }
}
