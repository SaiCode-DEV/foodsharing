<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

$I = new AcceptanceTester($scenario);

$I->wantTo('Check if people can give self banana');
$pass = sq('pass');

$foodsaver = $I->createFoodsaver($pass);

$I->login($foodsaver['email'], $pass);

$I->amOnPage('/user/' . $foodsaver['id'] . '/profile');
$I->see($foodsaver['name']);

$I->waitForElementVisible('#bananas > a > span', 4);
$I->click('#bananas > a > span');
// This might need a wait as well but it would be a bit harder to figure out.
// if this test ever fails here, rerun & think about a fix!
$I->dontSee('Schenke ' . $foodsaver['id'] . ' eine Banane');
