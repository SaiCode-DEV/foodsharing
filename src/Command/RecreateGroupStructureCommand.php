<?php

namespace Foodsharing\Command;

use Foodsharing\Modules\Maintenance\MaintenanceGateway;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('maintenance:recreateGroupStructure')]
class RecreateGroupStructureCommand extends Command
{
    public function __construct(
        private readonly MaintenanceGateway $maintenanceGateway
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->maintenanceGateway->recreateClosure();

        return Command::SUCCESS;
    }
}
