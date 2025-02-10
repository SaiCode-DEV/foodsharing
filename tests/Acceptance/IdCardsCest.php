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
        $foodSaver = $I->createFoodsaver(null, ['name' => 'fs1', 'nachname' => 'saver1', 'photo' => 'does-not-exist.jpg', 'bezirk_id' => $region['id']]);
        $ambassador = $I->createAmbassador(null, ['photo' => 'does-not-exist.jpg', 'bezirk_id' => $region['id']]);
        $I->addRegionAdmin($region['id'], $ambassador['id']);

        $I->login($ambassador['email']);

        $I->amOnPage('/region?bid=' . $region['id'] . '&sub=members');
        $I->waitForText('Foodsaver:innen im Bezirk');
        $I->click('Ausweise');
        $I->waitForElementVisible('#filterMember', 30);
        $I->fillField('#filterMember', $foodSaver['name']);

        $searchContent = $foodSaver['name'];
        $I->see($searchContent);

        $I->executeJS('
            var rows = document.querySelectorAll("tr[role=\'row\']");
            for (var i = 0; i < rows.length; i++) {
                if (rows[i].innerText.includes("' . $searchContent . '")) {
                    rows[i].setAttribute("aria-selected", "true");
                } else {
                    rows[i].setAttribute("aria-selected", "false");
                }
            }
        ');

        $I->click('ausführen');

        // $I->waitForFileExists('/downloads/fs_passports_' . $region['id'] . '_' . convertRegionName($region['name']) . '.pdf', 10);
    }
}
