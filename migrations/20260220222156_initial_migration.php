<?php

use Phinx\Db\Adapter\MysqlAdapter;

class InitialMigration extends Phinx\Migration\AbstractMigration
{
    public function change()
    {
        $this->execute('SET unique_checks=0; SET foreign_key_checks=0;');
        $this->execute("ALTER DATABASE CHARACTER SET 'utf8mb4';");
        $this->execute("ALTER DATABASE COLLATE='utf8mb4_unicode_ci';");
        $this->table('fs_email_bounces', [
            'id' => false,
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('email', 'string', [
                'null' => false,
                'limit' => 255,
            ])
            ->addColumn('bounced_at', 'datetime', [
                'null' => false,
                'after' => 'email',
            ])
            ->addColumn('bounce_category', 'string', [
                'null' => false,
                'limit' => 255,
                'after' => 'bounced_at',
            ])
            ->addIndex(['email'], [
                'name' => 'email',
                'unique' => false,
            ])
            ->create();
        $this->table('fs_basket_has_art', [
            'id' => false,
            'primary_key' => ['basket_id', 'art_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('basket_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('art_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'basket_id',
            ])
            ->create();
        $this->table('fs_basket_has_types', [
            'id' => false,
            'primary_key' => ['basket_id', 'types_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('basket_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('types_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'basket_id',
            ])
            ->create();
        $this->table('fs_fetchweight', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('weight', 'decimal', [
                'null' => false,
                'precision' => 5,
                'scale' => 1,
                'after' => 'id',
            ])
            ->create();
        $this->table('fs_email_blacklist', [
            'id' => false,
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('email', 'string', [
                'null' => false,
                'limit' => 255,
            ])
            ->addColumn('since', 'timestamp', [
                'null' => false,
                'default' => 'current_timestamp()',
                'after' => 'email',
            ])
            ->addColumn('reason', 'text', [
                'null' => false,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'since',
            ])
            ->create();
        $this->table('fs_stat_abholmengen', [
            'id' => false,
            'primary_key' => ['betrieb_id', 'date'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('betrieb_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('date', 'datetime', [
                'null' => false,
                'after' => 'betrieb_id',
            ])
            ->addColumn('abholmenge', 'decimal', [
                'null' => false,
                'precision' => 5,
                'scale' => 1,
                'after' => 'date',
            ])
            ->addIndex(['betrieb_id', 'date'], [
                'name' => 'betrieb_id',
                'unique' => true,
            ])
            ->create();
        $this->table('fs_contact', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('name', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 180,
                'after' => 'id',
            ])
            ->addColumn('email', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 180,
                'after' => 'name',
            ])
            ->addIndex(['email'], [
                'name' => 'email',
                'unique' => true,
            ])
            ->create();
        $this->table('fs_content', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('name', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 20,
                'after' => 'id',
            ])
            ->addColumn('title', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 120,
                'after' => 'name',
            ])
            ->addColumn('body', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'title',
            ])
            ->addColumn('last_mod', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'body',
            ])
            ->create();
        $this->table('fs_betrieb_has_lebensmittel', [
            'id' => false,
            'primary_key' => ['betrieb_id', 'lebensmittel_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('betrieb_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('lebensmittel_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'betrieb_id',
            ])
            ->create();
        $this->table('fs_betrieb_notiz', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'id',
            ])
            ->addColumn('betrieb_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('milestone', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'betrieb_id',
            ])
            ->addColumn('text', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'milestone',
            ])
            ->addColumn('zeit', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'text',
            ])
            ->addColumn('last', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'zeit',
            ])
            ->addIndex(['betrieb_id'], [
                'name' => 'betrieb_notitz_FKIndex1',
                'unique' => false,
            ])
            ->addIndex(['foodsaver_id'], [
                'name' => 'betrieb_notiz_FKIndex2',
                'unique' => false,
            ])
            ->create();
        $this->table('fs_blog_entry', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('bezirk_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'id',
            ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'bezirk_id',
            ])
            ->addColumn('active', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('name', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 100,
                'after' => 'active',
            ])
            ->addColumn('teaser', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 500,
                'after' => 'name',
            ])
            ->addColumn('body', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'teaser',
            ])
            ->addColumn('time', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'body',
            ])
            ->addColumn('picture', 'string', [
                'null' => false,
                'limit' => 150,
                'after' => 'time',
            ])
            ->addIndex(['foodsaver_id'], [
                'name' => 'blog_entry_FKIndex1',
                'unique' => false,
            ])
            ->addIndex(['bezirk_id'], [
                'name' => 'blog_entry_FKIndex2',
                'unique' => false,
            ])
            ->addIndex(['active'], [
                'name' => 'active',
                'unique' => false,
            ])
            ->create();
        $this->table('fs_mailbox', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('name', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 50,
                'after' => 'id',
            ])
            ->addColumn('member', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'name',
            ])
            ->addColumn('last_access', 'datetime', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
                'after' => 'member',
            ])
            ->addIndex(['name'], [
                'name' => 'email_unique',
                'unique' => true,
            ])
            ->addIndex(['member'], [
                'name' => 'member',
                'unique' => false,
            ])
            ->create();
        $this->table('fs_conversation', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('locked', 'boolean', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'id',
            ])
            ->addColumn('name', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 40,
                'after' => 'locked',
            ])
            ->addColumn('last', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'name',
            ])
            ->addColumn('last_foodsaver_id', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'signed' => false,
                'after' => 'last',
            ])
            ->addColumn('last_message_id', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'signed' => false,
                'after' => 'last_foodsaver_id',
            ])
            ->addColumn('last_message', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'last_message_id',
            ])
            ->addColumn('last_message_is_htmlentity_encoded', 'boolean', [
                'null' => false,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'last_message',
            ])
            ->addIndex(['last_foodsaver_id'], [
                'name' => 'conversation_last_fs_id',
                'unique' => false,
            ])
            ->create();
        $this->table('fs_ipblock', [
            'id' => false,
            'primary_key' => ['ip', 'context'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('ip', 'string', [
                'null' => false,
                'limit' => 20,
            ])
            ->addColumn('context', 'string', [
                'null' => false,
                'limit' => 10,
                'after' => 'ip',
            ])
            ->addColumn('start', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'context',
            ])
            ->addColumn('duration', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'signed' => false,
                'after' => 'start',
            ])
            ->create();
        $this->table('fs_lebensmittel', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('name', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 50,
                'after' => 'id',
            ])
            ->create();
        $this->table('fs_verify_history', [
            'id' => false,
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('fs_id', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('date', 'datetime', [
                'null' => false,
                'after' => 'fs_id',
            ])
            ->addColumn('bot_id', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'signed' => false,
                'after' => 'date',
            ])
            ->addColumn('change_status', 'boolean', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'bot_id',
            ])
            ->addIndex(['fs_id'], [
                'name' => 'fs_id',
                'unique' => false,
            ])
            ->create();
        $this->table('fs_location', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('name', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 200,
                'after' => 'id',
            ])
            ->addColumn('lat', 'decimal', [
                'null' => true,
                'default' => null,
                'precision' => 10,
                'scale' => 8,
                'after' => 'name',
            ])
            ->addColumn('lon', 'decimal', [
                'null' => true,
                'default' => null,
                'precision' => 11,
                'scale' => 8,
                'after' => 'lat',
            ])
            ->addColumn('zip', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'after' => 'lon',
            ])
            ->addColumn('city', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 100,
                'after' => 'zip',
            ])
            ->addColumn('street', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 200,
                'after' => 'city',
            ])
            ->create();
        $this->table('fs_foodsaver_change_history', [
            'id' => false,
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('date', 'timestamp', [
                'null' => false,
                'default' => 'current_timestamp()',
            ])
            ->addColumn('fs_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'after' => 'date',
            ])
            ->addColumn('changer_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'after' => 'fs_id',
            ])
            ->addColumn('object_name', 'text', [
                'null' => false,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'changer_id',
            ])
            ->addColumn('old_value', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'object_name',
            ])
            ->addColumn('new_value', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'old_value',
            ])
            ->addIndex(['fs_id'], [
                'name' => 'fs_id',
                'unique' => false,
            ])
            ->create();
        $this->table('fs_answer', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('question_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'id',
            ])
            ->addColumn('text', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'question_id',
            ])
            ->addColumn('explanation', 'text', [
                'null' => false,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'text',
            ])
            ->addColumn('right', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'explanation',
            ])
            ->addIndex(['question_id'], [
                'name' => 'answer_FKIndex1',
                'unique' => false,
            ])
            ->addForeignKey('question_id', 'fs_question', 'id', [
                'constraint' => 'fs_answer_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_apitoken', [
            'id' => false,
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('token', 'string', [
                'null' => false,
                'limit' => 255,
                'after' => 'foodsaver_id',
            ])
            ->addIndex(['foodsaver_id'], [
                'name' => 'foodsaver_id',
                'unique' => false,
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_apitoken_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_basket_anfrage', [
            'id' => false,
            'primary_key' => ['foodsaver_id', 'basket_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('basket_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('status', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'basket_id',
            ])
            ->addColumn('time', 'datetime', [
                'null' => false,
                'after' => 'status',
            ])
            ->addColumn('appost', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'time',
            ])
            ->addIndex(['foodsaver_id'], [
                'name' => 'foodsaver_has_basket_FKIndex1',
                'unique' => false,
            ])
            ->addIndex(['basket_id'], [
                'name' => 'foodsaver_has_basket_FKIndex2',
                'unique' => false,
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_basket_anfrage_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('basket_id', 'fs_basket', 'id', [
                'constraint' => 'fs_basket_anfrage_ibfk_2',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_bezirk_closure', [
            'id' => false,
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('bezirk_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('ancestor_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'bezirk_id',
            ])
            ->addColumn('depth', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'ancestor_id',
            ])
            ->addIndex(['ancestor_id'], [
                'name' => 'ancestor_id',
                'unique' => false,
            ])
            ->addIndex(['bezirk_id'], [
                'name' => 'bezirk_id',
                'unique' => false,
            ])
            ->addForeignKey('bezirk_id', 'fs_bezirk', 'id', [
                'constraint' => 'fs_bezirk_closure_ibfk_1',
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('ancestor_id', 'fs_bezirk', 'id', [
                'constraint' => 'fs_bezirk_closure_ibfk_2',
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_bezirk_has_wallpost', [
            'id' => false,
            'primary_key' => ['bezirk_id', 'wallpost_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('bezirk_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('wallpost_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'bezirk_id',
            ])
            ->addIndex(['bezirk_id'], [
                'name' => 'bezirk_id',
                'unique' => false,
            ])
            ->addIndex(['wallpost_id'], [
                'name' => 'wallpost_id',
                'unique' => false,
            ])
            ->addForeignKey('bezirk_id', 'fs_bezirk', 'id', [
                'constraint' => 'fs_bezirk_has_wallpost_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('wallpost_id', 'fs_wallpost', 'id', [
                'constraint' => 'fs_bezirk_has_wallpost_ibfk_2',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_botschafter', [
            'id' => false,
            'primary_key' => ['foodsaver_id', 'bezirk_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('bezirk_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'foodsaver_id',
            ])
            ->addIndex(['foodsaver_id'], [
                'name' => 'foodsaver_has_bezirk_FKIndex1',
                'unique' => false,
            ])
            ->addIndex(['bezirk_id'], [
                'name' => 'foodsaver_has_bezirk_FKIndex2',
                'unique' => false,
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_botschafter_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('bezirk_id', 'fs_bezirk', 'id', [
                'constraint' => 'fs_botschafter_ibfk_2',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_event_has_wallpost', [
            'id' => false,
            'primary_key' => ['event_id', 'wallpost_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('event_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('wallpost_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'event_id',
            ])
            ->addIndex(['event_id'], [
                'name' => 'event_id',
                'unique' => false,
            ])
            ->addIndex(['wallpost_id'], [
                'name' => 'wallpost_id',
                'unique' => false,
            ])
            ->addForeignKey('event_id', 'fs_event', 'id', [
                'constraint' => 'fs_event_has_wallpost_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('wallpost_id', 'fs_wallpost', 'id', [
                'constraint' => 'fs_event_has_wallpost_ibfk_2',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_fairteiler', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('bezirk_id', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'signed' => false,
                'after' => 'id',
            ])
            ->addColumn('name', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 260,
                'after' => 'bezirk_id',
            ])
            ->addColumn('picture', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 100,
                'after' => 'name',
            ])
            ->addColumn('status', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'picture',
            ])
            ->addColumn('desc', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'status',
            ])
            ->addColumn('anschrift', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 260,
                'after' => 'desc',
            ])
            ->addColumn('plz', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 5,
                'after' => 'anschrift',
            ])
            ->addColumn('ort', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 100,
                'after' => 'plz',
            ])
            ->addColumn('lat', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 100,
                'after' => 'ort',
            ])
            ->addColumn('lon', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 100,
                'after' => 'lat',
            ])
            ->addColumn('add_date', 'date', [
                'null' => true,
                'default' => null,
                'after' => 'lon',
            ])
            ->addColumn('add_foodsaver', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'signed' => false,
                'after' => 'add_date',
            ])
            ->addIndex(['bezirk_id'], [
                'name' => 'fairteiler_FKIndex1',
                'unique' => false,
            ])
            ->addForeignKey('bezirk_id', 'fs_bezirk', 'id', [
                'constraint' => 'fs_fairteiler_ibfk_1',
                'delete' => 'SET_NULL',
            ])
            ->create();
        $this->table('fs_fairteiler_follower', [
            'id' => false,
            'primary_key' => ['fairteiler_id', 'foodsaver_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('fairteiler_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'fairteiler_id',
            ])
            ->addColumn('type', 'integer', [
                'null' => false,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('infotype', 'integer', [
                'null' => false,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'type',
            ])
            ->addIndex(['fairteiler_id'], [
                'name' => 'fairteiler_verantwortlich_FKIndex1',
                'unique' => false,
            ])
            ->addIndex(['foodsaver_id'], [
                'name' => 'fairteiler_verantwortlich_FKIndex2',
                'unique' => false,
            ])
            ->addIndex(['type'], [
                'name' => 'type',
                'unique' => false,
            ])
            ->addIndex(['infotype'], [
                'name' => 'infotype',
                'unique' => false,
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_fairteiler_follower_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('fairteiler_id', 'fs_fairteiler', 'id', [
                'constraint' => 'fs_fairteiler_follower_ibfk_2',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_fairteiler_has_wallpost', [
            'id' => false,
            'primary_key' => ['fairteiler_id', 'wallpost_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('fairteiler_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('wallpost_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'fairteiler_id',
            ])
            ->addIndex(['fairteiler_id'], [
                'name' => 'fairteiler_has_wallpost_FKIndex1',
                'unique' => false,
            ])
            ->addIndex(['wallpost_id'], [
                'name' => 'fairteiler_has_wallpost_FKIndex2',
                'unique' => false,
            ])
            ->addForeignKey('fairteiler_id', 'fs_fairteiler', 'id', [
                'constraint' => 'fs_fairteiler_has_wallpost_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('wallpost_id', 'fs_wallpost', 'id', [
                'constraint' => 'fs_fairteiler_has_wallpost_ibfk_2',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_foodsaver_has_bell', [
            'id' => false,
            'primary_key' => ['foodsaver_id', 'bell_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('bell_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('seen', 'integer', [
                'null' => true,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'bell_id',
            ])
            ->addIndex(['foodsaver_id'], [
                'name' => 'foodsaver_has_bell_FKIndex1',
                'unique' => false,
            ])
            ->addIndex(['bell_id'], [
                'name' => 'foodsaver_has_bell_FKIndex2',
                'unique' => false,
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_foodsaver_has_bell_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('bell_id', 'fs_bell', 'id', [
                'constraint' => 'fs_foodsaver_has_bell_ibfk_2',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_foodsaver_has_contact', [
            'id' => false,
            'primary_key' => ['foodsaver_id', 'contact_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('contact_id', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
                'after' => 'foodsaver_id',
            ])
            ->addIndex(['contact_id'], [
                'name' => 'contact_id',
                'unique' => false,
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_foodsaver_has_contact_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('contact_id', 'fs_contact', 'id', [
                'constraint' => 'fs_foodsaver_has_contact_ibfk_2',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_foodsaver_has_event', [
            'id' => false,
            'primary_key' => ['foodsaver_id', 'event_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('event_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('status', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'event_id',
            ])
            ->addIndex(['foodsaver_id'], [
                'name' => 'foodsaver_has_event_FKIndex1',
                'unique' => false,
            ])
            ->addIndex(['event_id'], [
                'name' => 'foodsaver_has_event_FKIndex2',
                'unique' => false,
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_foodsaver_has_event_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('event_id', 'fs_event', 'id', [
                'constraint' => 'fs_foodsaver_has_event_ibfk_2',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_foodsaver_has_wallpost', [
            'id' => false,
            'primary_key' => ['foodsaver_id', 'wallpost_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('wallpost_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('usercomment', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'wallpost_id',
            ])
            ->addIndex(['foodsaver_id'], [
                'name' => 'foodsaver_has_wallpost_FKIndex1',
                'unique' => false,
            ])
            ->addIndex(['wallpost_id'], [
                'name' => 'foodsaver_has_wallpost_FKIndex2',
                'unique' => false,
            ])
            ->addIndex(['foodsaver_id'], [
                'name' => 'foodsaver_id',
                'unique' => false,
            ])
            ->addIndex(['wallpost_id'], [
                'name' => 'wallpost_id',
                'unique' => false,
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_foodsaver_has_wallpost_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('wallpost_id', 'fs_wallpost', 'id', [
                'constraint' => 'fs_foodsaver_has_wallpost_ibfk_2',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_mailbox_message', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('mailbox_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'id',
            ])
            ->addColumn('folder', 'integer', [
                'null' => true,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'mailbox_id',
            ])
            ->addColumn('sender', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'after' => 'folder',
            ])
            ->addColumn('to', 'text', [
                'null' => false,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'sender',
            ])
            ->addColumn('subject', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'after' => 'to',
            ])
            ->addColumn('body', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'subject',
            ])
            ->addColumn('body_html', 'text', [
                'null' => false,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'body',
            ])
            ->addColumn('time', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'body_html',
            ])
            ->addColumn('attach', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'time',
            ])
            ->addColumn('read', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'attach',
            ])
            ->addColumn('answer', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'read',
            ])
            ->addIndex(['folder'], [
                'name' => 'email_message_folder',
                'unique' => false,
            ])
            ->addIndex(['mailbox_id', 'read'], [
                'name' => 'mailbox_message_FKIndex1',
                'unique' => false,
            ])
            ->addForeignKey('mailbox_id', 'fs_mailbox', 'id', [
                'constraint' => 'fs_mailbox_message_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_mailchange', [
            'id' => false,
            'primary_key' => ['foodsaver_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('newmail', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 200,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('time', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'newmail',
            ])
            ->addColumn('token', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 300,
                'after' => 'time',
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_mailchange_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_msg', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('conversation_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'id',
            ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'conversation_id',
            ])
            ->addColumn('body', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('time', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'body',
            ])
            ->addColumn('is_htmlentity_encoded', 'boolean', [
                'null' => false,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'time',
            ])
            ->addIndex(['foodsaver_id'], [
                'name' => 'message_FKIndex1',
                'unique' => false,
            ])
            ->addIndex(['conversation_id', 'time'], [
                'name' => 'message_conversationTimeIndex',
                'unique' => false,
            ])
            ->addForeignKey('conversation_id', 'fs_conversation', 'id', [
                'constraint' => 'fs_msg_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_pass_gen', [
            'id' => false,
            'primary_key' => ['foodsaver_id', 'date'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('date', 'datetime', [
                'null' => false,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('bot_id', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'signed' => false,
                'after' => 'date',
            ])
            ->addIndex(['bot_id'], [
                'name' => 'bot_id',
                'unique' => false,
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_pass_gen_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('bot_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_pass_gen_ibfk_2',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_pass_request', [
            'id' => false,
            'primary_key' => ['foodsaver_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('name', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 50,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('time', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'name',
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_pass_request_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_post_reaction', [
            'id' => false,
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('post_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('time', 'datetime', [
                'null' => false,
                'after' => 'post_id',
            ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'after' => 'time',
            ])
            ->addColumn('key', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 63,
                'after' => 'foodsaver_id',
            ])
            ->addIndex(['post_id', 'foodsaver_id', 'key'], [
                'name' => 'post-foodsaver-key',
                'unique' => true,
            ])
            ->addIndex(['post_id'], [
                'name' => 'post_id',
                'unique' => false,
            ])
            ->addForeignKey('post_id', 'fs_theme_post', 'id', [
                'constraint' => 'fs_post_reaction_ibfk_1',
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_question_has_quiz', [
            'id' => false,
            'primary_key' => ['question_id', 'quiz_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('question_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('quiz_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'question_id',
            ])
            ->addColumn('fp', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'quiz_id',
            ])
            ->addIndex(['question_id'], [
                'name' => 'question_has_quiz_FKIndex1',
                'unique' => false,
            ])
            ->addIndex(['quiz_id'], [
                'name' => 'question_has_quiz_FKIndex2',
                'unique' => false,
            ])
            ->addForeignKey('question_id', 'fs_question', 'id', [
                'constraint' => 'fs_question_has_quiz_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('quiz_id', 'fs_quiz', 'id', [
                'constraint' => 'fs_question_has_quiz_ibfk_2',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_question_has_wallpost', [
            'id' => false,
            'primary_key' => ['question_id', 'wallpost_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('question_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('wallpost_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'question_id',
            ])
            ->addColumn('usercomment', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'wallpost_id',
            ])
            ->addIndex(['question_id'], [
                'name' => 'question_has_wallpost_FKIndex1',
                'unique' => false,
            ])
            ->addIndex(['wallpost_id'], [
                'name' => 'question_has_wallpost_FKIndex2',
                'unique' => false,
            ])
            ->addIndex(['question_id'], [
                'name' => 'question_id',
                'unique' => false,
            ])
            ->addIndex(['wallpost_id'], [
                'name' => 'wallpost_id',
                'unique' => false,
            ])
            ->addForeignKey('question_id', 'fs_question', 'id', [
                'constraint' => 'fs_question_has_wallpost_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('wallpost_id', 'fs_wallpost', 'id', [
                'constraint' => 'fs_question_has_wallpost_ibfk_2',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_theme_follower', [
            'id' => false,
            'primary_key' => ['foodsaver_id', 'theme_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('theme_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('infotype', 'boolean', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'theme_id',
            ])
            ->addColumn('bell_notification', 'boolean', [
                'null' => false,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'infotype',
            ])
            ->addIndex(['infotype'], [
                'name' => 'infotype',
                'unique' => false,
            ])
            ->addIndex(['theme_id'], [
                'name' => 'theme_id',
                'unique' => false,
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_theme_follower_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('theme_id', 'fs_theme', 'id', [
                'constraint' => 'fs_theme_follower_ibfk_2',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_usernotes_has_wallpost', [
            'id' => false,
            'primary_key' => ['usernotes_id', 'wallpost_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('usernotes_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('wallpost_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'usernotes_id',
            ])
            ->addColumn('usercomment', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'wallpost_id',
            ])
            ->addIndex(['usernotes_id'], [
                'name' => 'usernotes_has_wallpost_FKIndex1',
                'unique' => false,
            ])
            ->addIndex(['wallpost_id'], [
                'name' => 'usernotes_has_wallpost_FKIndex2',
                'unique' => false,
            ])
            ->addIndex(['usernotes_id'], [
                'name' => 'usernotes_id',
                'unique' => false,
            ])
            ->addIndex(['wallpost_id'], [
                'name' => 'wallpost_id',
                'unique' => false,
            ])
            ->addForeignKey('wallpost_id', 'fs_wallpost', 'id', [
                'constraint' => 'fs_usernotes_has_wallpost_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_rating', [
            'id' => false,
            'primary_key' => ['foodsaver_id', 'rater_id'],
            'engine' => 'InnoDB',
            'comment' => 'ratingtype 1+2 = bananen, 4+5 = betriebsmeldung',
        ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('rater_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('msg', 'text', [
                'null' => false,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'rater_id',
            ])
            ->addColumn('time', 'datetime', [
                'null' => false,
                'after' => 'msg',
            ])
            ->addIndex(['rater_id'], [
                'name' => 'fk_foodsaver_has_foodsaver_foodsaver1_idx',
                'unique' => false,
            ])
            ->addIndex(['foodsaver_id'], [
                'name' => 'fk_foodsaver_has_foodsaver_foodsaver_idx',
                'unique' => false,
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_rating_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('rater_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_rating_ibfk_2',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_poll_option_has_value', [
            'id' => false,
            'primary_key' => ['poll_id', 'option', 'value'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('poll_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'comment' => 'the poll to which the option belongs',
            ])
            ->addColumn('option', 'integer', [
                'null' => false,
                'limit' => 2,
                'signed' => false,
                'comment' => 'index of the option',
                'after' => 'poll_id',
            ])
            ->addColumn('value', 'integer', [
                'null' => false,
                'limit' => 2,
                'comment' => 'value for the option',
                'after' => 'option',
            ])
            ->addColumn('votes', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
                'comment' => 'number of current votes for the value',
                'after' => 'value',
            ])
            ->addForeignKey('poll_id', 'fs_poll', 'id', [
                'constraint' => 'fs_poll_option_has_value_ibfk_1',
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_foodsaver_has_poll', [
            'id' => false,
            'primary_key' => ['foodsaver_id', 'poll_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'comment' => 'id of the voter',
            ])
            ->addColumn('poll_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'comment' => 'id of the poll',
                'after' => 'foodsaver_id',
            ])
            ->addColumn('time', 'datetime', [
                'null' => true,
                'default' => null,
                'update' => 'CURRENT_TIMESTAMP',
                'comment' => 'time at which the voter has voted, null if not voted yet',
                'after' => 'poll_id',
            ])
            ->addIndex(['poll_id'], [
                'name' => 'poll_id',
                'unique' => false,
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_foodsaver_has_poll_ibfk_1',
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('poll_id', 'fs_poll', 'id', [
                'constraint' => 'fs_foodsaver_has_poll_ibfk_2',
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_foodsaver_has_options', [
            'id' => false,
            'primary_key' => ['foodsaver_id', 'option_type'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('option_type', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'comment' => 'category of the option',
                'after' => 'foodsaver_id',
            ])
            ->addColumn('option_value', 'string', [
                'null' => false,
                'limit' => 255,
                'comment' => 'value of the option',
                'after' => 'option_type',
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_foodsaver_has_options_ibfk_1',
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_region_options', [
            'id' => false,
            'primary_key' => ['region_id', 'option_type'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('region_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('option_type', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'comment' => 'category of the option',
                'after' => 'region_id',
            ])
            ->addColumn('option_value', 'string', [
                'null' => false,
                'limit' => 255,
                'comment' => 'value of the option',
                'after' => 'option_type',
            ])
            ->addForeignKey('region_id', 'fs_bezirk', 'id', [
                'constraint' => 'fs_region_options_ibfk_1',
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_region_pin', [
            'id' => false,
            'primary_key' => ['region_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('region_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'comment' => 'region id',
            ])
            ->addColumn('lat', 'string', [
                'null' => false,
                'limit' => 20,
                'comment' => 'latitude',
                'after' => 'region_id',
            ])
            ->addColumn('lon', 'string', [
                'null' => false,
                'limit' => 20,
                'comment' => 'longitude',
                'after' => 'lat',
            ])
            ->addColumn('desc', 'text', [
                'null' => false,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'comment' => 'description',
                'after' => 'lon',
            ])
            ->addColumn('status', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'comment' => 'state of the pin',
                'after' => 'desc',
            ])
            ->addForeignKey('region_id', 'fs_bezirk', 'id', [
                'constraint' => 'fs_region_pin_ibfk_1',
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_key_account_manager', [
            'id' => false,
            'primary_key' => ['foodsaver_id', 'chain_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('chain_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'foodsaver_id',
            ])
            ->addIndex(['chain_id'], [
                'name' => 'chain_id',
                'unique' => false,
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_key_account_manager_ibfk_1',
            ])
            ->addForeignKey('chain_id', 'fs_chain', 'id', [
                'constraint' => 'fs_key_account_manager_ibfk_2',
            ])
            ->create();
        $this->table('fs_chain', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
                'comment' => 'unique id of the chain',
            ])
            ->addColumn('name', 'string', [
                'null' => false,
                'limit' => 120,
                'after' => 'id',
            ])
            ->addColumn('headquarters_zip', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 5,
                'after' => 'name',
            ])
            ->addColumn('headquarters_city', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 50,
                'after' => 'headquarters_zip',
            ])
            ->addColumn('status', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'headquarters_city',
            ])
            ->addColumn('modification_date', 'date', [
                'null' => false,
                'after' => 'status',
            ])
            ->addColumn('allow_press', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'modification_date',
            ])
            ->addColumn('forum_thread', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'signed' => false,
                'comment' => 'id of the chains forum thread',
                'after' => 'allow_press',
            ])
            ->addColumn('notes', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 200,
                'comment' => 'Only visibe in the chain table',
                'after' => 'forum_thread',
            ])
            ->addColumn('common_store_information', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'comment' => 'Details displayed on store pages',
                'after' => 'notes',
            ])
            ->addColumn('estimated_store_count', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 6,
                'signed' => false,
                'after' => 'common_store_information',
            ])
            ->addColumn('headquarters_country', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 50,
                'after' => 'estimated_store_count',
            ])
            ->addIndex(['forum_thread'], [
                'name' => 'forum_thread',
                'unique' => false,
            ])
            ->addIndex(['name'], [
                'name' => 'name',
                'unique' => false,
                'type' => 'fulltext',
            ])
            ->addIndex(['notes'], [
                'name' => 'notes',
                'unique' => false,
                'type' => 'fulltext',
            ])
            ->addIndex(['common_store_information'], [
                'name' => 'common_store_information',
                'unique' => false,
                'type' => 'fulltext',
            ])
            ->addIndex(['headquarters_city'], [
                'name' => 'headquarters_city',
                'unique' => false,
                'type' => 'fulltext',
            ])
            ->addForeignKey('forum_thread', 'fs_theme', 'id', [
                'constraint' => 'fs_chain_ibfk_1',
                'delete' => 'SET_NULL',
            ])
            ->create();
        $this->table('fs_abholzeiten', [
            'id' => false,
            'primary_key' => ['betrieb_id', 'dow', 'time'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('betrieb_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('dow', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'betrieb_id',
            ])
            ->addColumn('time', 'time', [
                'null' => false,
                'default' => '00:00:00',
                'after' => 'dow',
            ])
            ->addColumn('fetcher', 'integer', [
                'null' => false,
                'default' => '4',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'time',
            ])
            ->addColumn('description', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 100,
                'comment' => 'optional description for this pickup time',
                'after' => 'fetcher',
            ])
            ->create();
        $this->table('fs_fetchdate', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('betrieb_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'id',
            ])
            ->addColumn('time', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'betrieb_id',
            ])
            ->addColumn('fetchercount', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'time',
            ])
            ->addColumn('description', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 100,
                'comment' => 'optional description for this pickup',
                'after' => 'fetchercount',
            ])
            ->addIndex(['betrieb_id', 'time'], [
                'name' => 'betrieb_id',
                'unique' => true,
            ])
            ->addIndex(['betrieb_id'], [
                'name' => 'fetchdate_FKIndex1',
                'unique' => false,
            ])
            ->create();
        $this->table('fs_report', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'id',
            ])
            ->addColumn('reporter_id', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'signed' => false,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('reporttype', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'reporter_id',
            ])
            ->addColumn('betrieb_id', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'signed' => false,
                'after' => 'reporttype',
            ])
            ->addColumn('time', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'betrieb_id',
            ])
            ->addColumn('committed', 'integer', [
                'null' => true,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'time',
            ])
            ->addColumn('msg', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'committed',
            ])
            ->addColumn('tvalue', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 300,
                'after' => 'msg',
            ])
            ->addColumn('report_reason_id', 'integer', [
                'null' => false,
                'default' => '1',
                'limit' => 6,
                'signed' => false,
                'comment' => 'Report Reason ID',
                'after' => 'tvalue',
            ])
            ->addIndex(['foodsaver_id'], [
                'name' => 'report_FKIndex1',
                'unique' => false,
            ])
            ->addIndex(['reporter_id'], [
                'name' => 'report_reporter',
                'unique' => false,
            ])
            ->addIndex(['betrieb_id'], [
                'name' => 'report_betrieb',
                'unique' => false,
            ])
            ->create();
        $this->table('fs_quiz', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('name', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 200,
                'after' => 'id',
            ])
            ->addColumn('desc', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'name',
            ])
            ->addColumn('is_desc_htmlentity_encoded', 'boolean', [
                'null' => false,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
                'comment' => 'Whether the quiz description is html encoded.',
                'after' => 'desc',
            ])
            ->addColumn('maxfp', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_SMALL,
                'signed' => false,
                'after' => 'is_desc_htmlentity_encoded',
            ])
            ->addColumn('questcount', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_SMALL,
                'signed' => false,
                'after' => 'maxfp',
            ])
            ->addColumn('questcount_untimed', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_SMALL,
                'signed' => false,
                'comment' => 'number of questions that need to be answered when not using a time limit. Can be null to disable untimed quizzes.',
                'after' => 'questcount',
            ])
            ->create();
        $this->table('fs_quiz_session', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'id',
            ])
            ->addColumn('quiz_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('status', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'quiz_id',
            ])
            ->addColumn('quiz_index', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'status',
            ])
            ->addColumn('quiz_questions', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'quiz_index',
            ])
            ->addColumn('quiz_result', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'quiz_questions',
            ])
            ->addColumn('time_start', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'quiz_result',
            ])
            ->addColumn('time_end', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'time_start',
            ])
            ->addColumn('fp', 'decimal', [
                'null' => true,
                'default' => null,
                'precision' => 5,
                'scale' => 2,
                'after' => 'time_end',
            ])
            ->addColumn('maxfp', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'fp',
            ])
            ->addColumn('quest_count', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'maxfp',
            ])
            ->addColumn('easymode', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'quest_count',
            ])
            ->addColumn('is_test', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'comment' => 'Whether this quiz session is only for testing purposes',
                'after' => 'easymode',
            ])
            ->addIndex(['quiz_id'], [
                'name' => 'quiz_result_FKIndex1',
                'unique' => false,
            ])
            ->addIndex(['foodsaver_id'], [
                'name' => 'quiz_result_FKIndex2',
                'unique' => false,
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_quiz_session_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_store_has_wallpost', [
            'id' => false,
            'primary_key' => ['store_id', 'wallpost_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('store_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('wallpost_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'store_id',
            ])
            ->addIndex(['wallpost_id'], [
                'name' => 'wallpost_id',
                'unique' => false,
            ])
            ->addIndex(['store_id'], [
                'name' => 'store_id',
                'unique' => false,
            ])
            ->addForeignKey('store_id', 'fs_betrieb', 'id', [
                'constraint' => 'fs_store_has_wallpost_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('wallpost_id', 'fs_wallpost', 'id', [
                'constraint' => 'fs_store_has_wallpost_ibfk_2',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_poll_has_options', [
            'id' => false,
            'primary_key' => ['poll_id', 'option'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('poll_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'comment' => 'the poll to which this option belongs',
            ])
            ->addColumn('option', 'integer', [
                'null' => false,
                'limit' => 2,
                'signed' => false,
                'comment' => 'index of the option',
                'after' => 'poll_id',
            ])
            ->addColumn('option_text', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 1000,
                'after' => 'option',
            ])
            ->addForeignKey('poll_id', 'fs_poll', 'id', [
                'constraint' => 'fs_poll_has_options_ibfk_1',
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_question', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('text', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'id',
            ])
            ->addColumn('duration', 'integer', [
                'null' => false,
                'limit' => 3,
                'signed' => false,
                'after' => 'text',
            ])
            ->addColumn('wikilink', 'string', [
                'null' => false,
                'limit' => 250,
                'after' => 'duration',
            ])
            ->addColumn('is_mandatory', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'wikilink',
            ])
            ->create();
        $this->table('fs_event', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'id',
            ])
            ->addColumn('bezirk_id', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'signed' => false,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('location_id', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'signed' => false,
                'after' => 'bezirk_id',
            ])
            ->addColumn('public', 'boolean', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'location_id',
            ])
            ->addColumn('name', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 200,
                'after' => 'public',
            ])
            ->addColumn('start', 'datetime', [
                'null' => false,
                'after' => 'name',
            ])
            ->addColumn('end', 'datetime', [
                'null' => false,
                'after' => 'start',
            ])
            ->addColumn('description', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'end',
            ])
            ->addColumn('bot', 'integer', [
                'null' => true,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'description',
            ])
            ->addColumn('online', 'integer', [
                'null' => true,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'bot',
            ])
            ->addColumn('is_public', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'online',
            ])
            ->addIndex(['location_id'], [
                'name' => 'event_FKIndex1',
                'unique' => false,
            ])
            ->addIndex(['bezirk_id'], [
                'name' => 'event_FKIndex2',
                'unique' => false,
            ])
            ->addIndex(['foodsaver_id'], [
                'name' => 'event_FKIndex3',
                'unique' => false,
            ])
            ->addForeignKey('bezirk_id', 'fs_bezirk', 'id', [
                'constraint' => 'fs_event_ibfk_1',
                'delete' => 'SET_NULL',
            ])
            ->addForeignKey('location_id', 'fs_location', 'id', [
                'constraint' => 'fs_event_ibfk_2',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_foodsaver_has_bezirk', [
            'id' => false,
            'primary_key' => ['foodsaver_id', 'bezirk_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('bezirk_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('active', 'integer', [
                'null' => true,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
                'comment' => '0=beworben,1=aktiv,10=vielleicht',
                'after' => 'bezirk_id',
            ])
            ->addColumn('added', 'datetime', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
                'after' => 'active',
            ])
            ->addColumn('application', 'text', [
                'null' => false,
                'default' => '',
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'added',
            ])
            ->addColumn('notify_by_email_about_new_threads', 'integer', [
                'null' => false,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'comment' => 'Emails from new forum threads in regions and working groups can be disabled.',
                'after' => 'application',
            ])
            ->addColumn('notify_on_all_new_threads', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'comment' => 'Whether to send a bell for every new thread in the forum. NULL for default value.',
                'after' => 'notify_by_email_about_new_threads',
            ])
            ->addIndex(['foodsaver_id'], [
                'name' => 'foodsaver_has_bezirk_FKIndex1',
                'unique' => false,
            ])
            ->addIndex(['bezirk_id'], [
                'name' => 'foodsaver_has_bezirk_FKIndex2',
                'unique' => false,
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_foodsaver_has_bezirk_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('bezirk_id', 'fs_bezirk', 'id', [
                'constraint' => 'fs_foodsaver_has_bezirk_ibfk_2',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_wall_post_reaction', [
            'id' => false,
            'primary_key' => ['post_id', 'foodsaver_id', 'key'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('post_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'post_id',
            ])
            ->addColumn('key', 'string', [
                'null' => false,
                'limit' => 63,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('time', 'datetime', [
                'null' => false,
                'after' => 'key',
            ])
            ->addIndex(['post_id', 'foodsaver_id', 'key'], [
                'name' => 'post_id',
                'unique' => true,
            ])
            ->addIndex(['post_id'], [
                'name' => 'post_id_2',
                'unique' => false,
            ])
            ->addIndex(['foodsaver_id'], [
                'name' => 'foodsaver_id',
                'unique' => false,
            ])
            ->addForeignKey('post_id', 'fs_wallpost', 'id', [
                'constraint' => 'fs_wall_post_reaction_ibfk_1',
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_wall_post_reaction_ibfk_2',
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_betrieb_kategorie', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('name', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 50,
                'after' => 'id',
            ])
            ->addColumn('type', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'comment' => 'Type of the category: 0 = pickup, 1 = giving, 2 = orga',
                'after' => 'name',
            ])
            ->create();
        $this->table('fs_region_statistics', [
            'id' => false,
            'primary_key' => ['region_id'],
            'engine' => 'InnoDB',
            'comment' => 'Contains statistics for each region which are precomputed regularly',
        ])
            ->addColumn('region_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'comment' => 'Id of the region, referring to table fs_bezirk.',
            ])
            ->addColumn('last_modified', 'datetime', [
                'null' => true,
                'default' => null,
                'comment' => 'The last time that the statistics for the region were updated',
                'after' => 'region_id',
            ])
            ->addColumn('active_home_region_foodsavers', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
                'comment' => 'Number of verified foodsavers with home region within the region that logged in within the last month',
                'after' => 'last_modified',
            ])
            ->addColumn('active_coorporations', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
                'comment' => 'Number of currently cooperating stores',
                'after' => 'active_home_region_foodsavers',
            ])
            ->addColumn('pickups_last_month', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
                'comment' => 'Number of filled pickup slots in the last month',
                'after' => 'active_coorporations',
            ])
            ->addColumn('saved_food_weight_last_month', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
                'comment' => 'Weight of saved food of the last month. Rounded to full kg.',
                'after' => 'pickups_last_month',
            ])
            ->addColumn('active_food_share_points', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_MEDIUM,
                'signed' => false,
                'comment' => 'Number of active food share points',
                'after' => 'saved_food_weight_last_month',
            ])
            ->addColumn('food_baskets_last_month', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_MEDIUM,
                'signed' => false,
                'comment' => 'Number of foodbaskets in the last month',
                'after' => 'active_food_share_points',
            ])
            ->addForeignKey('region_id', 'fs_bezirk', 'id', [
                'constraint' => 'fs_region_statistics_ibfk_1',
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_resource_category', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('name', 'string', [
                'null' => false,
                'limit' => 35,
                'after' => 'id',
            ])
            ->create();
        $this->table('fs_resource_has_category', [
            'id' => false,
            'primary_key' => ['resource_id', 'category_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('resource_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('category_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'resource_id',
            ])
            ->addIndex(['category_id'], [
                'name' => 'category_id',
                'unique' => false,
            ])
            ->addForeignKey('resource_id', 'fs_resource', 'id', [
                'constraint' => 'fs_resource_has_category_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('category_id', 'fs_resource_category', 'id', [
                'constraint' => 'fs_resource_has_category_ibfk_2',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_foodsaver_has_favorite_resource', [
            'id' => false,
            'primary_key' => ['foodsaver_id', 'resource_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('resource_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'foodsaver_id',
            ])
            ->addIndex(['foodsaver_id'], [
                'name' => 'foodsaver_id',
                'unique' => false,
            ])
            ->addIndex(['resource_id'], [
                'name' => 'resource_id',
                'unique' => false,
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_foodsaver_has_favorite_resource_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('resource_id', 'fs_resource', 'id', [
                'constraint' => 'fs_foodsaver_has_favorite_resource_ibfk_2',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_poll', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
                'comment' => 'unique id of the poll',
            ])
            ->addColumn('region_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'comment' => 'region with which the poll is associated',
                'after' => 'id',
            ])
            ->addColumn('name', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 200,
                'comment' => 'title of the poll',
                'after' => 'region_id',
            ])
            ->addColumn('description', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'comment' => 'description of the poll',
                'after' => 'name',
            ])
            ->addColumn('scope', 'integer', [
                'null' => false,
                'limit' => 2,
                'signed' => false,
                'comment' => 'determines who will be invited to vote',
                'after' => 'description',
            ])
            ->addColumn('type', 'integer', [
                'null' => false,
                'limit' => 2,
                'signed' => false,
                'comment' => 'determines how a vote is cast and which values are possible for each option',
                'after' => 'scope',
            ])
            ->addColumn('start', 'datetime', [
                'null' => false,
                'comment' => 'start timestamp for the poll',
                'after' => 'type',
            ])
            ->addColumn('end', 'datetime', [
                'null' => false,
                'comment' => 'end timestamp for the poll',
                'after' => 'start',
            ])
            ->addColumn('author', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'comment' => 'id of the user who created the poll',
                'after' => 'end',
            ])
            ->addColumn('creation_timestamp', 'datetime', [
                'null' => false,
                'after' => 'author',
            ])
            ->addColumn('votes', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
                'comment' => 'number of users who have voted',
                'after' => 'creation_timestamp',
            ])
            ->addColumn('cancelled_by', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'signed' => false,
                'comment' => 'id of the user who cancelled the poll',
                'after' => 'votes',
            ])
            ->addColumn('eligible_votes_count', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
                'comment' => 'number of users who are eligible to vote',
                'after' => 'cancelled_by',
            ])
            ->addColumn('shuffle_options', 'integer', [
                'null' => false,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'eligible_votes_count',
            ])
            ->addColumn('notifications_sent', 'integer', [
                'null' => false,
                'default' => '2',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'shuffle_options',
            ])
            ->addIndex(['region_id'], [
                'name' => 'region_id',
                'unique' => false,
            ])
            ->addIndex(['notifications_sent', 'start'], [
                'name' => 'idx_poll_start_notification',
                'unique' => false,
            ])
            ->addIndex(['notifications_sent', 'end'], [
                'name' => 'idx_poll_end_notification',
                'unique' => false,
            ])
            ->addForeignKey('region_id', 'fs_bezirk', 'id', [
                'constraint' => 'fs_poll_ibfk_1',
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_abholer', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('betrieb_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('date', 'datetime', [
                'null' => false,
                'after' => 'betrieb_id',
            ])
            ->addColumn('confirmed', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'date',
            ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
                'after' => 'confirmed',
            ])
            ->addIndex(['foodsaver_id', 'betrieb_id', 'date'], [
                'name' => 'foodsaver_id',
                'unique' => true,
            ])
            ->addIndex(['betrieb_id'], [
                'name' => 'betrieb_id',
                'unique' => false,
            ])
            ->addIndex(['betrieb_id', 'date'], [
                'name' => 'idx_betrieb_date',
                'unique' => false,
            ])
            ->create();
        $this->table('fs_buddy', [
            'id' => false,
            'primary_key' => ['foodsaver_id', 'buddy_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('buddy_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('confirmed', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'buddy_id',
            ])
            ->addIndex(['confirmed'], [
                'name' => 'buddy_confirmed',
                'unique' => false,
            ])
            ->addIndex(['buddy_id'], [
                'name' => 'buddy_id',
                'unique' => false,
            ])
            ->addIndex(['buddy_id', 'foodsaver_id'], [
                'name' => 'idx_buddy_id_foodsaver_id',
                'unique' => false,
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_buddy_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('buddy_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_buddy_ibfk_2',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_basket', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'id',
            ])
            ->addColumn('status', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('time', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'status',
            ])
            ->addColumn('update', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'time',
            ])
            ->addColumn('until', 'datetime', [
                'null' => false,
                'after' => 'update',
            ])
            ->addColumn('fetchtime', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'until',
            ])
            ->addColumn('description', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'fetchtime',
            ])
            ->addColumn('picture', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'after' => 'description',
            ])
            ->addColumn('tel', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 50,
                'after' => 'picture',
            ])
            ->addColumn('handy', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 50,
                'after' => 'tel',
            ])
            ->addColumn('contact_type', 'string', [
                'null' => false,
                'default' => '1',
                'limit' => 20,
                'after' => 'handy',
            ])
            ->addColumn('location_type', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'contact_type',
            ])
            ->addColumn('weight', 'float', [
                'null' => true,
                'default' => null,
                'after' => 'location_type',
            ])
            ->addColumn('lat', 'float', [
                'null' => false,
                'precision' => 10,
                'scale' => 6,
                'default' => '0.000000',
                'after' => 'weight',
            ])
            ->addColumn('lon', 'float', [
                'null' => false,
                'precision' => 10,
                'scale' => 6,
                'default' => '0.000000',
                'after' => 'lat',
            ])
            ->addColumn('bezirk_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'lon',
            ])
            ->addColumn('appost', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'bezirk_id',
            ])
            ->addIndex(['foodsaver_id'], [
                'name' => 'basket_FKIndex1',
                'unique' => false,
            ])
            ->addIndex(['bezirk_id'], [
                'name' => 'bezirk_id',
                'unique' => false,
            ])
            ->addIndex(['lat', 'lon'], [
                'name' => 'lat',
                'unique' => false,
            ])
            ->addIndex(['status', 'until', 'foodsaver_id'], [
                'name' => 'idx_basket_status_until_fs',
                'unique' => false,
            ])
            ->create();
        $this->table('fs_resource', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'signed' => false,
                'comment' => 'null if resource is tied to the region (commons)',
                'after' => 'id',
            ])
            ->addColumn('region_id', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'signed' => false,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('name', 'string', [
                'null' => false,
                'limit' => 35,
                'after' => 'region_id',
            ])
            ->addColumn('description', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'after' => 'name',
            ])
            ->addColumn('is_private', 'boolean', [
                'null' => true,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'description',
            ])
            ->addColumn('openness', 'integer', [
                'null' => true,
                'default' => '3',
                'limit' => 1,
                'signed' => false,
                'after' => 'is_private',
            ])
            ->addColumn('images', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'after' => 'openness',
            ])
            ->addIndex(['foodsaver_id'], [
                'name' => 'foodsaver_id',
                'unique' => false,
            ])
            ->addIndex(['region_id'], [
                'name' => 'region_id',
                'unique' => false,
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_resource_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('region_id', 'fs_bezirk', 'id', [
                'constraint' => 'fs_resource_ibfk_2',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_mailbox_member', [
            'id' => false,
            'primary_key' => ['mailbox_id', 'foodsaver_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('mailbox_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'mailbox_id',
            ])
            ->addColumn('email_name', 'string', [
                'null' => false,
                'limit' => 120,
                'after' => 'foodsaver_id',
            ])
            ->addIndex(['mailbox_id'], [
                'name' => 'mailbox_has_foodsaver_FKIndex1',
                'unique' => false,
            ])
            ->addIndex(['foodsaver_id'], [
                'name' => 'mailbox_has_foodsaver_FKIndex2',
                'unique' => false,
            ])
            ->addIndex(['foodsaver_id', 'mailbox_id'], [
                'name' => 'idx_fs_mailbox_member_foodsaver_mailbox',
                'unique' => false,
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_mailbox_member_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('mailbox_id', 'fs_mailbox', 'id', [
                'constraint' => 'fs_mailbox_member_ibfk_2',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_push_notification_subscription', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'after' => 'id',
            ])
            ->addColumn('data', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('type', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 24,
                'after' => 'data',
            ])
            ->addIndex(['foodsaver_id'], [
                'name' => 'idx_fs_push_notification_subscription_foodsaver_id',
                'unique' => false,
            ])
            ->create();
        $this->table('fs_theme', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'id',
            ])
            ->addColumn('last_post_id', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('name', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 260,
                'after' => 'last_post_id',
            ])
            ->addColumn('time', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'name',
            ])
            ->addColumn('active', 'integer', [
                'null' => false,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'time',
            ])
            ->addColumn('sticky', 'boolean', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'active',
            ])
            ->addColumn('status', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
                'comment' => 'status of the thread (open or closed)',
                'after' => 'sticky',
            ])
            ->addIndex(['foodsaver_id'], [
                'name' => 'theme_FKIndex1',
                'unique' => false,
            ])
            ->addIndex(['last_post_id'], [
                'name' => 'last_post_id',
                'unique' => false,
            ])
            ->addIndex(['active'], [
                'name' => 'active',
                'unique' => false,
            ])
            ->addIndex(['active', 'last_post_id'], [
                'name' => 'idx_theme_active_lastpost',
                'unique' => false,
            ])
            ->addIndex(['name'], [
                'name' => 'name',
                'unique' => false,
                'type' => 'fulltext',
            ])
            ->create();
        $this->table('fs_bezirk_has_theme', [
            'id' => false,
            'primary_key' => ['theme_id', 'bezirk_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('theme_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('bezirk_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'theme_id',
            ])
            ->addColumn('bot_theme', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'bezirk_id',
            ])
            ->addIndex(['bezirk_id'], [
                'name' => 'bezirk_id',
                'unique' => false,
            ])
            ->addIndex(['bot_theme', 'bezirk_id', 'theme_id'], [
                'name' => 'idx_bt_bot_bezirk_theme',
                'unique' => false,
            ])
            ->addForeignKey('bezirk_id', 'fs_bezirk', 'id', [
                'constraint' => 'fs_bezirk_has_theme_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('theme_id', 'fs_theme', 'id', [
                'constraint' => 'fs_bezirk_has_theme_ibfk_2',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_foodsaver_has_achievement', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'id',
            ])
            ->addColumn('achievement_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('reviewer_id', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'signed' => false,
                'after' => 'achievement_id',
            ])
            ->addColumn('notice', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'after' => 'reviewer_id',
            ])
            ->addColumn('valid_until', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'notice',
            ])
            ->addColumn('created_at', 'timestamp', [
                'null' => false,
                'default' => 'current_timestamp()',
                'after' => 'valid_until',
            ])
            ->addColumn('updated_at', 'timestamp', [
                'null' => true,
                'default' => null,
                'update' => 'CURRENT_TIMESTAMP',
                'after' => 'created_at',
            ])
            ->addIndex(['foodsaver_id'], [
                'name' => 'foodsaver_id',
                'unique' => false,
            ])
            ->addIndex(['achievement_id'], [
                'name' => 'achievement_id',
                'unique' => false,
            ])
            ->addIndex(['valid_until'], [
                'name' => 'valid_until',
                'unique' => false,
            ])
            ->addIndex(['reviewer_id'], [
                'name' => 'reviewer_id',
                'unique' => false,
            ])
            ->addIndex(['foodsaver_id', 'achievement_id', 'valid_until'], [
                'name' => 'idx_fsa_foodsaver_achievement_valid',
                'unique' => false,
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_foodsaver_has_achievement_ibfk_1',
                'update' => 'NO_ACTION',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('achievement_id', 'fs_achievement', 'id', [
                'constraint' => 'fs_foodsaver_has_achievement_ibfk_2',
                'update' => 'NO_ACTION',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('reviewer_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_foodsaver_has_achievement_ibfk_3',
                'update' => 'NO_ACTION',
                'delete' => 'SET_NULL',
            ])
            ->create();
        $this->table('fs_webauthn_credentials', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'string', [
                'null' => false,
                'limit' => 26,
            ])
            ->addColumn('public_key_credential_id', 'text', [
                'null' => false,
                'limit' => 65535,
                'after' => 'id',
            ])
            ->addColumn('type', 'string', [
                'null' => false,
                'limit' => 255,
                'after' => 'public_key_credential_id',
            ])
            ->addColumn('transports', 'json', [
                'null' => false,
                'limit' => MysqlAdapter::TEXT_LONG,
                'after' => 'type',
            ])
            ->addColumn('attestation_type', 'string', [
                'null' => false,
                'limit' => 255,
                'after' => 'transports',
            ])
            ->addColumn('trust_path', 'text', [
                'null' => false,
                'limit' => 65535,
                'after' => 'attestation_type',
            ])
            ->addColumn('aaguid', 'string', [
                'null' => false,
                'limit' => 36,
                'after' => 'trust_path',
            ])
            ->addColumn('credential_public_key', 'text', [
                'null' => false,
                'limit' => 65535,
                'after' => 'aaguid',
            ])
            ->addColumn('user_handle', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'credential_public_key',
            ])
            ->addColumn('counter', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
                'after' => 'user_handle',
            ])
            ->addColumn('other_ui', 'json', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_LONG,
                'after' => 'counter',
            ])
            ->addColumn('name', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'after' => 'other_ui',
            ])
            ->addColumn('rp_id', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 255,
                'comment' => 'The Relying Party ID (domain) where this passkey was registered',
                'after' => 'name',
            ])
            ->addColumn('created_at', 'datetime', [
                'null' => false,
                'after' => 'rp_id',
            ])
            ->addColumn('last_used_at', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'created_at',
            ])
            ->addIndex(['public_key_credential_id'], [
                'name' => 'idx_public_key_credential_id',
                'unique' => true,
            ])
            ->addIndex(['user_handle'], [
                'name' => 'idx_user_handle',
                'unique' => false,
            ])
            ->addIndex(['user_handle', 'rp_id'], [
                'name' => 'idx_user_handle_rp_id',
                'unique' => false,
            ])
            ->addForeignKey('user_handle', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_webauthn_credentials_ibfk_1',
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('oauth_clients', [
            'id' => false,
            'primary_key' => ['identifier'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('identifier', 'string', [
                'null' => false,
                'limit' => 100,
            ])
            ->addColumn('name', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 190,
                'after' => 'identifier',
            ])
            ->addColumn('confidential', 'boolean', [
                'null' => true,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'name',
            ])
            ->addColumn('active', 'boolean', [
                'null' => true,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'confidential',
            ])
            ->addColumn('redirect_uris', 'text', [
                'null' => false,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'active',
            ])
            ->addColumn('scopes', 'text', [
                'null' => false,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'redirect_uris',
            ])
            ->addColumn('grant_types', 'text', [
                'null' => false,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'scopes',
            ])
            ->addColumn('secret_hash', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'after' => 'grant_types',
            ])
            ->addColumn('required_region_ids', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'secret_hash',
            ])
            ->addColumn('changed_by', 'integer', [
                'null' => true,
                'default' => '0',
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
                'after' => 'required_region_ids',
            ])
            ->addColumn('created_at', 'datetime', [
                'null' => false,
                'after' => 'changed_by',
            ])
            ->addColumn('updated_at', 'datetime', [
                'null' => false,
                'after' => 'created_at',
            ])
            ->create();
        $this->table('oauth_access_tokens', [
            'id' => false,
            'primary_key' => ['identifier'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('identifier', 'string', [
                'null' => false,
                'limit' => 100,
            ])
            ->addColumn('client_identifier', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 100,
                'after' => 'identifier',
            ])
            ->addColumn('user_identifier', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 100,
                'after' => 'client_identifier',
            ])
            ->addColumn('scopes', 'text', [
                'null' => false,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'user_identifier',
            ])
            ->addColumn('expires_at', 'datetime', [
                'null' => false,
                'after' => 'scopes',
            ])
            ->addColumn('revoked', 'boolean', [
                'null' => true,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'expires_at',
            ])
            ->addColumn('created_at', 'datetime', [
                'null' => false,
                'after' => 'revoked',
            ])
            ->addIndex(['client_identifier'], [
                'name' => 'idx_oauth_access_tokens_client',
                'unique' => false,
            ])
            ->addIndex(['user_identifier'], [
                'name' => 'idx_oauth_access_tokens_user',
                'unique' => false,
            ])
            ->addIndex(['expires_at'], [
                'name' => 'idx_oauth_access_tokens_expires',
                'unique' => false,
            ])
            ->addIndex(['revoked'], [
                'name' => 'idx_oauth_access_tokens_revoked',
                'unique' => false,
            ])
            ->addForeignKey('client_identifier', 'oauth_clients', 'identifier', [
                'constraint' => 'oauth_access_tokens_ibfk_1',
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('oauth_refresh_tokens', [
            'id' => false,
            'primary_key' => ['identifier'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('identifier', 'string', [
                'null' => false,
                'limit' => 100,
            ])
            ->addColumn('access_token_identifier', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 100,
                'after' => 'identifier',
            ])
            ->addColumn('expires_at', 'datetime', [
                'null' => false,
                'after' => 'access_token_identifier',
            ])
            ->addColumn('revoked', 'boolean', [
                'null' => true,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'expires_at',
            ])
            ->addColumn('created_at', 'datetime', [
                'null' => false,
                'after' => 'revoked',
            ])
            ->addIndex(['access_token_identifier'], [
                'name' => 'idx_oauth_refresh_tokens_access_token',
                'unique' => false,
            ])
            ->addIndex(['revoked'], [
                'name' => 'idx_oauth_refresh_tokens_revoked',
                'unique' => false,
            ])
            ->addForeignKey('access_token_identifier', 'oauth_access_tokens', 'identifier', [
                'constraint' => 'oauth_refresh_tokens_ibfk_1',
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('oauth_auth_codes', [
            'id' => false,
            'primary_key' => ['identifier'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('identifier', 'string', [
                'null' => false,
                'limit' => 100,
            ])
            ->addColumn('client_identifier', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 100,
                'after' => 'identifier',
            ])
            ->addColumn('user_identifier', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 100,
                'after' => 'client_identifier',
            ])
            ->addColumn('scopes', 'text', [
                'null' => false,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'user_identifier',
            ])
            ->addColumn('redirect_uri', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'scopes',
            ])
            ->addColumn('nonce', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'after' => 'redirect_uri',
            ])
            ->addColumn('expires_at', 'datetime', [
                'null' => false,
                'after' => 'nonce',
            ])
            ->addColumn('revoked', 'boolean', [
                'null' => true,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'expires_at',
            ])
            ->addColumn('code_challenge', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'after' => 'revoked',
            ])
            ->addColumn('code_challenge_method', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 20,
                'after' => 'code_challenge',
            ])
            ->addColumn('created_at', 'datetime', [
                'null' => false,
                'after' => 'code_challenge_method',
            ])
            ->addIndex(['client_identifier'], [
                'name' => 'idx_oauth_auth_codes_client',
                'unique' => false,
            ])
            ->addIndex(['user_identifier'], [
                'name' => 'idx_oauth_auth_codes_user',
                'unique' => false,
            ])
            ->addIndex(['expires_at'], [
                'name' => 'idx_oauth_auth_codes_expires',
                'unique' => false,
            ])
            ->addForeignKey('client_identifier', 'oauth_clients', 'identifier', [
                'constraint' => 'oauth_auth_codes_ibfk_1',
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('oauth_user_consents', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('user_id', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
                'after' => 'id',
            ])
            ->addColumn('client_identifier', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 100,
                'after' => 'user_id',
            ])
            ->addColumn('scopes', 'text', [
                'null' => false,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'client_identifier',
            ])
            ->addColumn('revoked_at', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'scopes',
            ])
            ->addColumn('created_at', 'datetime', [
                'null' => false,
                'after' => 'revoked_at',
            ])
            ->addColumn('updated_at', 'datetime', [
                'null' => false,
                'after' => 'created_at',
            ])
            ->addIndex(['user_id', 'client_identifier'], [
                'name' => 'uniq_oauth_user_client',
                'unique' => true,
            ])
            ->addIndex(['client_identifier'], [
                'name' => 'client_identifier',
                'unique' => false,
            ])
            ->addForeignKey('user_id', 'fs_foodsaver', 'id', [
                'constraint' => 'oauth_user_consents_ibfk_1',
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('client_identifier', 'oauth_clients', 'identifier', [
                'constraint' => 'oauth_user_consents_ibfk_2',
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_achievement', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('region_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'comment' => 'region defining the scope in which this achievement is relevant',
                'after' => 'id',
            ])
            ->addColumn('name', 'string', [
                'null' => false,
                'limit' => 255,
                'after' => 'region_id',
            ])
            ->addColumn('description', 'string', [
                'null' => false,
                'limit' => 255,
                'after' => 'name',
            ])
            ->addColumn('validity_in_days_after_assignment', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_REGULAR,
                'after' => 'description',
            ])
            ->addColumn('created_at', 'timestamp', [
                'null' => false,
                'default' => 'current_timestamp()',
                'after' => 'validity_in_days_after_assignment',
            ])
            ->addColumn('updated_at', 'timestamp', [
                'null' => true,
                'default' => null,
                'update' => 'CURRENT_TIMESTAMP',
                'after' => 'created_at',
            ])
            ->addColumn('icon', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 100,
                'comment' => 'the icon to display this achievement with',
                'after' => 'updated_at',
            ])
            ->addColumn('visibility_type', 'integer', [
                'null' => false,
                'default' => '2',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'icon',
            ])
            ->addColumn('duplicate_mode', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'visibility_type',
            ])
            ->addIndex(['region_id'], [
                'name' => 'region_id',
                'unique' => false,
            ])
            ->addForeignKey('region_id', 'fs_bezirk', 'id', [
                'constraint' => 'fs_achievement_ibfk_1',
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_bell', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('name', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 50,
                'after' => 'id',
            ])
            ->addColumn('body', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 50,
                'after' => 'name',
            ])
            ->addColumn('vars', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'body',
            ])
            ->addColumn('attr', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 500,
                'after' => 'vars',
            ])
            ->addColumn('icon', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 150,
                'after' => 'attr',
            ])
            ->addColumn('identifier', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 40,
                'after' => 'icon',
            ])
            ->addColumn('time', 'datetime', [
                'null' => false,
                'after' => 'identifier',
            ])
            ->addColumn('closeable', 'integer', [
                'null' => false,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'time',
            ])
            ->addColumn('expiration', 'date', [
                'null' => true,
                'default' => null,
                'after' => 'closeable',
            ])
            ->addIndex(['expiration'], [
                'name' => 'expiration',
                'unique' => false,
            ])
            ->addIndex(['identifier'], [
                'name' => 'identifier',
                'unique' => false,
            ])
            ->addIndex(['time'], [
                'name' => 'idx_fs_bell_time',
                'unique' => false,
            ])
            ->create();
        $this->table('fs_betrieb_team', [
            'id' => false,
            'primary_key' => ['foodsaver_id', 'betrieb_id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('betrieb_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('verantwortlich', 'integer', [
                'null' => true,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'betrieb_id',
            ])
            ->addColumn('active', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_REGULAR,
                'after' => 'verantwortlich',
            ])
            ->addColumn('stat_last_update', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'active',
            ])
            ->addColumn('stat_fetchcount', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'stat_last_update',
            ])
            ->addColumn('stat_first_fetch', 'date', [
                'null' => true,
                'default' => null,
                'after' => 'stat_fetchcount',
            ])
            ->addColumn('stat_last_fetch', 'date', [
                'null' => true,
                'default' => null,
                'after' => 'stat_first_fetch',
            ])
            ->addColumn('stat_add_date', 'date', [
                'null' => true,
                'default' => null,
                'after' => 'stat_last_fetch',
            ])
            ->addIndex(['foodsaver_id'], [
                'name' => 'foodsaver_has_betrieb_FKIndex1',
                'unique' => false,
            ])
            ->addIndex(['betrieb_id'], [
                'name' => 'foodsaver_has_betrieb_FKIndex2',
                'unique' => false,
            ])
            ->addIndex(['betrieb_id', 'active', 'foodsaver_id'], [
                'name' => 'idx_betrieb_team_betrieb_active_foodsaver',
                'unique' => false,
            ])
            ->addForeignKey('betrieb_id', 'fs_betrieb', 'id', [
                'constraint' => 'fs_betrieb_team_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_betrieb_team_ibfk_2',
            ])
            ->create();
        $this->table('fs_bezirk', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('parent_id', 'integer', [
                'null' => true,
                'default' => '0',
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
                'after' => 'id',
            ])
            ->addColumn('has_children', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'parent_id',
            ])
            ->addColumn('type', 'integer', [
                'null' => false,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'has_children',
            ])
            ->addColumn('teaser', 'text', [
                'null' => false,
                'default' => '',
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'type',
            ])
            ->addColumn('desc', 'text', [
                'null' => false,
                'default' => '',
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'teaser',
            ])
            ->addColumn('photo', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 200,
                'after' => 'desc',
            ])
            ->addColumn('master', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
                'after' => 'photo',
            ])
            ->addColumn('mailbox_id', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
                'after' => 'master',
            ])
            ->addColumn('name', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 50,
                'after' => 'mailbox_id',
            ])
            ->addColumn('email_name', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 100,
                'after' => 'name',
            ])
            ->addColumn('apply_type', 'integer', [
                'null' => false,
                'default' => '2',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'email_name',
            ])
            ->addColumn('banana_count', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'apply_type',
            ])
            ->addColumn('fetch_count', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'banana_count',
            ])
            ->addColumn('week_num', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'fetch_count',
            ])
            ->addColumn('report_num', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'week_num',
            ])
            ->addColumn('stat_last_update', 'datetime', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
                'after' => 'report_num',
            ])
            ->addColumn('stat_fetchweight', 'decimal', [
                'null' => false,
                'default' => '0.00',
                'precision' => 12,
                'scale' => 2,
                'signed' => false,
                'after' => 'stat_last_update',
            ])
            ->addColumn('stat_fetchcount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
                'after' => 'stat_fetchweight',
            ])
            ->addColumn('stat_postcount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
                'after' => 'stat_fetchcount',
            ])
            ->addColumn('stat_betriebcount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 7,
                'signed' => false,
                'after' => 'stat_postcount',
            ])
            ->addColumn('stat_korpcount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 7,
                'signed' => false,
                'after' => 'stat_betriebcount',
            ])
            ->addColumn('stat_botcount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 7,
                'signed' => false,
                'after' => 'stat_korpcount',
            ])
            ->addColumn('stat_fscount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 7,
                'signed' => false,
                'after' => 'stat_botcount',
            ])
            ->addColumn('stat_fairteilercount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 7,
                'signed' => false,
                'after' => 'stat_fscount',
            ])
            ->addColumn('conversation_id', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
                'after' => 'stat_fairteilercount',
            ])
            ->addColumn('moderated', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'conversation_id',
            ])
            ->addColumn('stat_givecount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
                'after' => 'moderated',
            ])
            ->addColumn('stat_engagecount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
                'after' => 'stat_givecount',
            ])
            ->addIndex(['parent_id'], [
                'name' => 'parent_id',
                'unique' => false,
            ])
            ->addIndex(['type'], [
                'name' => 'type',
                'unique' => false,
            ])
            ->addIndex(['mailbox_id'], [
                'name' => 'mailbox_id',
                'unique' => false,
            ])
            ->addIndex(['master'], [
                'name' => 'master',
                'unique' => false,
            ])
            ->addForeignKey('parent_id', 'fs_bezirk', 'id', [
                'constraint' => 'fs_bezirk_ibfk_1',
                'update' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_feature_toggles', [
            'id' => false,
            'primary_key' => ['identifier', 'site_environment'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('identifier', 'string', [
                'null' => false,
                'limit' => 255,
            ])
            ->addColumn('is_active', 'boolean', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'identifier',
            ])
            ->addColumn('site_environment', 'string', [
                'null' => false,
                'limit' => 255,
                'after' => 'is_active',
            ])
            ->addColumn('created_at', 'timestamp', [
                'null' => false,
                'default' => 'current_timestamp()',
                'after' => 'site_environment',
            ])
            ->addColumn('updated_at', 'timestamp', [
                'null' => true,
                'default' => null,
                'update' => 'CURRENT_TIMESTAMP',
                'after' => 'created_at',
            ])
            ->addIndex(['identifier'], [
                'name' => 'identifier',
                'unique' => false,
            ])
            ->create();
        $this->table('fs_foodsaver_archive', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('bezirk_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'id',
            ])
            ->addColumn('position', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 255,
                'after' => 'bezirk_id',
            ])
            ->addColumn('verified', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'position',
            ])
            ->addColumn('last_pass', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'verified',
            ])
            ->addColumn('new_bezirk', 'string', [
                'null' => false,
                'limit' => 120,
                'after' => 'last_pass',
            ])
            ->addColumn('want_new', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'new_bezirk',
            ])
            ->addColumn('mailbox_id', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'signed' => false,
                'after' => 'want_new',
            ])
            ->addColumn('rolle', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'mailbox_id',
            ])
            ->addColumn('type', 'integer', [
                'null' => true,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'rolle',
            ])
            ->addColumn('plz', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'after' => 'type',
            ])
            ->addColumn('stadt', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 100,
                'after' => 'plz',
            ])
            ->addColumn('lat', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 20,
                'after' => 'stadt',
            ])
            ->addColumn('lon', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 20,
                'after' => 'lat',
            ])
            ->addColumn('photo', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 50,
                'after' => 'lon',
            ])
            ->addColumn('email', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 120,
                'after' => 'photo',
            ])
            ->addColumn('password', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 100,
                'after' => 'email',
            ])
            ->addColumn('name', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 120,
                'after' => 'password',
            ])
            ->addColumn('admin', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'name',
            ])
            ->addColumn('nachname', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 120,
                'after' => 'admin',
            ])
            ->addColumn('anschrift', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 120,
                'after' => 'nachname',
            ])
            ->addColumn('telefon', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 30,
                'after' => 'anschrift',
            ])
            ->addColumn('homepage', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'after' => 'telefon',
            ])
            ->addColumn('handy', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 50,
                'after' => 'homepage',
            ])
            ->addColumn('geschlecht', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'handy',
            ])
            ->addColumn('geb_datum', 'date', [
                'null' => true,
                'default' => null,
                'after' => 'geschlecht',
            ])
            ->addColumn('anmeldedatum', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'geb_datum',
            ])
            ->addColumn('privacy_notice_accepted_date', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'anmeldedatum',
            ])
            ->addColumn('privacy_policy_accepted_date', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'privacy_notice_accepted_date',
            ])
            ->addColumn('orgateam', 'integer', [
                'null' => true,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'privacy_policy_accepted_date',
            ])
            ->addColumn('active', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'orgateam',
            ])
            ->addColumn('data', 'text', [
                'null' => false,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'active',
            ])
            ->addColumn('about_me_public', 'text', [
                'null' => false,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'data',
            ])
            ->addColumn('newsletter', 'boolean', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'about_me_public',
            ])
            ->addColumn('token', 'string', [
                'null' => false,
                'limit' => 100,
                'after' => 'newsletter',
            ])
            ->addColumn('infomail_message', 'boolean', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'token',
            ])
            ->addColumn('last_login', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'infomail_message',
            ])
            ->addColumn('stat_fetchweight', 'decimal', [
                'null' => false,
                'default' => '0.00',
                'precision' => 9,
                'scale' => 2,
                'signed' => false,
                'after' => 'last_login',
            ])
            ->addColumn('stat_fetchcount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
                'after' => 'stat_fetchweight',
            ])
            ->addColumn('stat_ratecount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
                'after' => 'stat_fetchcount',
            ])
            ->addColumn('stat_rating', 'decimal', [
                'null' => false,
                'default' => '0.00',
                'precision' => 4,
                'scale' => 2,
                'signed' => false,
                'after' => 'stat_ratecount',
            ])
            ->addColumn('stat_postcount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_REGULAR,
                'after' => 'stat_rating',
            ])
            ->addColumn('stat_buddycount', 'integer', [
                'null' => false,
                'limit' => 7,
                'signed' => false,
                'after' => 'stat_postcount',
            ])
            ->addColumn('stat_bananacount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 7,
                'signed' => false,
                'after' => 'stat_buddycount',
            ])
            ->addColumn('stat_fetchrate', 'decimal', [
                'null' => false,
                'default' => '100.00',
                'precision' => 6,
                'scale' => 2,
                'after' => 'stat_bananacount',
            ])
            ->addColumn('sleep_status', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'stat_fetchrate',
            ])
            ->addColumn('sleep_from', 'date', [
                'null' => true,
                'default' => null,
                'after' => 'sleep_status',
            ])
            ->addColumn('sleep_until', 'date', [
                'null' => true,
                'default' => null,
                'after' => 'sleep_from',
            ])
            ->addColumn('sleep_msg', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'sleep_until',
            ])
            ->addColumn('option', 'text', [
                'null' => false,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'sleep_msg',
            ])
            ->addColumn('beta', 'boolean', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'option',
            ])
            ->addColumn('quiz_rolle', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'beta',
            ])
            ->addColumn('contact_public', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'quiz_rolle',
            ])
            ->addColumn('deleted_at', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'contact_public',
            ])
            ->addColumn('about_me_intern', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'after' => 'deleted_at',
            ])
            ->addColumn('deleted_by', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'signed' => false,
                'comment' => 'id of the user who deleted this profile',
                'after' => 'about_me_intern',
            ])
            ->addColumn('deleted_reason', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 200,
                'comment' => 'optional explanation why this profile was deleted',
                'after' => 'deleted_by',
            ])
            ->addColumn('no_automatic_delete', 'boolean', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'comment' => 'If true user is not automatically deleted',
                'after' => 'deleted_reason',
            ])
            ->addColumn('is_sleeping', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'no_automatic_delete',
            ])
            ->addColumn('totp_secret', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 40,
                'after' => 'is_sleeping',
            ])
            ->addColumn('backup_codes', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_LONG,
                'after' => 'totp_secret',
            ])
            ->addColumn('stat_givecount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
                'after' => 'backup_codes',
            ])
            ->addColumn('stat_engagecount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
                'after' => 'stat_givecount',
            ])
            ->addIndex(['bezirk_id'], [
                'name' => 'foodsaver_FKIndex2',
                'unique' => false,
            ])
            ->addIndex(['plz'], [
                'name' => 'plz',
                'unique' => false,
            ])
            ->addIndex(['mailbox_id'], [
                'name' => 'mailbox_id',
                'unique' => false,
            ])
            ->addIndex(['newsletter'], [
                'name' => 'newsletter',
                'unique' => false,
            ])
            ->create();
        $this->table('fs_foodsaver_has_conversation', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('conversation_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('unread', 'integer', [
                'null' => false,
                'default' => '1',
                'limit' => MysqlAdapter::INT_SMALL,
                'after' => 'conversation_id',
            ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
                'after' => 'unread',
            ])
            ->addIndex(['foodsaver_id', 'conversation_id'], [
                'name' => 'foodsaver_id',
                'unique' => true,
            ])
            ->addIndex(['foodsaver_id'], [
                'name' => 'foodsaver_has_conversation_FKIndex1',
                'unique' => false,
            ])
            ->addIndex(['conversation_id'], [
                'name' => 'foodsaver_has_conversation_FKIndex2',
                'unique' => false,
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_foodsaver_has_conversation_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('conversation_id', 'fs_conversation', 'id', [
                'constraint' => 'fs_foodsaver_has_conversation_ibfk_2',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_fsreports_has_wallpost', [
            'id' => false,
            'primary_key' => ['fsreports_id', 'wallpost_id'],
            'engine' => 'InnoDB',
            'comment' => 'This wall is over ALL reports of a foodsaver',
        ])
            ->addColumn('fsreports_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'comment' => 'foodsaver Id that has all reports',
            ])
            ->addColumn('wallpost_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'comment' => 'wallpost_id',
                'after' => 'fsreports_id',
            ])
            ->addIndex(['fsreports_id'], [
                'name' => 'foodsaver_ID',
                'unique' => false,
            ])
            ->addIndex(['wallpost_id'], [
                'name' => 'wallpost_id',
                'unique' => false,
            ])
            ->addForeignKey('wallpost_id', 'fs_wallpost', 'id', [
                'constraint' => 'fs_fsreports_has_wallpost_ibfk_2',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('fsreports_id', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_fsreports_has_wallpost_ibfk_3',
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_region_function', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('region_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
                'after' => 'id',
            ])
            ->addColumn('function_id', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
                'after' => 'region_id',
            ])
            ->addColumn('target_id', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_REGULAR,
                'after' => 'function_id',
            ])
            ->addIndex(['target_id', 'function_id'], [
                'name' => 'ux_fs_region_function_target_function',
                'unique' => true,
            ])
            ->addIndex(['target_id', 'function_id', 'region_id'], [
                'name' => 'idx_fs_region_function_target_function_region',
                'unique' => false,
            ])
            ->addIndex(['region_id'], [
                'name' => 'fs_region_function_ibfk_1',
                'unique' => false,
            ])
            ->addForeignKey('region_id', 'fs_bezirk', 'id', [
                'constraint' => 'fs_region_function_ibfk_1',
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ])
            ->create();
        $this->table('fs_store_log', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('store_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'comment' => 'ID of Store',
                'after' => 'id',
            ])
            ->addColumn('date_activity', 'datetime', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
                'comment' => 'when did the action take place',
                'after' => 'store_id',
            ])
            ->addColumn('action', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'comment' => 'action type that was performed',
                'after' => 'date_activity',
            ])
            ->addColumn('fs_id_a', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'comment' => 'foodsaver_id who is doing the action',
                'after' => 'action',
            ])
            ->addColumn('fs_id_p', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'signed' => false,
                'comment' => 'to which foodsaver_id is it done to',
                'after' => 'fs_id_a',
            ])
            ->addColumn('date_reference', 'datetime', [
                'null' => true,
                'default' => null,
                'comment' => 'date referenced (slot or wallpost entry)',
                'after' => 'fs_id_p',
            ])
            ->addColumn('content', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'comment' => 'Text from the store-wall-entry',
                'after' => 'date_reference',
            ])
            ->addColumn('reason', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'comment' => 'Why a negativ action was done',
                'after' => 'content',
            ])
            ->addIndex(['store_id', 'date_reference'], [
                'name' => 'store_id_date_ref',
                'unique' => false,
            ])
            ->addIndex(['store_id', 'date_activity'], [
                'name' => 'store_id_date_act',
                'unique' => false,
            ])
            ->addIndex(['date_reference', 'date_activity'], [
                'name' => 'date_ref_date_act',
                'unique' => false,
            ])
            ->addIndex(['fs_id_a', 'action', 'date_reference'], [
                'name' => 'fsid_ref',
                'unique' => false,
            ])
            ->create();
        $this->table('fs_theme_post', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('theme_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'id',
            ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'theme_id',
            ])
            ->addColumn('body', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('time', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'body',
            ])
            ->addColumn('hidden_time', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'time',
            ])
            ->addColumn('hidden_by', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'signed' => false,
                'after' => 'hidden_time',
            ])
            ->addColumn('hidden_reason', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 255,
                'after' => 'hidden_by',
            ])
            ->addIndex(['foodsaver_id'], [
                'name' => 'theme_post_FKIndex1',
                'unique' => false,
            ])
            ->addIndex(['theme_id'], [
                'name' => 'theme_post_FKIndex2',
                'unique' => false,
            ])
            ->addIndex(['hidden_by'], [
                'name' => 'hidden_by',
                'unique' => false,
            ])
            ->addIndex(['body'], [
                'name' => 'fs_theme_post_body_fulltext',
                'unique' => false,
                'type' => 'fulltext',
            ])
            ->addForeignKey('theme_id', 'fs_theme', 'id', [
                'constraint' => 'fs_theme_post_ibfk_1',
                'delete' => 'CASCADE',
            ])
            ->addForeignKey('hidden_by', 'fs_foodsaver', 'id', [
                'constraint' => 'fs_theme_post_ibfk_2',
                'update' => 'CASCADE',
                'delete' => 'NO_ACTION',
            ])
            ->create();
        $this->table('fs_wallpost', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'id',
            ])
            ->addColumn('body', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'foodsaver_id',
            ])
            ->addColumn('time', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'body',
            ])
            ->addColumn('attach', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'time',
            ])
            ->addIndex(['foodsaver_id'], [
                'name' => 'wallpost_FKIndex1',
                'unique' => false,
            ])
            ->addIndex(['time'], [
                'name' => 'idx_wall_time',
                'unique' => false,
            ])
            ->create();
        $this->table('uploads', [
            'id' => false,
            'primary_key' => ['uuid'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('uuid', 'char', [
                'null' => false,
                'limit' => 36,
            ])
            ->addColumn('user_id', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'signed' => false,
                'after' => 'uuid',
            ])
            ->addColumn('sha256hash', 'char', [
                'null' => false,
                'limit' => 64,
                'after' => 'user_id',
            ])
            ->addColumn('mimeType', 'string', [
                'null' => false,
                'limit' => 255,
                'after' => 'sha256hash',
            ])
            ->addColumn('uploaded_at', 'datetime', [
                'null' => false,
                'after' => 'mimeType',
            ])
            ->addColumn('filesize', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'uploaded_at',
            ])
            ->addColumn('used_in', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 4,
                'signed' => false,
                'comment' => 'Indicates in which module this uploaded file is being used (profile photo, wall post, ...). A value of null indicates that the file is not being used (yet).',
                'after' => 'filesize',
            ])
            ->addColumn('usage_id', 'char', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'comment' => 'Id of the entity that uses this uploaded file, e.g. id of the profile or the wall post. A null value indicates that the file is not being used (yet).',
                'after' => 'used_in',
            ])
            ->create();
        $this->table('configuration', [
            'id' => false,
            'primary_key' => ['key'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('key', 'string', [
                'null' => false,
                'limit' => 50,
            ])
            ->addColumn('value', 'string', [
                'null' => false,
                'limit' => 1000,
                'after' => 'key',
            ])
            ->addColumn('category', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
                'comment' => 'Optional category of the key-value pair',
                'after' => 'value',
            ])
            ->create();
        $this->table('fs_foodsaver', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('bezirk_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'id',
            ])
            ->addColumn('position', 'string', [
                'null' => false,
                'default' => '',
                'limit' => 255,
                'after' => 'bezirk_id',
            ])
            ->addColumn('verified', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'position',
            ])
            ->addColumn('last_pass', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'verified',
            ])
            ->addColumn('mailbox_id', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'signed' => false,
                'after' => 'last_pass',
            ])
            ->addColumn('rolle', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'mailbox_id',
            ])
            ->addColumn('plz', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'after' => 'rolle',
            ])
            ->addColumn('stadt', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 100,
                'after' => 'plz',
            ])
            ->addColumn('lat', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 20,
                'after' => 'stadt',
            ])
            ->addColumn('lon', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 20,
                'after' => 'lat',
            ])
            ->addColumn('photo', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 50,
                'after' => 'lon',
            ])
            ->addColumn('email', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 120,
                'after' => 'photo',
            ])
            ->addColumn('password', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 100,
                'after' => 'email',
            ])
            ->addColumn('name', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 120,
                'after' => 'password',
            ])
            ->addColumn('nachname', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 120,
                'after' => 'name',
            ])
            ->addColumn('anschrift', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 120,
                'after' => 'nachname',
            ])
            ->addColumn('telefon', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 30,
                'after' => 'anschrift',
            ])
            ->addColumn('handy', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 50,
                'after' => 'telefon',
            ])
            ->addColumn('geschlecht', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'handy',
            ])
            ->addColumn('geb_datum', 'date', [
                'null' => true,
                'default' => null,
                'after' => 'geschlecht',
            ])
            ->addColumn('anmeldedatum', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'geb_datum',
            ])
            ->addColumn('privacy_notice_accepted_date', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'anmeldedatum',
            ])
            ->addColumn('privacy_policy_accepted_date', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'privacy_notice_accepted_date',
            ])
            ->addColumn('active', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'privacy_policy_accepted_date',
            ])
            ->addColumn('about_me_public', 'text', [
                'null' => false,
                'default' => '',
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'active',
            ])
            ->addColumn('newsletter', 'boolean', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'about_me_public',
            ])
            ->addColumn('token', 'string', [
                'null' => false,
                'limit' => 100,
                'after' => 'newsletter',
            ])
            ->addColumn('infomail_message', 'boolean', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'token',
            ])
            ->addColumn('last_login', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'infomail_message',
            ])
            ->addColumn('stat_fetchweight', 'decimal', [
                'null' => false,
                'default' => '0.00',
                'precision' => 9,
                'scale' => 2,
                'signed' => false,
                'after' => 'last_login',
            ])
            ->addColumn('stat_fetchcount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
                'after' => 'stat_fetchweight',
            ])
            ->addColumn('stat_ratecount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
                'after' => 'stat_fetchcount',
            ])
            ->addColumn('stat_rating', 'decimal', [
                'null' => false,
                'default' => '0.00',
                'precision' => 4,
                'scale' => 2,
                'signed' => false,
                'after' => 'stat_ratecount',
            ])
            ->addColumn('stat_postcount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_REGULAR,
                'after' => 'stat_rating',
            ])
            ->addColumn('stat_buddycount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 7,
                'signed' => false,
                'after' => 'stat_postcount',
            ])
            ->addColumn('stat_bananacount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 7,
                'signed' => false,
                'after' => 'stat_buddycount',
            ])
            ->addColumn('stat_fetchrate', 'decimal', [
                'null' => false,
                'default' => '100.00',
                'precision' => 6,
                'scale' => 2,
                'after' => 'stat_bananacount',
            ])
            ->addColumn('sleep_status', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'stat_fetchrate',
            ])
            ->addColumn('sleep_from', 'date', [
                'null' => true,
                'default' => null,
                'after' => 'sleep_status',
            ])
            ->addColumn('sleep_until', 'date', [
                'null' => true,
                'default' => null,
                'after' => 'sleep_from',
            ])
            ->addColumn('sleep_msg', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'is_sleeping',
            ])
            ->addColumn('quiz_rolle', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'sleep_msg',
            ])
            ->addColumn('deleted_at', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'quiz_rolle',
            ])
            ->addColumn('about_me_intern', 'text', [
                'null' => true,
                'default' => null,
                'limit' => 65535,
                'after' => 'deleted_at',
            ])
            ->addColumn('deleted_by', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'signed' => false,
                'comment' => 'id of the user who deleted this profile',
                'after' => 'about_me_intern',
            ])
            ->addColumn('deleted_reason', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 200,
                'comment' => 'optional explanation why this profile was deleted',
                'after' => 'deleted_by',
            ])
            ->addColumn('no_automatic_delete', 'boolean', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'comment' => 'If true user is not automatically deleted',
                'after' => 'deleted_reason',
            ])
            ->addColumn('totp_secret', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 40,
                'after' => 'no_automatic_delete',
            ])
            ->addColumn('backup_codes', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_LONG,
                'after' => 'totp_secret',
            ])
            ->addColumn('stat_givecount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
                'after' => 'backup_codes',
            ])
            ->addColumn('stat_engagecount', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 10,
                'signed' => false,
                'after' => 'stat_givecount',
            ])
            ->addIndex(['email'], [
                'name' => 'email',
                'unique' => true,
            ])
            ->addIndex(['bezirk_id'], [
                'name' => 'foodsaver_FKIndex2',
                'unique' => false,
            ])
            ->addIndex(['plz'], [
                'name' => 'plz',
                'unique' => false,
            ])
            ->addIndex(['mailbox_id'], [
                'name' => 'mailbox_id',
                'unique' => false,
            ])
            ->addIndex(['newsletter'], [
                'name' => 'newsletter',
                'unique' => false,
            ])
            ->addIndex(['deleted_at'], [
                'name' => 'idx_fs_foodsaver_deleted_at',
                'unique' => false,
            ])
            ->addIndex(['name', 'nachname'], [
                'name' => 'name',
                'unique' => false,
                'type' => 'fulltext',
            ])
            ->create();
        $this->execute('ALTER TABLE fs_foodsaver
            ADD is_sleeping TINYINT(1) GENERATED ALWAYS AS (
                IF(sleep_status = 1,
                    sleep_from < NOW() AND NOW() < DATE_ADD(sleep_until, INTERVAL 1 DAY),
                    sleep_status = 2
                )
            ) VIRTUAL
            COMMENT "calculated column. Indicates, whether the user is currently sleeping"
            AFTER sleep_until');

        $this->table('fs_betrieb', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'comment' => '',
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'identity' => true,
            ])
            ->addColumn('betrieb_status_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'id',
            ])
            ->addColumn('bezirk_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'betrieb_status_id',
            ])
            ->addColumn('added', 'date', [
                'null' => false,
                'after' => 'bezirk_id',
            ])
            ->addColumn('plz', 'string', [
                'null' => false,
                'limit' => 10,
                'after' => 'added',
            ])
            ->addColumn('stadt', 'string', [
                'null' => false,
                'limit' => 50,
                'after' => 'plz',
            ])
            ->addColumn('lat', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 20,
                'after' => 'stadt',
            ])
            ->addColumn('lon', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 20,
                'after' => 'lat',
            ])
            ->addColumn('kette_id', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'signed' => false,
                'after' => 'lon',
            ])
            ->addColumn('betrieb_kategorie_id', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 10,
                'signed' => false,
                'after' => 'kette_id',
            ])
            ->addColumn('name', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 120,
                'after' => 'betrieb_kategorie_id',
            ])
            ->addColumn('str', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 120,
                'after' => 'name',
            ])
            ->addColumn('status_date', 'date', [
                'null' => true,
                'default' => null,
                'after' => 'str',
            ])
            ->addColumn('status', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'status_date',
            ])
            ->addColumn('ansprechpartner', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 60,
                'after' => 'status',
            ])
            ->addColumn('telefon', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 50,
                'after' => 'ansprechpartner',
            ])
            ->addColumn('fax', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 50,
                'after' => 'telefon',
            ])
            ->addColumn('email', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 60,
                'after' => 'fax',
            ])
            ->addColumn('begin', 'date', [
                'null' => true,
                'default' => null,
                'after' => 'email',
            ])
            ->addColumn('besonderheiten', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'after' => 'begin',
            ])
            ->addColumn('public_info', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 535,
                'after' => 'besonderheiten',
            ])
            ->addColumn('public_time', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'public_info',
            ])
            ->addColumn('ueberzeugungsarbeit', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'public_time',
            ])
            ->addColumn('presse', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'ueberzeugungsarbeit',
            ])
            ->addColumn('sticker', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'presse',
            ])
            ->addColumn('abholmenge', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'sticker',
            ])
            ->addColumn('team_status', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'comment' => '0 = Team Voll; 1 = Es werden noch Helfer gesucht; 2 = Es werden dringend Helfer gesucht',
                'after' => 'abholmenge',
            ])
            ->addColumn('prefetchtime', 'integer', [
                'null' => false,
                'default' => '1209600',
                'limit' => 10,
                'signed' => false,
                'after' => 'team_status',
            ])
            ->addColumn('team_conversation_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'prefetchtime',
            ])
            ->addColumn('springer_conversation_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'team_conversation_id',
            ])
            ->addColumn('deleted_at', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'springer_conversation_id',
            ])
            ->addColumn('use_region_pickup_rule', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => 1,
                'signed' => false,
                'comment' => '@StoreSettings::USE_PICKUP_RULE_YES = Store follows region pickup rule. @StoreSettings::USE_PICKUP_RULE_NO = Store does not follow region pickup rule.',
                'after' => 'deleted_at',
            ])
            ->addColumn('hygiene_requirement', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'use_region_pickup_rule',
            ])
            ->addColumn('verified_requirement', 'integer', [
                'null' => false,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'hygiene_requirement',
            ])
            ->addColumn('phone_requirement', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'verified_requirement',
            ])
            ->addColumn('apply_text_requirement', 'integer', [
                'null' => false,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'after' => 'phone_requirement',
            ])
            ->addIndex(['kette_id'], [
                'name' => 'betrieb_FKIndex2',
                'unique' => false,
            ])
            ->addIndex(['bezirk_id'], [
                'name' => 'betrieb_FKIndex3',
                'unique' => false,
            ])
            ->addIndex(['betrieb_status_id'], [
                'name' => 'betrieb_FKIndex5',
                'unique' => false,
            ])
            ->addIndex(['plz'], [
                'name' => 'plz',
                'unique' => false,
            ])
            ->addIndex(['team_status'], [
                'name' => 'team_status',
                'unique' => false,
            ])
            ->addIndex(['team_conversation_id'], [
                'name' => 'betrieb_FKIndex6_conv',
                'unique' => false,
            ])
            ->addIndex(['springer_conversation_id'], [
                'name' => 'betrieb_FKIndex7_conv_spring',
                'unique' => false,
            ])
            ->addIndex(['betrieb_kategorie_id'], [
                'name' => 'betrieb_kategorie_id',
                'unique' => false,
            ])
            ->addForeignKey('betrieb_kategorie_id', 'fs_betrieb_kategorie', 'id', [
                'constraint' => 'fs_betrieb_ibfk_1',
                'update' => 'NO_ACTION',
                'delete' => 'SET_NULL',
            ])
            ->addForeignKey('kette_id', 'fs_chain', 'id', [
                'constraint' => 'fs_betrieb_ibfk_2',
                'update' => 'NO_ACTION',
                'delete' => 'SET_NULL',
            ])
            ->addForeignKey('bezirk_id', 'fs_bezirk', 'id', [
                'constraint' => 'fs_betrieb_ibfk_3',
                'update' => 'NO_ACTION',
            ])
            ->addForeignKey('team_conversation_id', 'fs_conversation', 'id', [
                'constraint' => 'fs_betrieb_ibfk_4',
                'update' => 'NO_ACTION',
            ])
            ->addForeignKey('springer_conversation_id', 'fs_conversation', 'id', [
                'constraint' => 'fs_betrieb_ibfk_5',
                'update' => 'NO_ACTION',
            ])
            ->create();
        $this->execute('SET unique_checks=1; SET foreign_key_checks=1;');
    }
}
