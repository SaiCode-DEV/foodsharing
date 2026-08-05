<?php

namespace Foodsharing\Command;

use Foodsharing\Modules\Maintenance\MaintenanceService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('foodsharing:maintenance:cleanup2', 'Executes the part of the daily maintenance tasks that cleans up the database')]
class MaintenanceCleanupLowPriorityCommand extends Command
{
    public function __construct(
        private readonly MaintenanceService $maintenanceControl
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('This command executes background tasks that need to be run in daily intervals. This part contains all the clean-up tasks that should to be executed regularly, but do not break anything if they fail. Clean-up with high priority should be added to the other class.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->maintenanceControl->deleteInactiveUsers();
        $this->maintenanceControl->deleteUnusedImages();
        $this->maintenanceControl->deleteOldPassRequests();
        $this->maintenanceControl->cleanOldQuizSessionData();
        $this->maintenanceControl->deleteTestQuizSessions();
        $this->maintenanceControl->deleteHiddenForumPosts();
        $this->maintenanceControl->deleteExpiredOAuthTokens();
        $this->maintenanceControl->deleteExpiredMailChanges();

        return Command::SUCCESS;
    }
}
