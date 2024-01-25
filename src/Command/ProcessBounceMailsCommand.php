<?php

namespace Foodsharing\Command;

use Foodsharing\Lib\Mail\BounceProcessing;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('foodsharing:process-bounce-emails', 'fetches email bounces and stores them in the database')]
class ProcessBounceMailsCommand extends Command
{
    private $bounceProcessing;

    public function __construct(BounceProcessing $bounceProcessing)
    {
        $this->bounceProcessing = $bounceProcessing;
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->bounceProcessing->process();

        return Command::SUCCESS;
    }
}
