<?php

declare(strict_types=1);

use Foodsharing\Modules\Core\DBConstants\Foodsaver\SleepStatus;
use Phinx\Migration\AbstractMigration;

final class UpdateIsSleepingComputedColumnToIncludeUntilDate extends AbstractMigration
{
    public function change(): void
    {
        $temporary = SleepStatus::TEMP;
        $full = SleepStatus::FULL;

        // Include the full "sleep_until" day in the temporary sleep window
        $this->execute("ALTER TABLE fs_foodsaver
            MODIFY is_sleeping TINYINT(1) GENERATED ALWAYS AS (
                IF(sleep_status = {$temporary},
                    sleep_from < NOW() AND NOW() < DATE_ADD(sleep_until, INTERVAL 1 DAY),
                    sleep_status = {$full}
                )
            ) VIRTUAL
            COMMENT \"calculated column. Indicates, whether the user is currently sleeping\"");
    }
}
