<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Codeception\Example;
use Tests\Support\AcceptanceTester;

class FoodSharePointCest
{
    private $testBezirk;
    private $responsible;
    private $otherBot;
    private $user;
    private $foodSharePoint;

    public function _before(AcceptanceTester $I): void
    {
        $this->testBezirk = $I->createRegion('MyFunnyBezirk');
        $this->user = $I->createFoodsharer(null, ['bezirk_id' => $this->testBezirk['id']]);
        $this->responsible = $I->createAmbassador(null, ['bezirk_id' => $this->testBezirk['id']]);
        $I->addRegionAdmin($this->testBezirk['id'], $this->responsible['id']);
        $this->otherBot = $I->createAmbassador(null, ['bezirk_id' => $this->testBezirk['id']]);
        $I->addRegionAdmin($this->testBezirk['id'], $this->otherBot['id']);
        $this->foodSharePoint = $I->createFoodSharePoint($this->responsible['id'], $this->testBezirk['id']);
    }

    public function canSeeFoodSharePointInList(AcceptanceTester $I): void
    {
        $I->amOnPage($I->foodSharePointRegionListUrl($this->testBezirk['id']));
        $I->waitForText($this->foodSharePoint['name']);
        $I->click($this->foodSharePoint['name']);
        $I->waitForText(explode("\n", (string)$this->foodSharePoint['anschrift'])[0]);
    }

    public function redirectForGetPage(AcceptanceTester $I): void
    {
        $I->amOnPage($I->foodSharePointGetUrlShort($this->foodSharePoint['id']));
        $I->waitForText(explode("\n", (string)$this->foodSharePoint['anschrift'])[0]);
        $I->seeCurrentUrlEquals($I->foodSharePointGetUrl($this->foodSharePoint['id']));
    }

    public function createFoodSharePoint(AcceptanceTester $I): void
    {
        $address = 'Teststraße 1 37073 Teststadt Deutschland';

        $I->login($this->responsible['email']);
        $I->amOnPage($I->foodSharePointRegionListUrl($this->testBezirk['id']));
        $I->waitForText('Fairteiler eintragen', 10);
        $I->click('Fairteiler eintragen');
        $I->waitForText('In welchem Bezirk');
        $I->selectOption('#fsp_bezirk_id', $this->testBezirk['id']);
        $I->fillField('#name', 'The greatest fairsharepoint');
        $I->fillField('#desc', 'Blablabla if you come here be hungry!');

        // Find an address in the search field
        $I->fillField('#searchinput', $address);
        $I->waitForElementVisible('#searchinput_listbox');
        $I->click("//*[@id='searchinput_listbox']//*[contains(text(), 'Teststraße 1')]");

        // Codeception's click function doesn't work with this switch checkbox. We have to click it with javascript.
        $I->executeJs('document.getElementById(\'different_location\').click()');
        $I->fillField('#input-street', 'Kantstrasse 20');
        $I->fillField('#input-postal', '04808');
        $I->fillField('#input-city', 'Wurzen');
        $I->fillFieldJs('#lat', '1.23');
        $I->fillFieldJs('#lon', '2.48');
        $I->click('Speichern');
        $I->waitForText('wurde erfolgreich eingetragen');
        $id = $I->grabFromDatabase('fs_fairteiler', 'id', [
            'name' => 'The greatest fairsharepoint',
            'bezirk_id' => $this->testBezirk['id'],
        ]);
        $I->amOnPage($I->foodSharePointGetUrl($id));
        $I->waitForText('Kantstrasse 20', 10);
    }

    public function editFoodSharePoint(AcceptanceTester $I): void
    {
        $user = $I->createFoodsaver(null, ['bezirk_id' => $this->testBezirk['id']]);
        $I->login($this->responsible['email']);
        $I->amOnPage($I->foodSharePointEditUrl($this->foodSharePoint['id']));
        $I->waitForText('Schreibe hier ein paar grundsätzliche Infos über den Fairteiler');
        $I->waitForText('insbesondere wann er zugänglich/geöffnet ist');
        $I->fillField('#name', 'The BEST fairshare point!');
        $I->addInTagSelect($user['name'], '#fspmanagers');
        $I->click('Speichern');
        $I->waitForText('erfolgreich bearbeitet');
        $I->waitForText($user['name'] . ' ' . $user['nachname']);
    }

    /**
     * @example["user", false]
     * @example["responsible", true]
     * @example["otherBot", true]
     */
    public function mayEditFoodSharePoint(AcceptanceTester $I, Example $example): void
    {
        $user = $this->{$example[0]};
        $I->login($user['email']);
        $I->amOnPage($I->foodSharePointEditUrl($this->foodSharePoint['id']));
        if ($example[1]) {
            $I->waitForText('Schreibe hier ein paar');
        } else {
            /* just see the fairteiler page if not enough permissions to edit */
            $I->waitForText('Beachte, dass deine Beiträge');
        }
    }

    public function mayNotEditFoodSharePointWrongBid(AcceptanceTester $I): void
    {
        $region = $I->createRegion('another funny region');
        $bot = $I->createAmbassador(null, ['bezirk_id' => $region['id']]);
        $I->addRegionAdmin($region['id'], $bot['id']);
        $I->login($bot['email']);
        $I->amOnPage($I->foodSharePointEditUrl($this->foodSharePoint['id']) . '&bid=' . $region['id']);
        /* does not get edit view although region admin of another region (regression) */
        $I->waitForText('Beachte, dass deine Beiträge');
    }
}
