<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

$I = new AcceptanceTester($scenario);

$description = 'my basket';
$updateDescription = sq('upd');
$pass = sq('pass');

$foodsaver = $I->createFoodsaver($pass);

$I->wantTo('Ensure I can create a food basket');

$I->login($foodsaver['email'], $pass);

$I->amOnPage('/');
$I->see('Essenskörbe', ['css' => '.testing-basket-dropdown']);
$I->click('.testing-basket-dropdown > .nav-link');
$I->waitForText('Essenskorb anlegen');
$I->click('.testing-basket-create');
$I->waitForText('Beschreibung, Bild, Übergabeort und Zeitraum sind öffentlich sichtbar.');

$I->fillField('#basket-description-input', $description);
$I->seeCheckboxIsChecked('#chat-checkbox');
$I->dontSeeCheckboxIsChecked('#phone-checkbox');
$I->dontSee('Telefonnummer');
$I->click('#chat-checkbox + .custom-control-label');
$I->see('Bitte wähle eine Möglichkeit aus, wie der Essenskorb angefragt werden soll.');
$I->click('#phone-checkbox + .custom-control-label');
$I->see('Telefonnummer');
$I->fillField('#phone-number-input', '12345');

$I->selectOption('#duration-select', 'Eine Woche');

$I->click('Speichern');
$I->waitForActiveAPICalls();

$I->seeInDatabase('fs_basket', [
    'description' => $description,
    'foodsaver_id' => $foodsaver['id'],
    'handy' => '12345'
]);

$id = $I->grabFromDatabase('fs_basket', 'id', ['description' => $description,
    'foodsaver_id' => $foodsaver['id']]);

//Check update of the foodbasket
$I->amOnPage($I->foodBasketInfoUrl($id));
$I->waitForActiveAPICalls();
$I->click('Essenskorb bearbeiten');
$I->waitForText('Beschreibung, Bild, Übergabeort und Zeitraum sind öffentlich sichtbar.');

$I->fillField('#basket-description-input', $description . ' edited');
$I->click('#chat-checkbox + .custom-control-label');
$I->click('Speichern');
$I->waitForActiveAPICalls();
$I->waitForPageBody();
$I->see('Aktualisiert ');
$I->see($description . ' edited');

$I->seeInDatabase('fs_basket', [
    'description' => $description . ' edited',
    'foodsaver_id' => $foodsaver['id'],
    'handy' => '12345'
]);

$picker = $I->createFoodsaver();

$nick = $I->haveFriend('nick');
$nick->does(
    static function (AcceptanceTester $I) use ($id, $picker) {
        $I->login($picker['email']);
        $I->amOnPage($I->foodBasketInfoUrl($id));
        $I->waitForPageBody();

        $I->waitForText('Essenskorb anfragen');
        $I->click('Essenskorb anfragen');
        $I->waitForText('Anfrage absenden');
        $I->fillField('#contactmessage', 'Hi friend, can I have the basket please?');
        $I->click('Anfrage absenden');
        $I->waitForActiveAPICalls();
        $I->waitForText('Anfrage wurde versendet');
    });

$I->amOnPage($I->foodBasketInfoUrl($id));
$I->waitForActiveAPICalls();
$I->waitForElementNotVisible('#fancybox-loading');
$I->waitForText('Anfragen (1)');
// Open the dropdown menu
$I->see('Essenskörbe', ['css' => '.testing-basket-dropdown']);
$I->click('.testing-basket-dropdown > .nav-link');
$I->see('Essenskorb anlegen', ['css' => '.testing-basket-create']);
// Open chat
$I->click('.testing-basket-requests');
// TODO: https://gitlab.com/foodsharing-dev/foodsharing/-/issues/1466 $I->see('Hi friend, can I have', ['css' => 'vue-advanced-chat']);
// Reject request
$I->wait(2);
$I->click('.testing-basket-dropdown > .nav-link');
$I->click('.testing-basket-requests-close');
$I->waitForText('Essenskorbanfrage von ' . $picker['name'] . ' abschließen');
$I->see('Hat alles gut geklappt?');
$I->seeOptionIsSelected('#fetchstate-wrapper input[name=fetchstate]', '2');
$I->click('Weiter');
$I->waitForText('Danke');
