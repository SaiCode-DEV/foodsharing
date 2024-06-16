<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

$phonenumber = '+49483123213';
$mobilenumber = '+491518417482';
$aboutMeIntern = 'Ich mag foodsharing.';
$I = new AcceptanceTester($scenario);
$I->wantTo('edit my profile fields as a foodsaver');

$foodsaver = $I->createFoodsaver(null, ['name' => 'fs', 'nachname' => 'one']);

$I->login($foodsaver['email']);

$I->amOnPage('/user/current/settings');
$I->waitForElement('#phone', 10);

// ToDo: Birthdate and location

$I->executeJS('$("#phone").val("' . $phonenumber . '")');
$I->executeJS('$("#mobile").val("' . $mobilenumber . '")');
$I->executeJS('$("#about_me_intern").val("' . $aboutMeIntern . '")');

$I->click('Speichern');
$I->waitForText('Erfolgreich abgeschlossen');

$I->seeInField('#phone', $phonenumber);
$I->seeInField('#mobile', $mobilenumber);
$I->seeInField('#about_me_intern', $aboutMeIntern);
