<?php

namespace Foodsharing\Command;

use Foodsharing\Modules\Mails\MailsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('foodsharing:mailqueueworker', 'Processes the outgoing mail queue')]
class MailQueueWorkerCommand extends Command
{
    public function __construct(
        private readonly MailsService $maintenanceControl
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('This is intended to run indefinitely in the background.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Starting MailsControl::queueWorker...');
        // this will keep running indefinitely
        $this->maintenanceControl->queueWorker();

        return Command::SUCCESS;
    }
}
