<?php

namespace Foodsharing\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('foodsharing:setup', 'Prepares the environment to run the foodsharing application.')]
class SetupCommand extends Command
{
    protected function configure(): void
    {
        $this->setHelp('This command creates necessary folders so they can be used inside the app. It might be expanded to do more a-like things as well.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        umask(0);
        $progressBar = new ProgressBar($output);
        $dirs = [
            'assets',
            'images',
            'images/basket',
            'images/wallpost',
            'images/picture',
            'images/workgroup',
            'data',
            'data/attach',
            'data/mailattach',
            'data/mailattach/tmp',
            'data/pass',
            'data/uploads',
            'data/visite',
            'cache',
            'cache/searchindex',
            'var',
            'var/cache',
            'var/cache/dev',
            'var/cache/test',
            'var/cache/nginx/client_temp',
            'var/log',
            'tmp'
        ];

        foreach ($progressBar->iterate($dirs) as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
        }

        return Command::SUCCESS;
    }
}
