<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

/**
 * The "TakenSlotDialog" appears upon clicking a slot or user avatar within
 * the slots list. Within this dialog, the store coordinator can confirm or
 * reject the request and view the contact options for the foodsaver, along
 * with other pertinent details. This assists in making an informed decision
 * regarding the confirmation or rejection of a slot.
 */
class TakenSlotDialogCest
{
    /**
     * The store coordinator should be able to view the slots occupied
     * by the foodsaver along with the respective status. This information can be
     * leveraged by the coordinator to make informed decisions regarding the
     * approval or rejection of the pickup slots. For instance, this is
     * particularly valuable in cases where a foodsaver has occupied a
     * significant number of slots in a short period of time.
     */
    public function canSeeUserOccupiedSlotDetails(AcceptanceTester $I): void
    {
        $region = $I->createRegion();
        $store = $this->setupStore($I, $region);
        $foodsaver = $this->setupFoodSaver($I, $region, $store);
        $coordinator = $this->setupStoreCoordinator($I, $region, $store);

        $I->addPicker($store['id'], $foodsaver['id'], ['date' => '2060-01-01 12:00:00']);
        $I->addPicker($store['id'], $foodsaver['id'], ['date' => '2060-01-02 12:00:00']);

        $this->navigateToStorePage($I, $coordinator, $store);

        $this->openTakenSlotDialog($I, slot: 1);
        $this->toggleOccupiedSlotDetails($I);

        $I->see('1. Januar 2060 – Bestätigt'); // "–" is U+02013 !
        $I->see('2. Januar 2060 – Bestätigt'); // "–" is U+02013 !
    }

    /**
     * Among the occupied slots, make sure we only display those taken by the
     * same user who occupies the currently opened slot.
     */
    public function doesNotShowOtherUsersOccupiedSlotsInDetails(AcceptanceTester $I): void
    {
        $region = $I->createRegion();
        $store = $this->setupStore($I, $region);
        $foodsaver = $this->setupFoodSaver($I, $region, $store);
        $foodsaver2 = $this->setupFoodSaver($I, $region, $store);
        $coordinator = $this->setupStoreCoordinator($I, $region, $store);

        $I->addPicker($store['id'], $foodsaver['id'], ['date' => '2060-01-01 12:00:00']);
        $I->addPicker($store['id'], $foodsaver['id'], ['date' => '2060-01-02 12:00:00']);

        $I->addPicker($store['id'], $foodsaver2['id'], ['date' => '2060-01-03 12:00:00']);

        $this->navigateToStorePage($I, $coordinator, $store);

        // Only 3rd slot is occupied by foodsaver2
        $this->openTakenSlotDialog($I, slot: 3);

        $this->toggleOccupiedSlotDetails($I);

        // Should see only the one slot occupied by the foodsaver2, not the others
        $I->canSeeNumberOfElements("[role~='user-occupied-slots-listitem']", 1);
        $I->see('3. Januar 2060 – Bestätigt'); // "–" is U+02013 !
    }

    /**
     * Clicks one of the slots in the store's slot list to open the dialog.
     */
    private function openTakenSlotDialog(AcceptanceTester $I, int $slot): void
    {
        $I->click("(//*[contains(@role,'taken-slot-dialog-button')])[$slot]");
    }

    /**
     * Clicks the toogle in the dialog which will show all the other slots
     * the user has occupied (including date and status).
     */
    private function toggleOccupiedSlotDetails(AcceptanceTester $I): void
    {
        $I->click("[role~='occupied-slot-details-button']");
    }

    /**
     * @param array{ email: string } $user
     * @param array{ id: string } $store
     */
    private function navigateToStorePage(AcceptanceTester $I, array $user, array $store): void
    {
        $I->login($user['email']);
        $I->amOnPage($I->storeUrl($store['id']));
        $I->waitForActiveAPICalls();
    }

    /**
     * @return array{ id: string }
     */
    private function setupFoodSaver(AcceptanceTester $I, $region, $store): array
    {
        $foodsaver = $I->createFoodsaver(null, [
            'bezirk_id' => $region['id'],
        ]);

        $I->addRegionMember($region['id'], $foodsaver['id']);
        $I->addStoreTeam($store['id'], $foodsaver['id']);

        return $foodsaver;
    }

    /**
     * @return array{ id: string, email: string }
     */
    private function setupStoreCoordinator(AcceptanceTester $I, $region, $store): array
    {
        $coordinator = $I->createStoreCoordinator(null, [
            'bezirk_id' => $region['id']
        ]);
        $I->addRegionMember($region['id'], $coordinator['id']);
        $I->addStoreTeam($store['id'], $coordinator['id'], is_coordinator: true);

        return $coordinator;
    }

    /**
     * @return array{ id: string }
     */
    private function setupStore(AcceptanceTester $I, $region): array
    {
        $store = $I->createStore($region['id']);

        $I->addPickup($store['id'], ['time' => '2060-01-01 12:00:00']);
        $I->addPickup($store['id'], ['time' => '2060-01-02 12:00:00']);
        $I->addPickup($store['id'], ['time' => '2060-01-03 12:00:00']);

        return $store;
    }
}
