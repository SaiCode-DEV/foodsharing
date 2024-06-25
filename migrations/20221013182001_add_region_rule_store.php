<?php

use Phinx\Migration\AbstractMigration;

final class AddRegionRuleStore extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_betrieb')
            ->addColumn('use_region_pickup_rule', 'integer', [
                'null' => false,
                'default' => 0,
                'limit' => 1,
                'signed' => false,
                'comment' => '@StoreSettings::USE_PICKUP_RULE_YES = Store follows region pickup rule. @StoreSettings::USE_PICKUP_RULE_NO = Store does not follow region pickup rule.'
            ])
            ->update();
    }
}
