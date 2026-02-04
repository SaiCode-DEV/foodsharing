<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class IncreaseStoreZipCodeLength extends AbstractMigration
{
    /**
     * Increases the max length of the 'plz' column from fs_betrieb. This is required for some non-german zip codes (#2514).
     */
    public function change(): void
    {
        $this->table('fs_betrieb')
            ->changeColumn('plz', 'string', ['null' => false, 'limit' => 10])
            ->update();
    }
}
