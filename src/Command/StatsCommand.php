<?php

namespace Foodsharing\Command;

use Foodsharing\Modules\Stats\StatsControl;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('foodsharing:stats', 'Executes foodsaver, stores and regions statistics tasks.')]
class StatsCommand extends Command
{
    private const VALID_JOBS = [
        'foodsaver', 'betriebe', 'bezirke'
    ];

    public function __construct(
        private readonly StatsControl $statsControl
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('This command executes background tasks that need to be run in regular intervals.
		While the exact interval should not matter, it must still be chosen sane. See implementation for details.');
        $this->addArgument('job_name', InputArgument::OPTIONAL,
            'Which job to run (omit to run all of them)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($job = $input->getArgument('job_name')) {
            if (!in_array($job, self::VALID_JOBS)) {
                return Command::INVALID;
            }
            $this->statsControl->$job();
        } else {
            foreach (self::VALID_JOBS as $method) {
                $this->statsControl->$method();
            }
        }

        return Command::SUCCESS;
    }
}
