<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Adds triggers that sync the contents of fs_betrieb_notiz with fs_wallpost and fs_store_has_wallpost.
 * This way, no matter in which of the tables posts are accessed, created or deleted, they always are
 * represented properly in both tables.
 * This allows to transition to the newer wallpost system while still supporting the old way used in production until release N.
 * These triggers can be removed after that release alongside the fs_betrieb_notiz table.
 */
final class AddStoreWallSyncTriggers extends AbstractMigration
{
    public function change()
    {
        $this->execute('
            CREATE TRIGGER sync_after_insert_fs_betrieb_notiz
            AFTER INSERT ON fs_betrieb_notiz
            FOR EACH ROW
            BEGIN
                IF @disable_sync_trigger IS NULL OR @disable_sync_trigger = 0 THEN
                    SET @disable_sync_trigger = 1;
                    INSERT INTO fs_wallpost (foodsaver_id, body, time)
                    VALUES (NEW.foodsaver_id, NEW.text, NEW.zeit);

                    INSERT INTO fs_store_has_wallpost (store_id, wallpost_id)
                    VALUES (NEW.betrieb_id, LAST_INSERT_ID());

                    SET @disable_sync_trigger = 0;
                END IF;
            END;

            CREATE TRIGGER sync_after_insert_fs_store_has_wallpost
            AFTER INSERT ON fs_store_has_wallpost
            FOR EACH ROW
            BEGIN
                IF @disable_sync_trigger IS NULL OR @disable_sync_trigger = 0 THEN
                    SET @disable_sync_trigger = 1;

                    INSERT INTO fs_betrieb_notiz (foodsaver_id, betrieb_id, milestone, text, zeit, last)
                    SELECT wp.foodsaver_id, NEW.store_id, 0, wp.body, wp.time, 0
                    FROM fs_wallpost wp
                    WHERE wp.id = NEW.wallpost_id;
                    
                    SET @disable_sync_trigger = 0;
                END IF;
            END;

            CREATE TRIGGER sync_after_delete_fs_betrieb_notiz
            AFTER DELETE ON fs_betrieb_notiz
            FOR EACH ROW
            BEGIN
                IF @disable_sync_trigger IS NULL OR @disable_sync_trigger = 0 THEN
                    SET @disable_sync_trigger = 1;

                    SET @wallpost_id_to_delete = (
                        SELECT wp.id
                        FROM fs_wallpost wp
                        JOIN fs_store_has_wallpost shw ON wp.id = shw.wallpost_id
                        WHERE shw.store_id = OLD.betrieb_id AND wp.foodsaver_id = OLD.foodsaver_id AND wp.time = OLD.zeit AND wp.body = OLD.text
                        LIMIT 1
                    );

                    DELETE FROM fs_store_has_wallpost 
                    WHERE store_id = OLD.betrieb_id AND wallpost_id = @wallpost_id_to_delete;

                    DELETE FROM fs_wallpost WHERE id = @wallpost_id_to_delete;
                    
                    SET @disable_sync_trigger = 0;
                END IF;
            END;

            CREATE TRIGGER sync_after_delete_fs_store_has_wallpost
            AFTER DELETE ON fs_store_has_wallpost
            FOR EACH ROW
            BEGIN
                IF @disable_sync_trigger IS NULL OR @disable_sync_trigger = 0 THEN
                    SET @disable_sync_trigger = 1;

                    SET @notiz_id_to_delete = (
                        SELECT bn.id
                        FROM fs_betrieb_notiz bn
                        JOIN fs_wallpost wp ON bn.foodsaver_id = wp.foodsaver_id AND bn.zeit = wp.time AND bn.text = wp.body
                        WHERE bn.betrieb_id = OLD.store_id AND wp.id = OLD.wallpost_id
                        LIMIT 1
                    );

                    DELETE FROM fs_betrieb_notiz WHERE id = @notiz_id_to_delete;
                    
                    SET @disable_sync_trigger = 0;
                END IF;
            END;
        ');
        $this->execute('
            SET @disable_sync_trigger = 1;
                -- remove current entries to prevent duplicates
                DELETE wp
                FROM fs_wallpost wp
                JOIN fs_store_has_wallpost shw ON shw.wallpost_id = wp.id;

                DELETE FROM fs_store_has_wallpost;

                -- add values based on fs_betrieb_notiz
                INSERT INTO fs_wallpost (foodsaver_id, body, time)
                SELECT foodsaver_id, text, zeit
                FROM fs_betrieb_notiz
                WHERE milestone = 0;

                INSERT INTO fs_store_has_wallpost (store_id, wallpost_id)
                SELECT bn.betrieb_id, wp.id
                FROM fs_betrieb_notiz bn
                JOIN fs_wallpost wp ON bn.foodsaver_id = wp.foodsaver_id AND bn.text = wp.body AND bn.zeit = wp.time
                WHERE bn.milestone = 0;

            SET @disable_sync_trigger = 0;
        ');
    }
}
