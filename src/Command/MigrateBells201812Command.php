<?php

namespace Foodsharing\Command;

use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Store\PickupGateway;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('migrations:2018-12-bells', 'Recreates bells that are handled differently since the 2018-12 release')]
class MigrateBells201812Command extends Command
{
    public function __construct(
        private readonly Database $database,
        private readonly PickupGateway $pickupGateway
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /*
         * Delete existing store bells: Before this deployment, bells were not stored in the database. So all bells with
         * "store" in their identifier were created in beta and could possibly be closed by their foodsavers (which was
         * temporarily possible in beta). PickupGateway::updateBellNotificationForBiebs() will not update the entries in
         * fs_foodsaver_has_bell, so those foodsavers would loose their bells and not get new ones. If the bells in fs_bell are deleted,
         * PickupGateway::updateBellNotificationForBiebs() will create new ones with new entries in fs_foodsaver_has_bell
         */
        $this->database->execute('DELETE FROM fs_bell WHERE identifier RLIKE "store-([0-9])+"');

        // get all store ids
        $storeIds = $this->database->fetchAllValuesByCriteria('fs_betrieb', 'id', []);

        // update all store bells
        foreach ($storeIds as $storeId) {
            $this->pickupGateway->updateBellNotificationForStoreManagers($storeId);
        }

        return Command::SUCCESS;
    }
}
