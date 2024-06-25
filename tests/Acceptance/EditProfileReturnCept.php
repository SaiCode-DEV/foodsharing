<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

$I = new AcceptanceTester($scenario);

$I->wantTo('Check if someone editing a profile sees return to profile button and if return to profile button points to the edited profile');

$region = $I->createRegion();

$member = $I->createFoodsaver();
$ambassador = $I->createAmbassador(null, ['bezirk_id' => $region['id']]);

$I->addRegionAdmin($region['id'], $ambassador['id']);
$I->addRegionMember($region['id'], $member['id']);

$I->login($ambassador['email']);

$I->amOnPage('/user/' . $member['id'] . '/settings');

$I->seeInField('#input-lastname', $member['nachname']);

// ToDo: is this in frontend needed?
$I->click('Zurück zum Profil');
$I->seeCurrentUrlEquals('/user/' . $member['id'] . '/profile');
