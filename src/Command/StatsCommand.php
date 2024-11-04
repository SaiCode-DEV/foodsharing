<?php

namespace Foodsharing\Command;

use Foodsharing\Modules\Stats\StatsGateway;
use Foodsharing\Utility\ConsoleHelper;
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
        private readonly StatsGateway $statsGateway,
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
            $this->$job();
        } else {
            foreach (self::VALID_JOBS as $method) {
                $this->$method();
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Updates all foodsaver related statistics.
     */
    public function foodsaver(): void
    {
        ConsoleHelper::info('Statistik Auswertung für Foodsaver');
        $this->statsGateway->updateFoodsaverStats();
        ConsoleHelper::success('foodsaver ready :o)');
    }

    /**
     * Updates all store team related statistics.
     */
    public function betriebe(): void
    {
        ConsoleHelper::info('Statistik Auswertung für Betriebe');
        $this->statsGateway->updateStoreUsersStats();
        ConsoleHelper::success('stores ready :o)');
    }

    /**
     * Updates all region related statistics.
     *
     * @param bool $updateIncrementally Whether to use previous stats to speed up calculations
     */
    public function bezirke(bool $updateIncrementally = true): void
    {
        ConsoleHelper::info('Statistik Auswertung für Bezirke');

        $this->statsGateway->updateNonIncrementalRegionStats();
        if ($updateIncrementally) {
            $this->statsGateway->updateIncrementalRegionStats();
        } else {
            $this->statsGateway->calculateIncrementalRegionStatsFromScratch();
        }

        ConsoleHelper::success('region ready :o)');
    }
}
