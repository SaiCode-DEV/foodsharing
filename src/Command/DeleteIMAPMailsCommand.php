<?php

namespace Foodsharing\Command;

use Foodsharing\Modules\Maintenance\MaintenanceControl;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('foodsharing:deleteOldIMAPMails', 'Deletes old mails from IMAP folders.')]
class DeleteIMAPMailsCommand extends Command
{
    private readonly MaintenanceControl $maintenanceControl;

    public function __construct(MaintenanceControl $maintenanceControl)
    {
        $this->maintenanceControl = $maintenanceControl;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('This command is also run by the daily cronjob. Unprocessed Bounce-Mails or unprocessable incoming Mails are deleted.');
        $this->addArgument('delete_delay_days', InputArgument::OPTIONAL, 'Days of retention before deletion', 30);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->maintenanceControl->deleteImapFolderMails($input->getArgument('delete_delay_days'));

        return Command::SUCCESS;
    }
}
