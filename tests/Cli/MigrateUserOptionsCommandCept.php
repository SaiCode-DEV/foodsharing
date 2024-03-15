<?php

declare(strict_types=1);

namespace Tests\Cli;

use Tests\Support\CliTester;

$I = new CliTester($scenario);

$foodsaver1 = $I->createFoodsaver(null, ['option' => serialize(['activity-listings' => '[1,2,3]'])]);
$foodsaver2 = $I->createFoodsaver(null, ['option' => serialize(['activity-listings' => '[4]'])]);
$foodsaver3 = $I->createFoodsaver(null, ['option' => '']);

$I->runShellCommand('bin/console foodsharing:migrateUserOptions');

$I->seeInDatabase('fs_foodsaver_has_options', [
        'foodsaver_id' => $foodsaver1['id'],
        'option_type' => 2,
        'option_value' => '[1,2,3]']);

$I->seeInDatabase('fs_foodsaver_has_options', [
    'foodsaver_id' => $foodsaver2['id'],
    'option_type' => 2,
    'option_value' => '[4]']);

$I->dontSeeInDatabase('fs_foodsaver_has_options', [
    'foodsaver_id' => $foodsaver3['id'],
    'option_type' => 2]);
