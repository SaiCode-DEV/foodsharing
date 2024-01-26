<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Foodsharing\Utility\IdentificationHelper;
use Tests\Support\AcceptanceTester;

function convertId($text)
{
    $text = strtolower((string)$text);
    str_replace(
        ['ä', 'ö', 'ü', 'ß', ' '],
        ['ae', 'oe', 'ue', 'ss', '_'],
        $text
    );

    $idHelper = new IdentificationHelper();
    $I = new AcceptanceTester($scenario);
    $I->wantTo('create a business card as a foodsaver');

    $region = $I->createRegion();

    $foodsaver = $I->createFoodsaver(
        null,
        [
            'name' => 'fs1',
            'nachname' => 'saver1',
            'photo' => 'does-not-exist.jpg',
            'handy' => '+4966669999',
            'bezirk_id' => $region['id']
        ]
    );

    $I->login($foodsaver['email']);

    $I->amOnPage('/?page=bcard');
    $sanitizedId = $idHelper->makeId('businesscard-options');
    $I->selectOption($sanitizedId, 'Foodsaver*in für ' . $region['name']);
}

/* ToDo: Not supported in new CI run style */
//$I->waitForFileExists('/downloads/bcard-fs.pdf');
