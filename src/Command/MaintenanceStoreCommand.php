<?php

namespace Foodsharing\Command;

use Foodsharing\Modules\Maintenance\MaintenanceService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('foodsharing:maintenance:stores', 'Executes the part of the daily maintenance tasks that updates stores')]
class MaintenanceStoreCommand extends Command
{
    public function __construct(
        private readonly MaintenanceService $maintenanceControl
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('This command executes background tasks that need to be run in daily intervals. This part contains all everything related to stores.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->maintenanceControl->storeTriggerPickupWarnings();

        return Command::SUCCESS;
    }
}
