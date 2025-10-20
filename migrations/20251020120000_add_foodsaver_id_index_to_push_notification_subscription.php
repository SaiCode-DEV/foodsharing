<?php

use Phinx\Migration\AbstractMigration;

class AddFoodsaverIdIndexToPushNotificationSubscription extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('fs_push_notification_subscription');
        // Add a non-unique index on foodsaver_id to speed up lookups by
        // foodsaver when sending push notifications. This query is called very
        // often (> 600 / minute during peak hours)
        if (!$table->hasIndex(['foodsaver_id'])) {
            $table->addIndex(['foodsaver_id'], ['name' => 'idx_fs_push_notification_subscription_foodsaver_id'])
                  ->update();
        }
    }
}
