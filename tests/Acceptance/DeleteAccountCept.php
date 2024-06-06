<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Codeception\Util\ActionSequence;
use Tests\Support\AcceptanceTester;

$I = new AcceptanceTester($scenario);

$I->wantTo('delete my account (being a foodsaver)');

$pass = sq('pass');

$foodsaver = $I->createFoodsaver($pass);

$I->login($foodsaver['email'], $pass);

$I->amOnPage('/?page=settings&sub=deleteaccount');

$I->click('#delete-account');
$I->performOn('#modal-delete-account', ActionSequence::build()
    ->wait(1) // Required screen fading hides text
    ->see('wirklich')
    ->click('//button[text()="Account löschen"]')
);
$I->waitForActiveAPICalls();

$I->seeInDatabase('fs_foodsaver', [
    'id' => $foodsaver['id'],
    'name' => null,
    'email' => null,
    'nachname' => null,
    'deleted_by' => $foodsaver['id']
]);

$I->seeInDatabase('fs_foodsaver_archive', [
    'id' => $foodsaver['id'],
    'name' => $foodsaver['name'],
    'email' => $foodsaver['email'],
    'nachname' => $foodsaver['nachname']
]);
