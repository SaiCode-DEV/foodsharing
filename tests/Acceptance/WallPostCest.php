<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Codeception\Example;
use Tests\Support\AcceptanceTester;

class WallPostCest
{
    private $regionMember;
    private $unconnectedFoodsaver;
    private $testGroup;

    public function _before(AcceptanceTester $I): void
    {
        $this->testGroup = $I->createWorkingGroup('a top group');
        $this->regionMember = $I->createFoodsaver();
        $I->addRegionMember($this->testGroup['id'], $this->regionMember['id']);
        $this->unconnectedFoodsaver = $I->createFoodsaver();
    }

    // tests

    /**
     * @example["regionMember", true]
     * @example["unconnectedFoodsaver", false]
     */
    public function canAddSeeWallPosts(AcceptanceTester $I, Example $example): void
    {
        $I->login($this->{$example[0]}['email']);
        $I->amOnPage($I->regionWallUrl($this->testGroup['id']));
        if ($example[1]) {
            $I->see('Pinnwand', '.list-group-header > h5');
            $wallPostText = 'Hey there, this is my new wallpost!';
            $I->fillField('.md-text-area', $wallPostText);
            $I->click('Senden');
            $I->waitForElement('.wallpost');
            $I->see($wallPostText);
            $I->seeInDatabase('fs_wallpost', ['body' => $wallPostText, 'foodsaver_id' => $this->{$example[0]}['id']]);
        } else {
            $I->dontSee('Pinnwand', '.list-group-header > h5');
        }
    }

    public function cannotAddEmptyWallPost(AcceptanceTester $I): void
    {
        $I->login($this->regionMember['email']);
        $I->amOnPage($I->regionWallUrl($this->testGroup['id']));
        $I->waitForElement('.md-text-area');
        $I->fillField('.md-text-area', ' ');
        $I->dontSee('Senden');
    }
}
