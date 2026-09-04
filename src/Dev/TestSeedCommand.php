<?php

namespace Foodsharing\Dev;

use Codeception\CustomCommandInterface;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;

class TestSeedCommand extends AbstractSeedCommand implements CustomCommandInterface
{
    protected static $defaultDescription = 'Seed the test db.';

    public static function getCommandName(): string
    {
        return 'foodsharing:test-seed';
    }

    protected function seed(): void
    {
        $I = $this->helper;

        // Create base regions
        $I->createRootRegion();
        $I->createRegion('Europa', ['id' => RegionIDs::EUROPE, 'parent_id' => RegionIDs::ROOT, 'type' => UnitType::CONTINENT, 'has_children' => 1, 'email' => 'europa', 'email_name' => 'Foodsharing Europa', 'stat_last_update' => '2020-05-24 02:18:15', 'stat_fetchweight' => '33829400.50', 'stat_fetchcount' => '2116647', 'stat_postcount' => '1733615', 'stat_betriebcount' => '23002', 'stat_korpcount' => '7031', 'stat_botcount' => '1004', 'stat_fscount' => '74600', 'stat_fairteilercount' => '891']);
        $I->createRegion('Arbeitsgruppen Überregional', ['id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'parent_id' => RegionIDs::ROOT, 'type' => UnitType::BIG_CITY, 'master' => 392, 'mailbox_id' => 32678, 'email_name' => 'Foodsharing Arbeitsgruppen Überregional', 'stat_last_update' => '2020-05-24 02:17:57', 'stat_fetchweight' => '5176.00', 'stat_fetchcount' => '208', 'stat_postcount' => '53969', 'stat_betriebcount' => '1', 'stat_korpcount' => '0', 'stat_botcount' => '1', 'stat_fscount' => '3360']);

        $this->output->writeln('Inserting fetch weight values');
        $this->insertFetchWeightValues($I);
        $this->output->writeln('');

        $this->output->writeln('Inserting donation configuration');
        $this->insertDonationConfiguration($I);
        $this->output->writeln('');

        $this->output->writeln('Adding content');
        $this->createContent($I);
        $this->output->writeln('');
    }
}
