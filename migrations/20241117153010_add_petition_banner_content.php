<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddPetitionBannerContent extends AbstractMigration
{
    public function change(): void
    {
        $body = '<p>Lorem ipsum hier sollte ein Text hin. Lorem ipsum hier sollte ein Text hin. Lorem ipsum hier sollte ein Text hin. Lorem ipsum hier sollte ein Text hin. Lorem ipsum hier sollte ein Text hin. Lorem ipsum hier sollte ein Text hin. Lorem ipsum hier sollte ein Text hin. Lorem ipsum hier sollte ein Text hin. Lorem ipsum hier sollte ein Text hin. Lorem ipsum hier sollte ein Text hin. Lorem ipsum hier sollte ein Text hin.</p><p>Lorem ipsum hier sollte ein Text hin. Lorem ipsum hier sollte ein Text hin. Lorem ipsum hier sollte ein Text hin. Lorem ipsum hier sollte ein Text hin. Lorem ipsum hier sollte ein Text hin. Lorem ipsum hier sollte ein Text hin. Lorem ipsum hier sollte ein Text hin.</p>';

        $this->table('fs_content')
            ->insert([
                'id' => '92',
                'name' => 'PetitionBanner',
                'title' => 'Bundestagspetition!',
                'body' => $body,
            ])
            ->save();
    }
}
