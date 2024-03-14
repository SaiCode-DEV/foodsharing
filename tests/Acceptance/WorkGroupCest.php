<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Codeception\Example;
use Codeception\Util\Locator;
use Foodsharing\Modules\Core\DBConstants\Region\ApplyType;
use Tests\Support\AcceptanceTester;

class WorkGroupCest
{
    /* roles that refer to testGroup */
    private $testGroup;

    private $regionMember;
    private $groupAdmin;
    private $unconnectedFoodsaver;

    /* group that can be applied for */
    private $testGroupApply;
    /* admin of testGroupApply */
    private $groupApplyAdmin;

    public function _before(AcceptanceTester $I): void
    {
        /* WorkGroup open to join for everybody */
        $I->createWorkingGroup('0random-placeholder-group');
        $this->testGroup = $I->createWorkingGroup('a group for testing to see groups', ['apply_type' => ApplyType::OPEN]);
        $this->testGroupApply = $I->createWorkingGroup('a group to apply for', ['apply_type' => ApplyType::EVERYBODY]);
        $this->regionMember = $I->createFoodsaver();
        $I->addRegionMember($this->testGroup['id'], $this->regionMember['id']);
        $this->unconnectedFoodsaver = $I->createFoodsaver();
        $this->foodsharer = $I->createFoodsharer();
        $this->groupAdmin = $I->createFoodsaver();
        $I->addRegionMember($this->testGroup['id'], $this->groupAdmin['id']);
        $I->addRegionAdmin($this->testGroup['id'], $this->groupAdmin['id']);
        $this->groupApplyAdmin = $I->createFoodsaver();
        $I->addRegionMember($this->testGroupApply['id'], $this->groupApplyAdmin['id']);
        $I->addRegionAdmin($this->testGroupApply['id'], $this->groupApplyAdmin['id']);
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
            $I->see($this->testGroup['name']);
        } else {
            $I->dontSee($this->testGroup['name']);
        }
    }

    /**
     * It is actually not really defined if foodsharer should be able to participate in groups or not.
     * They don't get the menu item but they can use groups.
     *
     * @example["unconnectedFoodsaver", "testGroup"]
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
     * @example["unconnectedFoodsaver"]
     * @example["regionMember"]
     */
    public function canNotEditWorkGroupAs(AcceptanceTester $I, Example $example): void
    {
        $I->login($this->{$example[0]}['email']);
        $I->amOnPage($I->groupEditUrl($this->testGroup['id']));
        $I->seeInCurrentUrl('dashboard');
        $I->dontSee('Bewerbungen');
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
        $I->selectOption('#input-time', '1-2 Stunden');
        $I->click('Senden');
        $I->waitForText('Erfolgreich abgeschlossen');
        $I->seeInDatabase('fs_foodsaver_has_bezirk', ['foodsaver_id' => $this->regionMember['id'], 'bezirk_id' => $this->testGroupApply['id']]);
        $admin = $I->haveFriend('admin');
        $admin->does(function (AcceptanceTester $I) {
            $I->login($this->groupApplyAdmin['email']);
            $I->amOnPage($I->forumUrl($this->testGroupApply['id']));
            $I->see('Bewerbungen (1)');
            $I->click('Bewerbungen');
            $I->see($this->regionMember['name']);
            $I->click($this->regionMember['name']);
            $I->see('Bewerbung annehmen');
            $I->click('Ja');
        });
        $I->logMeOut();
        $I->login($this->regionMember['email']);
        $I->amOnPage($I->forumUrl($this->testGroupApply['id']));
        $I->see($this->testGroupApply['name']);
        $I->see('Noch keine Themen gepostet');
    }
}
