<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Carbon\Carbon;
use Codeception\Example;
use Facebook\WebDriver\WebDriverKeys;
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

    private array $teamConversation;
    private array $jumperConversation;

    public function _before(AcceptanceTester $I): void
    {
        $this->region = $I->createRegion();
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
        $differentRegion = $I->createRegion()['id'];
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
    public function canAddStore(AcceptanceTester $I, Example $example): void
    {
        $this->loginAs($I, $example[0]);

        $I->amOnPage('/?page=betrieb&a=new');

        if ($example[0] === 'StoreManager') {
            $I->wantTo("Can add store as {$example[0]}");

            $I->unlockAllInputFields();
            $I->fillField('first_post', 'Testeintrag');
            $I->fillField('name', 'Testbetrieb');
            $I->fillField('#addresspicker', 'Test Teststraße 1 37073 Teststadt Deutschland');
            $I->waitForElementVisible('#addresspicker_listbox');

            $I->pressKey('#addresspicker', WebDriverKeys::ARROW_DOWN);
            $I->pressKey('#addresspicker', WebDriverKeys::RETURN_KEY);
            $I->wait(1);

            $I->fillField('public_info', 'Testeintrag im Feld öffentliche Information');
            $I->click('Senden');
            $I->waitForPageBody();

            $I->canSee('Kooperationsbetrieb wurde eingetragen', ['css' => '#pulse-success p']);
            $I->canSeeInDatabase('fs_betrieb', [
                'name' => 'Testbetrieb',
                'str' => 'Teststraße 1',
                'plz' => '37073',
                'stadt' => 'Teststadt',
                'public_info' => 'Testeintrag im Feld öffentliche Information',
            ]);
        }

        if ($example[0] === 'Foodsaver') {
            $I->wantTo("Can't add store as {$example[0]}");
            $I->dontSee('Neuen Betrieb eintragen');
            $I->cantSee('first_post');
            $I->seeCurrentUrlEquals('/?page=settings&sub=up_bip');
        }
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
     * @example["Foodsaver"]
     */
    public function canAccessStoreEditPage(AcceptanceTester $I, Example $example): void
    {
        $this->loginAs($I, $example[0]);

        $I->amOnPage($I->storeEditUrl($this->store['id']));
        if ($example[0] === 'StoreManager') {
            $I->see('Bezirk ändern');
            $I->see('Ansprechpersonen im Betrieb');
        } else {
            $I->dontSee('Bezirk ändern');
            $I->dontSee('Ansprechpersonen im Betrieb');
        }
    }

    /**
     * @example["StoreManager"]
     */
    public function willKeepApproxPickupTime(AcceptanceTester $I, Example $example): void
    {
        $this->loginAs($I, $example[0]);

        // Check original value
        $I->amOnPage('/?page=betrieb&a=edit&id=' . $this->store['id']);
        $I->see('Keine Angabe', '#public_time option[selected]');

        // Change option and save the page
        $I->selectOption('public_time', 'morgens');
        $I->click('Senden');

        // Check the page again to make sure our option was saved
        $I->amOnPage('/?page=betrieb&a=edit&id=' . $this->store['id']);
        $I->see('morgens', '#public_time option[selected]');
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

        if ($example[0] === 'StoreManager') {
            $I->wantTo("Can remove a member as {$example[0]}");
            $I->click('Ansicht für Betriebsverantwortliche aktivieren');
            // remove a member from the team entirely
            $I->click("{$this->foodsaverOnJumperList['name']} {$this->foodsaverOnJumperList['nachname']}", '.store-team');
            $I->click('Aus dem Team entfernen', '.member-actions');
            $I->waitForElement('#deleteModal');
            $I->see('Möchtest du wirklich ' . $this->foodsaverOnJumperList['name'] . ' ' . $this->foodsaverOnJumperList['nachname'] . ' aus diesem Betriebs-Team entfernen?', '#deleteModal');
            $I->click('Ja, ich bin mir sicher', '#deleteModal');
            $I->waitForActiveAPICalls();
            $I->dontSee("{$this->foodsaverOnJumperList['name']} {$this->foodsaverOnJumperList['nachname']}", '.store-team');
            $I->dontSeeInDatabase('fs_betrieb_team', [
                'betrieb_id' => $this->store['id'],
                'foodsaver_id' => $this->foodsaverOnJumperList['id'],
            ]);
        }

        if ($example[0] === 'Foodsaver') {
            $I->wantTo("Can't remove a member as {$example[0]}");
            $I->cantSee('Ansicht für Betriebsverantwortliche aktivieren');
            $I->canSee("{$this->foodsaverOnJumperList['name']} {$this->foodsaverOnJumperList['nachname']}", '.store-team');
        }
    }

    /**
     * @example["StoreManager"]
     */
    public function canPromoteAndDemoteMemberToStoreManager(AcceptanceTester $I, Example $example): void
    {
        $this->loginAs($I, $example[0]);
        $I->amOnPage($I->storeUrl($this->store['id']));
        $I->waitForActiveAPICalls();

        if ($example[0] === 'StoreManager') {
            $I->click('Ansicht für Betriebsverantwortliche aktivieren');
            // add new foodsaver to the team
            $I->fillField('#new-foodsaver-search input', $this->foodsaverWithStoreManagerQuiz['name']);
            $I->waitForActiveAPICalls();
            $I->waitForElement('#new-foodsaver-search li.suggest-item');
            $I->click('#new-foodsaver-search li.suggest-item');
            $I->click('#new-foodsaver-search button[type="submit"]');
            $I->waitForActiveAPICalls();

            // promote foodsaver to storemanager
            $I->click("{$this->foodsaverWithStoreManagerQuiz['name']} {$this->foodsaverWithStoreManagerQuiz['nachname']}", '.store-team');
            $I->click('Verantwortlich machen', '.member-actions');
            $I->waitForActiveAPICalls();
            $I->seeInDatabase('fs_betrieb_team', [
                'betrieb_id' => $this->store['id'],
                'foodsaver_id' => $this->foodsaverWithStoreManagerQuiz['id'],
                'active' => MembershipStatus::MEMBER,
                'verantwortlich' => 1,
            ]);
            $I->waitForElement('.store-team tr.table-warning[data-pk="' . $this->storeManager['id'] . '"]', 5);
            $I->waitForElement('.store-team tr.table-warning[data-pk="' . $this->foodsaverWithStoreManagerQuiz['id'] . '"]', 5);

            // demote newly promoted storemanager to regular team member
            $I->click("{$this->foodsaverWithStoreManagerQuiz['name']} {$this->foodsaverWithStoreManagerQuiz['nachname']}", '.store-team');
            $I->click('Als Betriebsverantwortliche:n entfernen', '.member-actions');
            $I->seeInPopup('die Verantwortung für diesen Betrieb entziehen?');
            $I->cancelPopup();
            $I->seeInDatabase('fs_betrieb_team', [
                'betrieb_id' => $this->store['id'],
                'foodsaver_id' => $this->foodsaverWithStoreManagerQuiz['id'],
                'verantwortlich' => 1,
            ]);
            $I->waitForElement('.store-team tr.table-warning[data-pk="' . $this->foodsaverWithStoreManagerQuiz['id'] . '"]', 2);
            $I->click('Als Betriebsverantwortliche:n entfernen', '.member-actions');
            $I->seeInPopup('die Verantwortung für diesen Betrieb entziehen?');
            $I->acceptPopup();
            $I->waitForActiveAPICalls();
            $I->seeInDatabase('fs_betrieb_team', [
                'betrieb_id' => $this->store['id'],
                'foodsaver_id' => $this->foodsaverWithStoreManagerQuiz['id'],
                'verantwortlich' => 0,
            ]);

            // Check if the demoted storemanager is shown as a regular team member
            $I->waitForElement('.store-team tr[data-pk="' . $this->foodsaverWithStoreManagerQuiz['id'] . '"]:not(.table-warning)', 5);
        }
    }

    /**
     * @example["StoreManager"]
     */
    public function canMoveMemberToJumper(AcceptanceTester $I, Example $example): void
    {
        $this->loginAs($I, $example[0]);
        $I->amOnPage($I->storeUrl($this->store['id']));
        $I->waitForActiveAPICalls();

        if ($example[0] === 'StoreManager') {
            $I->click('Ansicht für Betriebsverantwortliche aktivieren');
            // move a member to jumper (standby list)
            $I->click("{$this->foodsaver['name']} {$this->foodsaver['nachname']}", '.store-team');
            $I->click('Auf die Springerliste', '.member-actions');
            $I->waitForActiveAPICalls();
            // check that the jumper is still displayed as team member (but with muted colors)
            $I->see("{$this->foodsaver['name']} {$this->foodsaver['nachname']}", '.store-team');
            $I->waitForElement('.store-team #member-' . $this->foodsaver['id'] . '.member-info.jumper', 5);
        }
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
        $I->click("{$this->foodsaver['name']} {$this->foodsaver['nachname']}", '.store-team');
        $I->click('Auf die Springerliste', '.member-actions');
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
