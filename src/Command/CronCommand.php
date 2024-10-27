<?php

namespace Foodsharing\Command;

use Foodsharing\Modules\Mails\MailsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('foodsharing:cronjob', 'Executes regular maintenance tasks.')]
class CronCommand extends Command
{
    public function __construct(
        private readonly MailsService $mailsControl
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
        $this->mailsControl->fetchMails();

        return Command::SUCCESS;
    }
}
