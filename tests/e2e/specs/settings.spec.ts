import { test, expect } from '../helpers/acceptance';
import { foodsharing } from '../helpers/foodsharing';
import { Database } from '../helpers/database';

test.describe('Settings', () => {

  test('can edit internal self description', async ({ page, acceptanceHelper }) => {
    const password = 'password';
    const user = await foodsharing.createFoodsaver(password);

    await acceptanceHelper.login(user.email, password);

    const newSelfDesc = 'This is a new self description!';

    await page.goto('/user/current/settings');

    await page.locator('#about_me_intern').fill(newSelfDesc);
    await page.click('text=Speichern');

    await page.waitForSelector('text=Erfolgreich abgeschlossen', { timeout: 5000 });

    // Verify the description is visible on the profile page
    await page.goto(`/user/${user.id}/profile`);
    await expect(page.locator('body')).toContainText(newSelfDesc);
  });

  test('can view another user settings and verify data from DB', async ({ page, acceptanceHelper }) => {
    const password = 'password';
    const region = await foodsharing.createRegion();
    const foodSaver = await foodsharing.createFoodsaver(password, { bezirk_id: region.id });
    const ambassador = await foodsharing.createAmbassador(password, { bezirk_id: region.id });
    await foodsharing.addRegionAdmin(region.id, ambassador.id);

    await acceptanceHelper.login(ambassador.email, password);

    // Visit own settings as ambassador
    await page.goto(`/user/current/settings`);
    await acceptanceHelper.waitForActiveAPICalls();

    // Then visit foodSaver's settings
    await page.goto(`/user/${foodSaver.id}/settings`);
    await acceptanceHelper.waitForActiveAPICalls();

    // Grab expected data from database for foodSaver
    const expectedName = await Database.grabFromDatabase('fs_foodsaver', 'name', { id: foodSaver.id });
    const expectedLastName = await Database.grabFromDatabase('fs_foodsaver', 'nachname', { id: foodSaver.id });

    // Assert that the settings form displays the expected data in specific fields
    await expect(page.locator('#input-firstname')).toHaveValue(expectedName);
    await expect(page.locator('#input-lastname')).toHaveValue(expectedLastName);
  });
});
