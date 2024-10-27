<?php

namespace Foodsharing\Command;

use Foodsharing\Modules\Maintenance\MaintenanceService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('foodsharing:daily-cronjob', 'Executes daily maintenance tasks.')]
class DailyMaintenanceCommand extends Command
{
    public function __construct(
        private readonly MaintenanceService $maintenanceControl
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('This command executes background tasks that need to be run in daily intervals.
		While the exact interval should not matter, it must still be chosen sane. See implementation for details.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->maintenanceControl->daily();

        return Command::SUCCESS;
    }
}
