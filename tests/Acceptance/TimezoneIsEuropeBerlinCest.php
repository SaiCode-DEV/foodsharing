<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use DateTime;
use DateTimeZone;
use Exception;
use Tests\Support\AcceptanceTester;

class TimezoneIsEuropeBerlinCest
{
    /**
     * @throws Exception
     */
    final public function checkTimezonesMatch(AcceptanceTester $I): void
    {
        $I->wantTo('see that timezones match all to Europe/Berlin');

        $foodsaver = $I->createFoodsaver();
        $description = 'test foodbasket with bananas';

        $I->login($foodsaver['email'], 'password');
        $I->amOnPage('/');
        $I->see('Essenskörbe', ['css' => '.testing-basket-dropdown']);
        $I->click('.testing-basket-dropdown > .nav-link');
        $I->waitForText('Essenskorb anlegen');
        $I->click('.testing-basket-create');

        $I->waitForText('Beschreibung, Bild, Übergabeort und Zeitraum sind öffentlich sichtbar.');
        $I->fillField('#basket-description-input', $description);

        $min_time = new DateTime('-1 second', new DateTimeZone('Europe/Berlin')); /* microsends in PHP7.1+ make it fail because of rounding otherwise */

        $I->click('Speichern');
        $I->waitForActiveAPICalls();

        $max_time = new DateTime('+1 second', new DateTimeZone('Europe/Berlin'));

        $id = $I->grabFromDatabase('fs_basket', 'id', ['foodsaver_id' => $foodsaver['id'], 'description' => $description]);
        $time = $I->grabFromDatabase('fs_basket', 'time', ['id' => $id]);

        $I->seeFormattedDateInRange($min_time, $max_time, 'Y-m-d H:i:s', $time);

        $time_hm = substr(explode(' ', (string)$time)[1], 0, 5);

        $I->amOnPage('/essenskoerbe/' . $id);
        $I->see($time_hm . ' Uhr');
    }
}
