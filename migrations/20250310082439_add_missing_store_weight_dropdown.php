<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddMissingStoreWeightDropdown extends AbstractMigration
{
    /**
     * Adds a missing interval in the pickup weight table fs_fetchweight.
     *
     * Previous values [..., 5 => 25, 6 => 45, 7 => 64]
     * New values:  [..., 5 => 25, 6 => 35, 7 => 45, 8 => 64]
     */
    public function change(): void
    {
        // Shift the weight index of existing stores
        $this->query('UPDATE fs_betrieb SET abholmenge = 8 WHERE abholmenge = 7');
        $this->query('UPDATE fs_betrieb SET abholmenge = 7 WHERE abholmenge = 6');

        // Update the weight table
        $this->query('UPDATE fs_fetchweight SET weight=35 WHERE id=6');
        $this->query('UPDATE fs_fetchweight SET weight=45 WHERE id=7');
        $this->table('fs_fetchweight')->insert(['id' => 8, 'weight' => 64])->save();
    }
}
