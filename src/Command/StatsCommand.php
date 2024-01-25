<?php

namespace Foodsharing\Command;

use Foodsharing\Modules\Stats\StatsControl;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('foodsharing:stats', 'Executes foodsaver, stores and regions statistics tasks.')]
class StatsCommand extends Command
{
    /**
     * @var StatsControl
     */
    private $statsControl;

    public function __construct(StatsControl $statsControl)
    {
        $this->statsControl = $statsControl;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('This command executes background tasks that need to be run in regular intervals.
		While the exact interval should not matter, it must still be chosen sane. See implementation for details.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->statsControl->foodsaver();
        $this->statsControl->betriebe();
        $this->statsControl->bezirke();

        return Command::SUCCESS;
    }
}
