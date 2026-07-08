<?php

namespace Foodsharing\Command;

use Foodsharing\Modules\Mails\IncomingMailsService;
use Foodsharing\Modules\Report\ReportNotificationService;
use Foodsharing\Modules\Voting\VotingNotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('foodsharing:cronjob', 'Executes regular maintenance tasks.')]
class CronCommand extends Command
{
    public function __construct(
        private readonly IncomingMailsService $incomingMailsService,
        private readonly VotingNotificationService $votingNotificationService,
        private readonly ReportNotificationService $reportNotificationService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('This command executes background tasks that need to be run in regular intervals.
		While the exact interval should not matter, it must still be chosen sane. See implementation for details.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            // Fetch emails, may fail on development systems without proper mail
            // setup
            $this->incomingMailsService->fetchMails();
        } catch (\Exception $e) {
            $output->writeln('Error fetching emails: ' . $e->getMessage());
        }

        // Send notifications for polls that just started or are about to end
        $this->votingNotificationService->notifyForStartedPolls();
        $this->votingNotificationService->notifyForEndingPolls();

        // Send reminders for reports with due reminder dates
        $this->reportNotificationService->notifyForReminders();

        return Command::SUCCESS;
    }
}
