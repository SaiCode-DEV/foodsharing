<?php

// Acceptance test for buddies modal and badge on profile page

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

class BuddiesModalCest
{
    private $user;
    private $buddy;

    public function _before(AcceptanceTester $I): void
    {
        $this->user = $I->createFoodsharer();
        $this->buddy = $I->createFoodsharer();
    }

    protected function _after()
    {
    }

    public function seeBuddiesModalOnOwnProfile(AcceptanceTester $I)
    {
        // Login as a user
        $I->login($this->user['email']);

        // Become buddies
        $I->amOnPage('/profile/' . $this->buddy['id']);
        // Click the element that triggers the buddy request
        $I->click('[data-testid="buddy-request-' . $this->buddy['id'] . '"]');
        // Confirm sending the buddy request
        $I->click('Ja');
        // Wait for API calls to complete
        $I->waitForActiveAPICalls();
        $I->amOnPage('/profile/' . $this->user['id']);

        // Open buddies modal
        $I->click('#buddies');
        $I->waitForElementVisible('.modal');
        $I->waitForText($this->buddy['name'], 5, '.modal');
    }

    public function buddiesBadgeNotClickableOnOtherProfile(AcceptanceTester $I)
    {
        $I->login($this->user['email']);
        $I->amOnPage('/profile/' . $this->buddy['id']);
        $I->dontSeeElement('a#buddies'); // Not a link
    }
}
