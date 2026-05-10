<?php

namespace Foodsharing\Dev;

use Codeception\Command\Shared\ConfigTrait;
use Codeception\CustomCommandInterface;
use Codeception\Lib\Di;
use Codeception\Lib\ModuleContainer;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tests\Support\Helper\Foodsharing;

class TestSeedCommand extends AbstractSeedCommand implements CustomCommandInterface
{
    use ConfigTrait;
    protected static $defaultDescription = 'Seed the test db.';

    protected Foodsharing $helper;

    protected OutputInterface $output;

    public static function getCommandName(): string
    {
        return 'foodsharing:test-seed';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;

        $config = $this->getGlobalConfig();
        $di = new Di();
        $module = new ModuleContainer($di, $config);
        $this->helper = $module->create(Foodsharing::class);
        $this->helper->_initialize();

        $this->output->writeln('Clearing existing ' . FS_ENV . ' seed data');
        $this->helper->clear();

        $this->output->writeln('Seeding ' . FS_ENV . ' database');
        $this->seed();

        return Command::SUCCESS;
    }

    protected function seed()
    {
        $I = $this->helper;
        $I->_getDbh()->beginTransaction();
        $I->_getDriver()->executeQuery('SET FOREIGN_KEY_CHECKS=1;', []);

        // Create base regions
        $I->createRootRegion();
        $I->createRegion('Europa', ['id' => RegionIDs::EUROPE, 'parent_id' => RegionIDs::ROOT, 'type' => UnitType::COUNTRY, 'has_children' => 1, 'email' => 'europa', 'email_name' => 'Foodsharing Europa', 'stat_last_update' => '2020-05-24 02:18:15', 'stat_fetchweight' => '33829400.50', 'stat_fetchcount' => '2116647', 'stat_postcount' => '1733615', 'stat_betriebcount' => '23002', 'stat_korpcount' => '7031', 'stat_botcount' => '1004', 'stat_fscount' => '74600', 'stat_fairteilercount' => '891']);
        $I->createRegion('Arbeitsgruppen Überregional', ['id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'parent_id' => RegionIDs::ROOT, 'type' => UnitType::BIG_CITY, 'master' => 392, 'mailbox_id' => 32678, 'email_name' => 'Foodsharing Arbeitsgruppen Überregional', 'stat_last_update' => '2020-05-24 02:17:57', 'stat_fetchweight' => '5176.00', 'stat_fetchcount' => '208', 'stat_postcount' => '53969', 'stat_betriebcount' => '1', 'stat_korpcount' => '0', 'stat_botcount' => '1', 'stat_fscount' => '3360']);

        $this->output->writeln('Inserting fetch weight values');
        $this->insertFetchWeightValues($I);
        $this->output->writeln('');

        $this->output->writeln('Adding content');
        $this->createContent($I);
        $this->output->writeln('');

        $I->_getDbh()->commit();
    }
}
