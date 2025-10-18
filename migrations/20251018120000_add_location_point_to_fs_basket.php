<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddLocationPointToFsBasket extends AbstractMigration
{
    public function change(): void
    {
        $this->execute(<<<'SQL'
-- add nullable point column
ALTER TABLE `fs_basket` ADD COLUMN `point` POINT NULL;

-- backfill with numeric constructor ensuring no nulls
UPDATE `fs_basket` SET `point` = Point(COALESCE(`lon`, 0), COALESCE(`lat`, 0));

-- make column not null so a SPATIAL index can be created
ALTER TABLE `fs_basket` MODIFY COLUMN `point` POINT NOT NULL;

-- create spatial index on the new column
CREATE SPATIAL INDEX `idx_fs_basket_point` ON `fs_basket` (`point`);

-- composite index to speed common non-geo filters in nearby queries
CREATE INDEX `idx_basket_status_until_fs` ON `fs_basket` (`status`, `until`, `foodsaver_id`);

-- create triggers to keep point in sync on insert/update
CREATE TRIGGER fs_basket_set_point_before_insert
BEFORE INSERT ON fs_basket
FOR EACH ROW
BEGIN
    IF @disable_fs_basket_point_trigger IS NULL OR @disable_fs_basket_point_trigger = 0 THEN
        SET @disable_fs_basket_point_trigger = 1;
        SET NEW.point = Point(COALESCE(NEW.lon, 0), COALESCE(NEW.lat, 0));
        SET @disable_fs_basket_point_trigger = 0;
    END IF;
END;

CREATE TRIGGER fs_basket_set_point_before_update
BEFORE UPDATE ON fs_basket
FOR EACH ROW
BEGIN
    IF @disable_fs_basket_point_trigger IS NULL OR @disable_fs_basket_point_trigger = 0 THEN
        SET @disable_fs_basket_point_trigger = 1;
        SET NEW.point = Point(COALESCE(NEW.lon, 0), COALESCE(NEW.lat, 0));
        SET @disable_fs_basket_point_trigger = 0;
    END IF;
END;
SQL
        );
    }
}
