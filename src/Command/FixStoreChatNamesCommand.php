<?php

namespace Foodsharing\Command;

use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Modules\Store\StoreTransactions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('foodsharing:fixStoreChatNames', 'Updates all store conversation names to the current store names.')]
class FixStoreChatNamesCommand extends Command
{
    public function __construct(
        private readonly StoreGateway $storeGateway,
        private readonly StoreTransactions $storeTransactions
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('This command should just be needed as a one-time fix to bring all store conversation names up to date. It can also be used whenever conventions on store chat naming changes.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stores = $this->storeGateway->getStores();
        foreach ($stores as $store) {
            $this->storeTransactions->setStoreNameInConversations($store['id'], $store['name']);
        }

        return Command::SUCCESS;
    }
}
