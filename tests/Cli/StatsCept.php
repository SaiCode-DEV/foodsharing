<?php

declare(strict_types=1);

namespace Tests\Cli;

use Tests\Support\CliTester;

$I = new CliTester($scenario);
$I->am('Cron');
$I->wantTo('see that stat generation jobs do execute');
$I->amInPath('');
$I->runShellCommand('bin/console foodsharing:stats foodsaver', false);
$I->seeInShellOutput('foodsaver ready :o)');
$I->runShellCommand('bin/console foodsharing:stats betriebe', false);
$I->seeInShellOutput('stores ready :o)');
$I->runShellCommand('bin/console foodsharing:stats bezirke', false);
$I->seeInShellOutput('region ready :o)');
