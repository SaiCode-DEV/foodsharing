<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddDonationModalContent extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $body = '<div>Liebe fs-Community, wir brauchen eure Unterst&uuml;tzung f&uuml;r die &uuml;berregionale Arbeit!
Bitte spendet f&uuml;r eine Service- und Koordinationsstelle, die IT-Unterst&uuml;tzung, die Bildungsangebote
sowie die Weiterbildung lokaler Promotor:innen. Unser Ziel sind <strong>DONATION_GOAL</strong>
im Jahr 2024. Jeder Euro z&auml;hlt!</div>';

        $this->table('fs_content')
            ->insert([
                'id' => '1',
                'name' => 'DonationModal',
                'title' => 'Spenden für foodsharing - wir brauchen Eure Unterstützung!',
                'body' => $body,
            ])
            ->save();
    }
}
