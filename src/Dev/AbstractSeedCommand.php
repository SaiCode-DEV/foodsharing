<?php

namespace Foodsharing\Dev;

use Codeception\Command\Shared\ConfigTrait;
use Codeception\Lib\Di;
use Codeception\Lib\ModuleContainer;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tests\Support\Helper\Foodsharing;

/**
 * Base class for both seed script (SeedCommand and TestSeedCommand) which contains common functions. Child classes
 * should implement the `seed` function.
 */
abstract class AbstractSeedCommand extends Command
{
    use ConfigTrait;
    protected Foodsharing $helper;
    protected OutputInterface $output;
    protected ?ProgressBar $progressBar = null;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $this->output->setFormatter(new OutputFormatter(true));
        $this->progressBar = new ProgressBar($this->output);
        $this->progressBar->setOverwrite(true);
        $this->progressBar->setBarCharacter('<fg=green>=</>');
        $this->progressBar->setEmptyBarCharacter('<fg=red>-</>');
        $this->progressBar->setProgressCharacter('<fg=green>➤</>');
        $this->progressBar->setBarWidth(50);

        $config = $this->getGlobalConfig();
        $di = new Di();
        $module = new ModuleContainer($di, $config);
        $this->helper = $module->create(Foodsharing::class);
        $this->helper->_initialize();

        $this->output->writeln('Clearing existing ' . FS_ENV . ' seed data');
        $this->helper->clear();

        $this->output->writeln('Seeding ' . FS_ENV . ' database');
        $start = microtime(true);
        $this->helper->_getDbh()->beginTransaction();
        $this->helper->_getDriver()->executeQuery('SET FOREIGN_KEY_CHECKS=1;', []);
        $this->seed();
        $this->helper->_getDbh()->commit();
        $this->output->writeln('Database seeding took ' . round(microtime(true) - $start, 2) . ' seconds');

        return Command::SUCCESS;
    }

    abstract protected function seed(): void;

    protected function createContent(Foodsharing $I): void
    {
        $contentData = $this->loadJsonFromFile('content.json');
        foreach ($contentData as $content) {
            $I->createContent($content);
        }
    }

    protected function insertFetchWeightValues(Foodsharing $I)
    {
        $values = [
            [0, 1.5],
            [1, 2.0],
            [2, 4.0],
            [3, 7.5],
            [4, 15.0],
            [5, 25.0],
            [6, 35.0],
            [7, 45.0],
            [8, 64.0],
        ];
        foreach ($values as $value) {
            $I->haveInDatabase('fs_fetchweight', ['id' => $value[0], 'weight' => $value[1]]);
        }
    }

    /**
     * Loads JSON data from the file and returns it as an array.
     *
     * @param string $filename the file path relative to this file
     * @param bool $isAssociative if the data is supposed to be loaded as an associative array
     * @throws RuntimeException if the file does not exist or if the JSON data is invalid
     */
    protected function loadJsonFromFile(string $filename, bool $isAssociative = true): array
    {
        $file = 'src/Dev/' . $filename;
        if (!file_exists($file)) {
            throw new RuntimeException("File {$file} not found");
        }

        $data = json_decode(file_get_contents($file), $isAssociative);
        if (is_null($data)) {
            throw new RuntimeException("JSON data from file {$file} could not be decoded");
        }

        return $data;
    }
}
