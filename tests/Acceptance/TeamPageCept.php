<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Tests\Support\AcceptanceTester;

$board_id = RegionIDs::TEAM_BOARD_MEMBER;
$administration_id = RegionIDs::TEAM_ADMINISTRATION_MEMBER;
$alumni_id = RegionIDs::TEAM_ALUMNI_MEMBER;
$I = new AcceptanceTester($scenario);

$boardMember = $I->createFoodsaver();
$I->addRegionMember($board_id, $boardMember['id']);
$administrationMember = $I->createFoodsaver();
$I->addRegionMember($administration_id, $administrationMember['id']);
$alumniMember = $I->createFoodsaver();
$I->addRegionMember($alumni_id, $alumniMember['id']);

$I->wantTo('Check if the team page lists board and active members and alumni members');

$I->amOnPage('/team');
// $I->see('Kontaktanfragen');
$I->see($boardMember['name']);
$I->see($administrationMember['name']);
$I->see($alumniMember['name']);
