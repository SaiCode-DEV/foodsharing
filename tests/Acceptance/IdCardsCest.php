<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

function convertRegionName($name): array|string|null
{
    $name = strtolower((string)$name);

    $name = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $name);
    $name = preg_replace('/[^a-zA-Z]/', '', $name);

    return $name;
}

class IdCardsCest
{
    public function AmbassadorCanCreateIdCard(AcceptanceTester $I): void
    {
        $region = $I->createRegion();
        $I->createFoodsaver(null, ['name' => 'fs1', 'nachname' => 'saver1', 'photo' => 'does-not-exist.jpg', 'bezirk_id' => $region['id']]);
        $ambassador = $I->createAmbassador(null, ['photo' => 'does-not-exist.jpg', 'bezirk_id' => $region['id']]);
        $I->addRegionAdmin($region['id'], $ambassador['id']);

        $I->login($ambassador['email']);

        $I->amOnPage('/?page=passgen&bid=' . $region['id']);
        $I->waitForText('fs1 saver1');
        $I->click('Alle markieren');
        $I->click('Markierte Ausweise generieren');

        /* ToDo: Not supported in new ci run style */
        //$I->waitForFileExists('/downloads/foodsaver_pass_' . convertRegionName($region['name']) . '.pdf', 10);
    }
}
