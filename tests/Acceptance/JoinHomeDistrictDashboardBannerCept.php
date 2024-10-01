<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

$I = new AcceptanceTester($scenario);

$I->wantTo('join a home district via dashboard banner if none is choosen yet.');

/*
This foodsaver has bezirk_id 0, so no home district
*/
$foodsaver = $I->createFoodsaver();

$I->login($foodsaver['email']);

$I->amOnPage('/?page=dashboard');
$I->waitForActiveAPICalls();
$I->waitForElement('.testing-region-join');
$I->see('Bitte auswählen', ['css' => '.testing-region-join-select']);
$I->click('.testing-region-join .btn.btn-secondary');
$I->waitForElement('.testing-region-join');
