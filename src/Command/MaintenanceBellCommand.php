<?php

namespace Foodsharing\Command;

use Foodsharing\Modules\Maintenance\MaintenanceService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('foodsharing:maintenance:bells', 'Executes the part of the daily maintenance tasks that triggers bell updates')]
class MaintenanceBellCommand extends Command
{
    public function __construct(
        private readonly MaintenanceService $maintenanceControl
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('This command executes background tasks that need to be run in daily intervals. This part triggers updates of old bells notifications.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->maintenanceControl->triggerBellUpdates();

        return Command::SUCCESS;
    }
}
