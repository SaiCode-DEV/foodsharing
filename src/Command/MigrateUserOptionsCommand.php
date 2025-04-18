<?php

namespace Foodsharing\Command;

use DomainException;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\UserOptionType;
use Foodsharing\Modules\Settings\SettingsGateway;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('foodsharing:migrateUserOptions', 'Migrates fs_foodsaver.option to fs_foodsaver_has_options.')]
class MigrateUserOptionsCommand extends Command
{
    public function __construct(
        private readonly SettingsGateway $settingsGateway,
        private readonly Database $database,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('This command should be only be used during update, to migrate user options.
         SQL Command to see if migration worked: SELECT u.id, email, option, option_type, option_value FROM `fs_foodsaver` as u left join fs_foodsaver_has_options as o on o.foodsaver_id=u.id;
        ');
        $this->addOption('dry', null, InputOption::VALUE_NONE, 'List the files but do not actually move them');
        $this->addOption('maximum', null, InputOption::VALUE_REQUIRED, 'Maximum number of accounts to migrate', 1000000);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isDryRun = $input->getOption('dry');
        $maximum = (int)$input->getOption('maximum');

        $users = $this->database->fetchAll('
            SELECT fs.id, fs.option
            FROM fs_foodsaver fs
            WHERE fs.option <> ""
        ');
        $users = array_filter($users, function ($user) {
            return !empty($user['option']) && trim($user['option']) !== '';
        });
        $output->writeln('Found options for ' . count($users) . ' users.');

        if (!$isDryRun & count($users) > 0) {
            $selectedUsers = array_slice($users, 0, min($maximum, count($users)));
            $output->writeln('Migrating options for ' . count($selectedUsers) . ' users.');

            foreach ($selectedUsers as $user) {
                $this->migrateFoodsaverOptionsToFoodsaverSettings($output, $user['id'], $user['option']);
            }
        }

        return Command::SUCCESS;
    }

    /**
     * This migration is required until release is on prod. and all user have logged in.
     */
    public function migrateFoodsaverOptionsToFoodsaverSettings(OutputInterface $output, int $userId, string $options): void
    {
        $options = unserialize($options);

        // Copy old options to fs_foodsaver_has_options table
        $migrated = 0;
        foreach ($options as $key => $val) {
            $optionType = UserOptionType::parse($key);
            if ($optionType === null) {
                $output->writeln("Try to load unknown user option: key='{$key}', value='{$val}'");
            } else {
                $this->settingsGateway->setUserOption($userId, $optionType, $val);
                ++$migrated;
            }
        }

        // Delete the old options
        if ($migrated === count($options)) {
            $this->database->update('fs_foodsaver', ['option' => ''], ['id' => $userId]);
        }
    }
}
