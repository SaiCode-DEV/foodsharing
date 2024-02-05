<?php

namespace Foodsharing\Command;

use Foodsharing\Modules\Lookup\LookupControl;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('foodsharing:lookup', 'Executes LookupControl')]
class LookupCommand extends Command
{
    private const VALID_OPS = ['lookup', 'deleteOldUsers'];

    public function __construct(
        private readonly LookupControl $lookupControl
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp("either: \n" .
            "lookup: looks up a bunch of information about foodsavers by a list of emails\n" .
            "deleteOldUsers: deletes users using the given list of emails, if they have not logged in for more than 6 months\n");
        $this->addArgument('operation', InputArgument::REQUIRED,
            'either "lookup" or "deleteOldUsers"');
        $this->addArgument('csv path', InputArgument::REQUIRED,
            'path to a CSV file containing one email per row');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // TODO pass $input->getArgument('csv path') explicitly
        //  currently, LookupControl grabs it directly from $argv, which luckily matches up here

        $op = $input->getArgument('operation');
        if (!in_array($op, self::VALID_OPS)) {
            return Command::INVALID;
        }

        match ($op) {
            'lookup' => $this->lookupControl->lookup(),
            'deleteOldUsers' => $this->lookupControl->deleteOldUsers(),
            default => null
        };

        return Command::SUCCESS;
    }
}
