<?php

namespace Foodsharing\Command;

use Foodsharing\Modules\Maintenance\MaintenanceService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('foodsharing:maintenance:cleanup1', 'Executes the part of the daily maintenance tasks that cleans up the database')]
class MaintenanceCleanupHighPriorityCommand extends Command
{
    public function __construct(
        private readonly MaintenanceService $maintenanceControl
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('This command executes background tasks that need to be run in daily intervals. This part contains all the clean-up tasks that need to be executed successfully every day. Clean-up with low priority should be added to the other class.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->maintenanceControl->deactivateBaskets();
        $this->maintenanceControl->deleteOldRegistrationAttempts();

        return Command::SUCCESS;
    }
}
