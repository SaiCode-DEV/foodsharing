<?php

declare(strict_types=1);

namespace Tests\Cli;

use Tests\Support\CliTester;

$I = new CliTester($scenario);
$I->am('Cron');
$I->wantTo('see that CronCommand exists, starts, and exits with empty output');
$I->amInPath('');
$I->runShellCommand('bin/console foodsharing:cron', false);
$I->seeResultCodeIs(0);
$I->seeShellOutputMatches('/^$/');
