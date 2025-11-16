import { test } from '../helpers/acceptance';
import { expect } from '@playwright/test';
import { foodsharing } from '../helpers/foodsharing';

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

});
