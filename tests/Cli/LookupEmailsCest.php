<?php

declare(strict_types=1);

namespace Tests\Cli;

use DateTime;
use Tests\Support\CliTester;

class LookupEmailsCest
{
    final public function testLookupEmail(CliTester $I): void
    {
        $I->am('Cron');
        $I->wantTo('see that lookupEmail method works');
        $fsA = $I->createFoodsaver(null, ['email' => 'tollerBenutzer@ichbinneemail.de', 'last_login' => '2016-11-11 11:11:00']);
        $fsB = $I->createFoodsaver(null, ['email' => 'zweiterBenutzer@gmail.com', 'last_login' => null]);
        $fsC = $I->createFoodsaver(null, ['email' => '2zweiterBenutzer@gmail.com', 'last_login' => (new DateTime())->format('Y-m-d H:i:s')]);
        $I->seeInDatabase('fs_foodsaver', ['id' => $fsA['id'], 'deleted_at' => null]);
        $I->seeInDatabase('fs_foodsaver', ['id' => $fsB['id'], 'deleted_at' => null]);
        $I->seeInDatabase('fs_foodsaver', ['id' => $fsC['id'], 'deleted_at' => null]);
        $I->amInPath('');
        $I->runShellCommand('FS_ENV=test bin/console foodsharing:lookup lookup tests/Support/Data/emaillist.csv', false);
        $I->seeInShellOutput($fsA['id'] . ',');
        $I->seeInShellOutput($fsB['id'] . ',');
        $I->runShellCommand('FS_ENV=test bin/console foodsharing:lookup deleteOldUsers tests/Support/Data/emaillist.csv', false);

        $a = $I->grabFromDatabase('fs_foodsaver', 'deleted_at', ['id' => $fsA['id']]);
        $I->assertNotNull($a);

        $I->seeInDatabase('fs_foodsaver', ['id' => $fsB['id'], 'deleted_at' => null]);
        $I->seeInDatabase('fs_foodsaver', ['id' => $fsC['id'], 'deleted_at' => null]);
    }
}
