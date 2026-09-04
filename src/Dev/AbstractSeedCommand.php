<?php

namespace Foodsharing\Dev;

use Codeception\Command\Shared\ConfigTrait;
use Codeception\Lib\Di;
use Codeception\Lib\ModuleContainer;
use Foodsharing\Modules\Core\DBConstants\Configuration\ConfigurationCategory;
use Foodsharing\Modules\Core\DBConstants\Configuration\ConfigurationKey;
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

    /**
     * The Twingle project ids and the switches of the donation page. The addresses of the embedded
     * forms are not part of it: the test instance must not load anything from twingle.de, so only
     * the dev seed passes them in.
     */
    protected function insertDonationConfiguration(Foodsharing $I, array $additionalEntries = []): void
    {
        $donation = [
            ConfigurationKey::DONATION_CAMPAIGN_ID->value => '12573',
            ConfigurationKey::DONATION_FRIENDSHIP_CIRCLE_ID->value => '398',
            ConfigurationKey::DONATION_MODAL_ID->value => '12573',
            ConfigurationKey::DONATION_ONE_TIME_DONATION_ID->value => '384',
            ConfigurationKey::DONATION_SHOW_CAMPAIGN_CARD->value => '1',
            ConfigurationKey::DONATION_SHOW_DONATION_MODAL->value => '0',
            ConfigurationKey::DONATION_SHOW_DONATION_MODAL_IN_HOURS_FOR_LOGGED_IN_USERS->value => '24',
            ConfigurationKey::DONATION_SHOW_DONATION_MODAL_IN_HOURS_FOR_LOGGED_OUT_USERS->value => '1',
            ConfigurationKey::DONATION_SHOW_CAMPAIGN_PART_1->value => '1',
            ConfigurationKey::DONATION_SHOW_CAMPAIGN_PART_2->value => '1',
            ConfigurationKey::DONATION_SHOW_CAMPAIGN_GALLERY->value => '1',
            ConfigurationKey::DONATION_MODAL_INFO_URL->value => 'donation/campaign',
        ] + $additionalEntries;
        foreach ($donation as $key => $value) {
            $I->haveInDatabase('configuration', ['key' => $key, 'value' => $value, 'category' => ConfigurationCategory::DONATION->value]);
        }
    }

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
