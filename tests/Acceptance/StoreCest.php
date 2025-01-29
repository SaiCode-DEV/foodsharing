<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Carbon\Carbon;
use Codeception\Example;
use Foodsharing\Modules\Core\DBConstants\Store\CooperationStatus;
use Foodsharing\Modules\Core\DBConstants\StoreTeam\MembershipStatus;
use Tests\Support\AcceptanceTester;

class StoreCest
{
    private array $region;
    private array $store;

    private array $foodsharer;
    private array $foodsaver;
    private array $foodsaverNoRegion;
    private array $foodsaverOnJumperList;
    private array $foodsaverDifferentRegion;
    private array $storeManager;
    private array $foodsaverWithStoreManagerQuiz;

    private array $teamConversation;
    private array $jumperConversation;

    public function _before(AcceptanceTester $I): void
    {
        $this->region = $I->createRegion(fillMailbox: false);
        $regionId = $this->region['id'];
        $extra_params = ['bezirk_id' => $regionId];

        // user creation
        $this->foodsharer = $I->createFoodsharer();
        $this->foodsaver = $I->createFoodsaver(null, $extra_params);
        $this->foodsaverNoRegion = $I->createFoodsaver(null);
        $this->foodsaverOnJumperList = $I->createFoodsaver(null, $extra_params);
        $this->storeManager = $I->createStoreCoordinator(null, $extra_params);
        $this->foodsaverWithStoreManagerQuiz = $I->createStoreCoordinator(null, $extra_params);

        // init store conversations (DIRTY, HOW IT WORKS ...)
        $this->teamConversation = $I->createConversation([$this->storeManager['id'], $this->foodsaver['id']]);
        $this->jumperConversation = $I->createConversation([$this->storeManager['id'], $this->foodsaverOnJumperList['id']]);

        // init store
        $this->store = $I->createStore(
            $regionId, $this->teamConversation['id'], $this->jumperConversation['id'], ['betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value]
        );

        // add user to region (DIRTY, HOW IT WORKS ...)
        $I->addRegionMember($regionId, $this->foodsaver['id']);
        $I->addRegionMember($regionId, $this->foodsaverOnJumperList['id']);
        $I->addRegionMember($regionId, $this->storeManager['id']);
        $I->addRegionMember($regionId, $this->foodsaverWithStoreManagerQuiz['id']);

        // add user to store
        $I->addStoreTeam($this->store['id'], $this->storeManager['id'], true);
        $I->addStoreTeam($this->store['id'], $this->foodsaver['id']);
        $I->addStoreTeam($this->store['id'], $this->foodsaverOnJumperList['id'], false, true, true);

        // add edge case when user from other regions want to join a store, which is not in their region
        $differentRegion = $I->createRegion(fillMailbox: false)['id'];
        $this->foodsaverDifferentRegion = $I->createFoodsaver(null, ['bezirk_id' => $differentRegion]);
        $I->addRegionMember($differentRegion, $this->foodsaverDifferentRegion['id']);
    }

    /**
     * Login handling for the different user types.
     */
    private function loginAs(AcceptanceTester $I, string $user): void
    {
        if ($user === 'Foodsharer') {
            $I->login($this->foodsharer['email']);
        }

        if ($user === 'Foodsaver') {
            $I->login($this->foodsaver['email']);
        }

        if ($user === 'FoodsaverOnJumperList') {
            $I->login($this->foodsaverOnJumperList['email']);
        }

        if ($user === 'FoodsaverNoRegion') {
            $I->login($this->foodsaverNoRegion['email']);
        }

        if ($user === 'foodsaverDifferentRegion') {
            $I->login($this->foodsaverDifferentRegion['email']);
        }

        if ($user === 'StoreManager') {
            $I->login($this->storeManager['email']);
        }

        $I->waitForActiveAPICalls();
    }

    /**
     * @example["StoreManager"]
     * @example["Foodsaver"]
     */
    public function canSeeStoreOnDashboard(AcceptanceTester $I, Example $example): void
    {
        $this->loginAs($I, $example[0]);

        if ($example[0] === 'StoreManager') {
            $I->seeInDatabase('fs_betrieb_team', [
                'betrieb_id' => $this->store['id'],
                'foodsaver_id' => $this->storeManager['id'],
                'active' => MembershipStatus::MEMBER,
                'verantwortlich' => 1,
            ]);
            $I->see('Deine Betriebsverantwortungen', ['css' => '.list-group-header']);
            $I->see($this->store['name'], ['css' => '.field-headline']);
            $I->seeElement('.fas.fa-cog');
        }

        if ($example[0] === 'Foodsaver') {
            $I->seeInDatabase('fs_betrieb_team', [
                'betrieb_id' => $this->store['id'],
                'foodsaver_id' => $this->foodsaver['id'],
                'active' => MembershipStatus::MEMBER,
                'verantwortlich' => 0,
            ]);
            $I->see('Deine Betriebe', ['css' => '.list-group-header']);
            $I->see($this->store['name'], ['css' => '.field-headline']);
        }
    }

    /**
     * @example["Foodsharer"]
     * @example["FoodsaverNoRegion"]
     * @example["foodsaverDifferentRegion"]
     */
    public function canNotAccessStorePage(AcceptanceTester $I, Example $example): void
    {
        $this->loginAs($I, $example[0]);

        $I->amOnPage($I->storeUrl($this->store['id']));
        $I->waitForActiveAPICalls();
        $I->cantSeeInCurrentUrl('fsbetrieb');
    }

    /**
     * @example["StoreManager"]
     */
    public function seePickupHistory(AcceptanceTester $I, Example $example): void
    {
        $this->loginAs($I, $example[0]);

        $I->haveInDatabase('fs_abholer', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->foodsaver['id'],
            'date' => Carbon::now()->subYears(3)->subHours(8),
        ]);

        $I->amOnPage($I->storeUrl($this->store['id']));
        $I->waitForActiveAPICalls();
        $I->waitForText('Abholungshistorie');
        // expand UI (should be collapsed by default)
        $I->click('#expand-Abholungshistorie');
        $I->waitForText('Abholungen anzeigen');
        // select a date ~4 years in the past, to see if the calendar works
        $I->click('.date-picker-from');
        $I->click('button[title="Vorheriges Jahr"]');
        $I->click('button[title="Vorheriges Jahr"]');
        $I->click('button[title="Vorheriges Jahr"]');
        $I->click('button[title="Vorheriger Monat"]');
        $I->click('button[title="Vorheriges Jahr"]');
        $I->click('.b-calendar-grid-body > .row:first-child > .col:last-child');
        // submit search
        $I->click('.pickup-search-button > button');
        $I->waitForActiveAPICalls();
        $I->waitForElement('.pickup-date', 5);
        $I->see($this->foodsaver['name'] . ' ' . $this->foodsaver['nachname']);
    }

    /**
     * @example["StoreManager"]
     * @example["Foodsaver"]
     * @example["FoodsaverOnJumperList"]
     */
    public function canAccessStoreChat(AcceptanceTester $I, Example $example): void
    {
        $this->loginAs($I, $example[0]);

        if ($example[0] === 'StoreManager') {
            $I->wantTo("Can see team and jumper chat as {$example[0]}");

            $I->seeInDatabase('fs_foodsaver_has_conversation', [
                'conversation_id' => $this->store['team_conversation_id'],
                'foodsaver_id' => $this->storeManager['id'],
            ]);

            $I->seeInDatabase('fs_foodsaver_has_conversation', [
                'conversation_id' => $this->store['springer_conversation_id'],
                'foodsaver_id' => $this->storeManager['id'],
            ]);
        }

        if ($example[0] === 'Foodsaver') {
            $I->wantTo("Can see the team, but not jumper chat as {$example[0]}");

            $I->seeInDatabase('fs_foodsaver_has_conversation', [
                'conversation_id' => $this->store['team_conversation_id'],
                'foodsaver_id' => $this->foodsaver['id'],
            ]);

            $I->dontSeeInDatabase('fs_foodsaver_has_conversation', [
                'conversation_id' => $this->store['springer_conversation_id'],
                'foodsaver_id' => $this->foodsaver['id'],
            ]);
        }

        if ($example[0] === 'FoodsaverOnJumperList') {
            $I->wantTo("Can see not the team, but jumper chat as {$example[0]}");

            $I->dontSeeInDatabase('fs_foodsaver_has_conversation', [
                'conversation_id' => $this->store['team_conversation_id'],
                'foodsaver_id' => $this->foodsaverOnJumperList['id'],
            ]);

            $I->seeInDatabase('fs_foodsaver_has_conversation', [
                'conversation_id' => $this->store['springer_conversation_id'],
                'foodsaver_id' => $this->foodsaverOnJumperList['id'],
            ]);
        }
    }

    /**
     * @example["StoreManager"]
     * @example["Foodsaver"]
     */
    public function canRemoveMemberFromStore(AcceptanceTester $I, Example $example): void
    {
        $this->loginAs($I, $example[0]);
        $I->amOnPage($I->storeUrl($this->store['id']));
        $I->waitForActiveAPICalls();

        $I->click("#user-{$this->foodsaverOnJumperList['id']} .overflow-menu");
        $I->waitForElement('.dropdown-menu.show');

        if ($example[0] === 'StoreManager') {
            // remove a member from the team entirely
            $I->click('Aus dem Team entfernen', '.dropdown-menu.show');
            $I->waitForText('Bist du dir sicher?');
            $I->click('Ja, ich bin mir sicher');
            $I->waitForActiveAPICalls();
            $I->dontSee("{$this->foodsaverOnJumperList['name']} {$this->foodsaverOnJumperList['nachname']}", '.store-team');
            $I->dontSeeInDatabase('fs_betrieb_team', [
                'betrieb_id' => $this->store['id'],
                'foodsaver_id' => $this->foodsaverOnJumperList['id'],
            ]);
        }
        if ($example[0] === 'Foodsaver') {
            $I->cantSee('Aus dem Team entfernen');
        }
    }

    public function canPromoteAndDemoteMemberToStoreManager(AcceptanceTester $I): void
    {
        $this->loginAs($I, 'StoreManager');
        $I->amOnPage($I->storeUrl($this->store['id']));
        $I->waitForActiveAPICalls();

        // add new foodsaver to the team
        $I->fillField('#new-member-search input', $this->foodsaverWithStoreManagerQuiz['name']);
        $I->waitForActiveAPICalls();
        $I->waitForElement('#new-member-search li.suggest-item');
        $I->click('#new-member-search li.suggest-item');
        $I->click('#new-member-search button[type="submit"]');
        $I->waitForActiveAPICalls();
        $I->seeElement("#user-{$this->foodsaverWithStoreManagerQuiz['id']}");

        // promote foodsaver to storemanager
        $I->click("#user-{$this->foodsaverWithStoreManagerQuiz['id']} .overflow-menu");
        $I->waitForElement('.dropdown-menu.show');
        $I->click('Verantwortlich machen', '.dropdown-menu.show');
        $I->waitForActiveAPICalls();
        $I->seeInDatabase('fs_betrieb_team', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->foodsaverWithStoreManagerQuiz['id'],
            'active' => MembershipStatus::MEMBER,
            'verantwortlich' => 1,
        ]);
        $I->seeElement("#user-{$this->foodsaverWithStoreManagerQuiz['id']}.manager");

        // demote newly promoted storemanager to regular team member
        $I->click("#user-{$this->foodsaverWithStoreManagerQuiz['id']} .overflow-menu");
        $I->waitForElement('.dropdown-menu.show');
        $I->click('Verantwortung entziehen', '.dropdown-menu.show');
        $I->waitForText('Bist du dir sicher?');
        $I->click('Ja, ich bin mir sicher');
        $I->waitForActiveAPICalls();
        $I->seeInDatabase('fs_betrieb_team', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->foodsaverWithStoreManagerQuiz['id'],
            'verantwortlich' => 0,
        ]);
        $I->seeElement("#user-{$this->foodsaverWithStoreManagerQuiz['id']}:not(.manager)");
    }

    public function canMoveMemberToJumper(AcceptanceTester $I): void
    {
        $this->loginAs($I, 'StoreManager');
        $I->amOnPage($I->storeUrl($this->store['id']));
        $I->waitForActiveAPICalls();

        $I->click("#user-{$this->foodsaver['id']} .overflow-menu");
        $I->waitForElement('.dropdown-menu.show');
        $I->click('Auf die Springerliste', '.dropdown-menu.show');
        $I->waitForText('Bist du dir sicher?');
        $I->click('Ja, ich bin mir sicher');
        $I->waitForActiveAPICalls();
        $I->seeElement("#user-{$this->foodsaver['id']} .jumper");
    }

    public function canAccessStoreLog(AcceptanceTester $I)
    {
        call_user_func($this->loginAs(...), $I, 'StoreManager');
        $I->amOnPage($I->storeUrl($this->store['id']));
        $I->waitForActiveAPICalls();

        $I->click('#store-log');
        $I->click('.multiselect');
        $I->click('#null-4'); // Foodsaver zum Springer machen
        $I->click('.multiselect .multiselect__select');
        $I->click('#search-store-log');
        $I->waitForActiveAPICalls();
        $I->cantSeeElement('.log-entry-content');

        //Perform store action
        $I->click("#user-{$this->foodsaver['id']} .overflow-menu");
        $I->waitForElement('.dropdown-menu.show');
        $I->click('Auf die Springerliste', '.dropdown-menu.show');
        $I->waitForText('Bist du dir sicher?');
        $I->click('Ja, ich bin mir sicher');
        $I->waitForActiveAPICalls();

        //See storelog now contains something
        $I->click('#search-store-log');
        $I->waitForActiveAPICalls();
        $I->canSee('auf die Springerliste gesetzt');

        //Only find entries if category is matching
        $I->click('.multiselect');
        $I->click('#null-3'); // Enable other category
        $I->click('#null-4'); // Disable "Foodsaver zum Springer machen"
        $I->click('.multiselect .multiselect__select');
        $I->click('#search-store-log');
        $I->waitForActiveAPICalls();
        $I->cantSeeElement('.log-entry-content');
    }
}
