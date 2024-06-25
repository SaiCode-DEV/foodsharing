<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddAchievementBaseTables extends AbstractMigration
{
    public function change(): void
    {
        $achievementsTable = $this->table('fs_achievement');
        $achievementsTable->addColumn('name', 'string');
        $achievementsTable->addColumn('description', 'string');
        $achievementsTable->addColumn('validity_in_days_after_assignment', 'integer', [
            'null' => true,
        ]);
        $achievementsTable->addColumn('is_requestable_by_foodsaver', 'boolean');
        $achievementsTable->addTimestamps();

        $achievementsTable->create();

        $foodsaverHasAchievementTable = $this->table('fs_foodsaver_has_achievement');
        $foodsaverHasAchievementTable->addColumn('foodsaver_id', 'integer', [
            'length' => 10,
            'signed' => false,
            'null' => false,
        ]);
        $foodsaverHasAchievementTable->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', [
            'delete' => 'CASCADE',
            'update' => 'NO_ACTION',
        ]);
        $foodsaverHasAchievementTable->addColumn('achievement_id', 'integer', [
            'length' => 11,
            'signed' => false,
            'null' => false,
        ]);
        $foodsaverHasAchievementTable->addForeignKey('achievement_id', 'fs_achievement', 'id', [
            'delete' => 'CASCADE',
            'update' => 'NO_ACTION',
        ]);
        $foodsaverHasAchievementTable->addColumn('reviewer_id', 'integer', [
            'length' => 10,
            'signed' => false,
            'null' => true,
        ]);
        $foodsaverHasAchievementTable->addForeignKey('reviewer_id', 'fs_foodsaver', 'id', [
            'delete' => 'SET_NULL',
            'update' => 'NO_ACTION',
        ]);
        $foodsaverHasAchievementTable->addColumn('notice', 'text', [
            'null' => true,
        ]);
        $foodsaverHasAchievementTable->addColumn('valid_until', 'datetime', [
            'null' => true,
            'default' => null,
        ]);
        $foodsaverHasAchievementTable->addTimestamps();

        $foodsaverHasAchievementTable->addIndex('foodsaver_id');
        $foodsaverHasAchievementTable->addIndex('achievement_id');
        $foodsaverHasAchievementTable->addIndex('valid_until');

        $foodsaverHasAchievementTable->create();
    }
}
