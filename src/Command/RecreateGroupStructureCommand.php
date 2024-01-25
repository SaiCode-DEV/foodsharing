<?php

namespace Foodsharing\Command;

use Foodsharing\Modules\Group\GroupGateway;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('maintenance:recreateGroupStructure')]
class RecreateGroupStructureCommand extends Command
{
    protected GroupGateway $groupGateway;

    public function __construct(GroupGateway $groupGateway)
    {
        $this->groupGateway = $groupGateway;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->groupGateway->recreateClosure();

        return Command::SUCCESS;
    }
}
