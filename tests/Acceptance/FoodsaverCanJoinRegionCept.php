<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Tests\Support\AcceptanceTester;

$I = new AcceptanceTester($scenario);

$I->wantTo('Join another region');

/* This user is explicitly unverified to trigger #404; also actually newly registered users are unverified. */
$user = $I->createFoodsaver(null, ['verified' => 0]);
$region = $I->createRegion(null, ['parent_id' => 0]);
$ambassador = $I->createAmbassador(null, ['bezirk_id' => $region['id']]);
$I->addRegionAdmin($region['id'], $ambassador['id']);
$welcomeAdmin = $I->createFoodsaver(null, ['verified' => 0]);
$welcomeGroup = $I->createWorkingGroup('Begrüßung', ['parent_id' => $region['id']]);
$I->haveInDatabase('fs_region_function', ['region_id' => $welcomeGroup['id'], 'function_id' => WorkgroupFunction::WELCOME, 'target_id' => $region['id']]);
$I->addRegionAdmin($welcomeGroup['id'], $welcomeAdmin['id']);

$I->login($user['email']);
/*
 * As the user does not have a home region, it gets to select one by default. Maybe this behaviour changes and we need
 * to open the choser?
 */
$I->amOnPage('/?page=dashboard');
$I->waitForActiveAPICalls();
$I->waitForElement('.testing-region-join');
$I->see('Bitte auswählen', ['css' => '.testing-region-join-select']);
$I->selectOption('.testing-region-join-select', $region['name']);
$I->click('.testing-region-join .btn.btn-primary');
$I->waitForActiveAPICalls();
$I->amOnPage('/region');
$I->see('Neues Thema verfassen');
$I->dontSeeInDatabase('fs_foodsaver_has_bell', ['foodsaver_id' => $ambassador['id']]);
$I->seeInDatabase('fs_foodsaver_has_bell', ['foodsaver_id' => $welcomeAdmin['id']]);
$I->seeInDatabase('fs_foodsaver_has_bezirk', ['foodsaver_id' => $user['id'], 'bezirk_id' => $region['id']]);
