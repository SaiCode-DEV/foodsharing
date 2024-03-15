<?php

namespace Foodsharing\Command;

use DomainException;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\UserOptionType;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Settings\SettingsGateway;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('foodsharing:migrateUserOptions', 'Migrates fs_foodsaver.option to fs_foodsaver_has_options.')]
class MigrateUserOptionsCommand extends Command
{
    public function __construct(
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly SettingsGateway $settingsGateway
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('This command should be only be used during update, to migrate user options.
         SQL Command to see if migration worked: SELECT u.id, email, option, option_type, option_value FROM `fs_foodsaver` as u left join fs_foodsaver_has_options as o on o.foodsaver_id=u.id; 
        ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $users = $this->foodsaverGateway->getEmailAddresses();
        foreach ($users as $userId => $unused) {
            $this->migrateFoodsaverOptionsToFoodsaverSettings($userId);
        }

        return Command::SUCCESS;
    }

    /**
     * This migration is required until release is on prod. and all user have logged in.
     */
    public function migrateFoodsaverOptionsToFoodsaverSettings(int $userId): void
    {
        $options = $this->foodsaverGateway->getOption($userId);

        // move old options to fs_foodsaver_has_options table
        foreach ($options as $key => $val) {
            $optionType = UserOptionType::parse($key);
            if ($optionType === null) {
                throw new DomainException('Try to load unknown user option');
            }
            $this->settingsGateway->setUserOption($userId, $optionType, $val);
        }
    }
}
