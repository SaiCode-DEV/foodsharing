<?php

namespace Foodsharing\Command;

use Foodsharing\Modules\Stats\StatsGateway;
use Foodsharing\Utility\ConsoleHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('foodsharing:stats', 'Executes foodsaver, stores and regions statistics tasks.')]
class StatsCommand extends Command
{
    private const array VALID_JOBS = [
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
        $this->addOption('recalculate', null, InputOption::VALUE_NONE, 'If the foodsaver statistics should be fully recalculated instead of incremented');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $recalculate = $input->getOption('recalculate');

        if ($job = $input->getArgument('job_name')) {
            if (!in_array($job, self::VALID_JOBS)) {
                return Command::INVALID;
            }
            $this->$job($recalculate);
        } else {
            foreach (self::VALID_JOBS as $method) {
                $this->$method($recalculate);
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Updates all foodsaver related statistics.
     * @param bool $recalculate Whether to use recalculate the stats instead of using previous stats
     */
    public function foodsaver(bool $recalculate = false): void
    {
        ConsoleHelper::info('Statistik Auswertung für Foodsaver');
        $this->statsGateway->updateFoodsaverStats();
        $this->statsGateway->updateFoodsaverIterativeStats($recalculate);
        ConsoleHelper::success('foodsaver ready :o)');
    }

    /**
     * Updates all store team related statistics.
     *
     * @param bool $recalculate Whether to use recalculate the stats instead of using previous stats - not implemented for this job, but added for signature consistency with the other jobs
     */
    public function betriebe(bool $recalculate = false): void
    {
        ConsoleHelper::info('Statistik Auswertung für Betriebe');
        $this->statsGateway->updateStoreUsersStats();
        ConsoleHelper::success('stores ready :o)');
    }

    /**
     * Updates all region related statistics.
     *
     * @param bool $recalculate Whether to use recalculate the stats instead of using previous stats
     */
    public function bezirke(bool $recalculate = false): void
    {
        ConsoleHelper::info('Statistik Auswertung für Bezirke');

        $this->statsGateway->updateNonIncrementalRegionStats();
        if ($recalculate) {
            $this->statsGateway->calculateIncrementalRegionStatsFromScratch();
        } else {
            $this->statsGateway->updateIncrementalRegionStats();
        }

        ConsoleHelper::success('region ready :o)');
    }
}
