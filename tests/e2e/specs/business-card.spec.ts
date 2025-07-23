import { test } from '../helpers/acceptance';
import { expect } from '@playwright/test';
import { foodsharing } from '../helpers/foodsharing';

test.describe('Business card', () => {

  test.describe.configure({ retries: 3 });

  test("has to be logged in to access the business card", async ({ page }) => {
    await page.goto("/user/current/settings?sub=bcard", { waitUntil: 'commit' });
    await expect(page.getByRole("button", { name: "Einloggen", exact: true })).toBeVisible();
  });

  test('can create business card as foodsaver', async ({ page, acceptanceHelper }) => {
    const password = 'password';
    const user = await foodsharing.createFoodsaver(password, {
      handy: '+4915100000'
    });

    await acceptanceHelper.login(user.email, password);
    await page.goto('/user/current/settings?sub=bcard');

    await expect(page.getByText('Hier einfach generieren, ausdrucken und ausschneiden')).toBeVisible();
  });

  test.fixme('cannot access business card as foodsharer', async ({ page, acceptanceHelper }) => {
    const password = 'password';
    const user = await foodsharing.createFoodsharer(password);

    await acceptanceHelper.login(user.email, password);
    await page.goto('/user/current/settings?sub=bcard');

    await expect(page.getByText('Persönliche Visitenkarte')).toBeHidden();
  });
});
