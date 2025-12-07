import { test } from '../helpers/acceptance';
import { expect } from '@playwright/test';
import { foodsharing } from '../helpers/foodsharing';
import { Database } from '../helpers/database';

test.describe('Delete Account', () => {
  test('can delete my account (being a foodsaver)', async ({ page, acceptanceHelper }) => {
    test.setTimeout(120000); // Set timeout to 120 seconds
    
    const foodsaver = await foodsharing.createFoodsaver();

    await acceptanceHelper.login(foodsaver.email);

    await page.goto('/user/current/settings?sub=deleteaccount');

    await page.click('#delete-account');
    
    // Handle confirmation dialog
    const dialog = page.locator('#confirmation-dialogue');
    await dialog.waitFor({ state: 'visible' });
    
    // Wait for screen fading animation and verify text is visible
    await expect(dialog).toContainText('wirklich', { timeout: 5000 });
    
    // Wait for countdown to complete (button should no longer be disabled)
    const confirmButton = dialog.locator('button.btn-danger');
    await confirmButton.waitFor({ state: 'visible' });

    // Wait until button is clickable (not disabled)
    await expect(async () => {
      const isDisabled = await confirmButton.getAttribute('disabled');
      expect(isDisabled).toBeNull();
    }).toPass({ timeout: 65000 });
    
    await confirmButton.click();
    
    await acceptanceHelper.waitForActiveAPICalls();

    // Verify data in database
    expect(await Database.seeInDatabase('fs_foodsaver', {
      id: foodsaver.id,
      name: null,
      email: null,
      nachname: null,
      deleted_by: foodsaver.id
    })).toBeTruthy();

    expect(await Database.seeInDatabase('fs_foodsaver_archive', {
      id: foodsaver.id,
      name: foodsaver.name,
      email: foodsaver.email,
      nachname: foodsaver.nachname
    })).toBeTruthy();
  });
});
