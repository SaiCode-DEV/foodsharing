<?php

declare(strict_types=1);

namespace Tests\Cli;

use Tests\Support\CliTester;

class MaintenanceCest
{
    final public function sendWarningAndDeleteUnconfirmedFetchdates(CliTester $I): void
    {
        $store = $I->createStore(1, null, null, ['betrieb_status_id' => '5']);
        $store_established = $I->createStore(1, null, null, ['betrieb_status_id' => '5']);

        $fetcher_unconfirmed_past_1 = $I->createFoodsaver();
        $fetcher_unconfirmed_past_2 = $I->createFoodsaver();
        $fetcher_unconfirmed_future = $I->createFoodsaver();

        $fetcher_confirmed_past = $I->createFoodsaver();
        $fetcher_confirmed_future = $I->createFoodsaver();

        $store_manager_1 = $I->createFoodsaver();

        $I->addStoreTeam($store['id'], $fetcher_unconfirmed_past_1['id'], false, false, true);
        $I->addStoreTeam($store['id'], $fetcher_unconfirmed_past_2['id'], false, false, true);
        $I->addStoreTeam($store['id'], $fetcher_unconfirmed_future['id'], false, false, true);

        $I->addStoreTeam($store['id'], $fetcher_confirmed_past['id'], false, false, true);
        $I->addStoreTeam($store['id'], $fetcher_confirmed_future['id'], false, false, true);

        $I->addStoreTeam($store_established['id'], $store_manager_1['id'], true, false, true);
        $I->addRecurringPickup($store_established['id'], ['dow' => (((int)date('w')) + 1) % 7]);

        $dataset_unconfirmed_past_1 = [
            'foodsaver_id' => $fetcher_unconfirmed_past_1['id'],
            'betrieb_id' => $store['id'],
            'date' => '2001-02-25 08:55',
            'confirmed' => 0
        ];
        $I->haveInDatabase('fs_abholer', $dataset_unconfirmed_past_1);

        $dataset_unconfirmed_past_2 = [
            'foodsaver_id' => $fetcher_unconfirmed_past_2['id'],
            'betrieb_id' => $store['id'],
            'date' => '2008-08-25 17:55',
            'confirmed' => 0
        ];
        $I->haveInDatabase('fs_abholer', $dataset_unconfirmed_past_2);

        $dataset_unconfirmed_future = [
            'foodsaver_id' => $fetcher_unconfirmed_future['id'],
            'betrieb_id' => $store['id'],
            'date' => '2500-06-25 22:20',
            'confirmed' => 0
        ];
        $I->haveInDatabase('fs_abholer', $dataset_unconfirmed_future);

        $dataset_confirmed_past = [
            'foodsaver_id' => $fetcher_confirmed_past['id'],
            'betrieb_id' => $store['id'],
            'date' => '2008-11-25 17:55',
            'confirmed' => 1
        ];
        $I->haveInDatabase('fs_abholer', $dataset_confirmed_past);

        $dataset_confirmed_future = [
            'foodsaver_id' => $fetcher_confirmed_future['id'],
            'betrieb_id' => $store['id'],
            'date' => '2500-05-25 22:20',
            'confirmed' => 1
        ];
        $I->haveInDatabase('fs_abholer', $dataset_confirmed_future);

        $I->am('Cron');
        $I->wantTo('see that maintenance jobs do execute');
        $I->amInPath('');
        $I->runShellCommand('bin/console foodsharing:foodsharing:maintenance:regions');
        $I->runShellCommand('bin/console foodsharing:foodsharing:maintenance:bells');
        $I->runShellCommand('bin/console foodsharing:foodsharing:maintenance:stores');
        $I->runShellCommand('bin/console foodsharing:foodsharing:maintenance:cleanup1');
        $I->runShellCommand('bin/console foodsharing:foodsharing:maintenance:cleanup2');

        $I->seeInShellOutput('send 1 warnings...');
        $I->seeInShellOutput('updating Wien BIEB group');
    }

    final public function clearExpiredMailChangeRequests(CliTester $I): void
    {
        $expiredUser = $I->createFoodsaver();
        $freshUser = $I->createFoodsaver();
        $I->haveInDatabase('fs_mailchange', [
            'foodsaver_id' => $expiredUser['id'],
            'newmail' => 'expired@example.com',
            'time' => date('Y-m-d H:i:s', strtotime('-8 days')),
            'token' => 'expiredtoken1234',
        ]);
        $I->haveInDatabase('fs_mailchange', [
            'foodsaver_id' => $freshUser['id'],
            'newmail' => 'fresh@example.com',
            'time' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'token' => 'freshtoken12345',
        ]);

        $I->amInPath('');
        $I->runShellCommand('bin/console foodsharing:maintenance:cleanup2');

        $I->dontSeeInDatabase('fs_mailchange', ['token' => 'expiredtoken1234']);
        $I->seeInDatabase('fs_mailchange', ['token' => 'freshtoken12345']);
    }
}
