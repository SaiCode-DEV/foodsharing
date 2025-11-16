import { test } from '../helpers/acceptance';
import { expect } from '@playwright/test';
import { foodsharing } from '../helpers/foodsharing';

test.describe('Foodsaver can edit profile', () => {

  test('can edit profile fields as foodsaver', async ({ page, acceptanceHelper }) => {
    const password = 'password';
    const user = await foodsharing.createFoodsaver(password);

    await acceptanceHelper.login(user.email, password);

    await page.goto('/user/current/settings');

    const mobilenumber = '+49 151 8417482';
    const phonenumber = '+49 4687 0307670';
    const aboutMeIntern = 'Ich mag foodsharing.';

    // vue-tel-input normalizes phone numbers:
    // - mobile numbers: spaces removed
    const mobilenumberNormalized = '+49 1518417482';

    await page.locator('#mobile').clear();
    await page.locator('#mobile').fill(mobilenumber);
    await page.locator('#phone').clear();
    await page.locator('#phone').fill(phonenumber);
    await page.locator('#about_me_intern').fill(aboutMeIntern);

    await page.click('text=Speichern');

    await page.waitForSelector('text=Erfolgreich abgeschlossen', { timeout: 5000 });

    // Assert fields contain the new values (normalized format without spaces)
    await expect(page.locator('#phone')).toHaveValue(phonenumber);
    await expect(page.locator('#mobile')).toHaveValue(mobilenumberNormalized);
    await expect(page.locator('#about_me_intern')).toHaveValue(aboutMeIntern);
  });

});