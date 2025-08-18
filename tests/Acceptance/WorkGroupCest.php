<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Codeception\Example;
use Codeception\Util\Locator;
use Foodsharing\Modules\Core\DBConstants\Region\ApplyType;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Tests\Support\AcceptanceTester;

class WorkGroupCest
{
    /* roles that refer to testGroup */
    private array $parentRegion;
    private array $testGroup;
    private array $globalTestGroup;

    private $regionMember;
    private $groupAdmin;
    private $unconnectedFoodsaver;

    /* group that can be applied for */
    private $testGroupApply;
    /* admin of testGroupApply */
    private $groupApplyAdmin;
    private array $foodsharer;
    private array $userOrga;

    public function _before(AcceptanceTester $I): void
    {
        /* WorkGroup open to join for everybody */
        $I->createWorkingGroup('0random-placeholder-group', fillMailbox: false);
        $this->globalTestGroup = $I->createWorkingGroup('a group in the global groups region', ['apply_type' => ApplyType::OPEN, 'parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS], fillMailbox: false);
        $this->parentRegion = $I->createRegion(fillMailbox: false);
        $this->testGroup = $I->createWorkingGroup('a group for testing to see groups', ['apply_type' => ApplyType::OPEN, 'parent_id' => $this->parentRegion['id']], fillMailbox: false);
        $this->testGroupApply = $I->createWorkingGroup('a group to apply for', ['apply_type' => ApplyType::EVERYBODY], fillMailbox: false);
        $this->regionMember = $I->createFoodsaver();
        $I->addRegionMember(RegionIDs::GLOBAL_WORKING_GROUPS, $this->regionMember['id']);
        $I->addRegionMember($this->testGroup['id'], $this->regionMember['id']);
        $this->unconnectedFoodsaver = $I->createFoodsaver();
        $this->foodsharer = $I->createFoodsharer();
        $this->groupAdmin = $I->createFoodsaver();
        $I->addRegionMember($this->testGroup['id'], $this->groupAdmin['id']);
        $I->addRegionAdmin($this->testGroup['id'], $this->groupAdmin['id']);
        $this->groupApplyAdmin = $I->createFoodsaver();
        $I->addRegionMember(RegionIDs::GLOBAL_WORKING_GROUPS, $this->groupApplyAdmin['id']);
        $I->addRegionMember($this->testGroupApply['id'], $this->groupApplyAdmin['id']);
        $I->addRegionAdmin($this->testGroupApply['id'], $this->groupApplyAdmin['id']);
        $this->userOrga = $I->createOrga();
    }

    public function _after(AcceptanceTester $I): void
    {
    }

    // tests

    /**
     * It is actually not really defined if foodsharer should be able to participate in groups or not.
     * They don't get the menu item but they can use groups.
     *
     * @example["regionMember", true]
     * @example["unconnectedFoodsaver", true]
     * @example["foodsharer", true]
     */
    public function canSeeGlobalGroups(AcceptanceTester $I, Example $example): void
    {
        $I->login($this->{$example[0]}['email']);
        $I->amOnPage($I->groupListUrl());
        if ($example[1]) {
            $I->see($this->globalTestGroup['name']);
        } else {
            $I->dontSee($this->globalTestGroup['name']);
        }
    }

    /**
     * It is actually not really defined if foodsharer should be able to participate in groups or not.
     * They don't get the menu item but they can use groups.
     *
     * @example["unconnectedFoodsaver", "globalTestGroup"]
     */
    public function canJoinGlobalGroup(AcceptanceTester $I, Example $example): void
    {
        $group = $this->{$example[1]};
        $I->login($this->{$example[0]}['email']);
        $I->amOnPage($I->groupListUrl());
        $I->clickWithLeftButton(Locator::contains('.list-group', $group['name']));
        $I->click('Dieser Arbeitsgruppe beitreten');
        $I->waitForText('Pinnwand');
        $I->amOnPage($I->forumUrl($group['id']));
        $I->see($group['name']);
        $I->see('Noch keine Themen gepostet');
    }

    /**
     * Users who are not members of the group should be redirected to the parent region's public page.
     *
     * @example["unconnectedFoodsaver"]
     */
    public function canNotAccessWorkGroupAs(AcceptanceTester $I, Example $example): void
    {
        $I->login($this->{$example[0]}['email']);
        $I->amOnPage($I->groupEditUrl($this->testGroup['id']));
        $I->seeInCurrentUrl($I->regionPublicPageUrl($this->parentRegion['id'], $this->testGroup['id']));
        $I->dontSee('Bewerbungen');
    }

    /**
     * @example["regionMember"]
     */
    public function canNotEditWorkGroupAs(AcceptanceTester $I, Example $example): void
    {
        $I->login($this->{$example[0]}['email']);
        $I->amOnPage($I->groupEditUrl($this->testGroup['id']));
        $I->seeInCurrentUrl('dashboard');
        $I->dontSee('Bewerbungen');
    }

    /**
     * @example["groupAdmin"]
     * @example["userOrga"]
     */
    public function canEditWorkGroupAs(AcceptanceTester $I, Example $example): void
    {
        $I->login($this->{$example[0]}['email']);
        $I->amOnPage($I->groupEditUrl($this->testGroup['id']));
        $I->see($this->testGroup['name'] . ' bearbeiten');
    }

    public function canApplyForWorkGroup(AcceptanceTester $I): void
    {
        $I->login($this->regionMember['email']);
        $I->amOnPage($I->groupListUrl());
        $I->clickWithLeftButton(Locator::contains('.list-group', $this->testGroupApply['name']));
        $I->waitForText('Arbeitsgruppe bewerben');
        $I->click('Für diese Arbeitsgruppe bewerben');
        $I->waitForElement('#input-motivation');
        $I->fillField('#input-motivation', 'My Motivation');
        $I->fillField('#input-ability', 'My Skillz');
        $I->fillField('#input-experience', 'My Experience');
        $I->selectOption('#input-time', '1–2 Stunden');
        $I->click('Senden');
        $I->waitForText('Erfolgreich abgeschlossen');
        $I->seeInDatabase('fs_foodsaver_has_bezirk', ['foodsaver_id' => $this->regionMember['id'], 'bezirk_id' => $this->testGroupApply['id']]);
        $admin = $I->haveFriend('admin');
        $admin->does(function (AcceptanceTester $I) {
            $I->login($this->groupApplyAdmin['email']);
            $I->amOnPage($I->forumUrl($this->testGroupApply['id']));
            $I->waitForText('Bewerbungen (1)');
            $I->click('Bewerbungen');
            $I->waitForText($this->regionMember['name']);
            $I->click($this->regionMember['name']);
            $I->waitForText('Bewerbung annehmen');
            $I->click('Ja');
        });
        $I->logMeOut();
        $I->login($this->regionMember['email']);
        $I->amOnPage($I->forumUrl($this->testGroupApply['id']));
        $I->waitForText($this->testGroupApply['name']);
        $I->waitForText('Noch keine Themen gepostet');
    }
}
