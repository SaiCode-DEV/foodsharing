<?php

class OptimizeGroupListQueries extends Phinx\Migration\AbstractMigration
{
    public function change()
    {
        // Optimize fs_bezirk lookups by parent_id and type (used in group list queries)
        $this->table('fs_bezirk')
            ->addIndex(['parent_id', 'type'], [
                'name' => 'idx_bezirk_parent_type',
                'unique' => false,
            ])
            ->update();

        // Optimize fs_foodsaver_has_bezirk lookups by bezirk_id and active status
        // Helps with JOINs that filter on active members
        $this->table('fs_foodsaver_has_bezirk')
            ->addIndex(['bezirk_id', 'active'], [
                'name' => 'idx_fshb_bezirk_active',
                'unique' => false,
            ])
            ->update();
    }
}
