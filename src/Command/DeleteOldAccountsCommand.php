<?php

namespace Foodsharing\Command;

use Foodsharing\Modules\Maintenance\MaintenanceService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('foodsharing:deleteOldAccounts', 'Removes accounts that have been inactive for more than 5 years.')]
class DeleteOldAccountsCommand extends Command
{
    public function __construct(
        private readonly MaintenanceService $maintenanceControl,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry', null, InputOption::VALUE_NONE, 'Do not actually delete the accounts');
        $this->addOption('maximum', null, InputOption::VALUE_REQUIRED, 'Maximum number of accounts to delete', MAX_DELETE_OLD_ACCOUNTS_PER_DAY);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $maximum = $input->getOption('maximum');
        $dryRun = $input->getOption('dry');
        $this->maintenanceControl->deleteInactiveUsers($dryRun, $maximum);

        return Command::SUCCESS;
    }
}
