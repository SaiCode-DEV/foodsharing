import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";

test.describe("Profile", () => {
  test("does not allow giving self banana", async ({ page, acceptanceHelper }) => {
    const foodsaver = await foodsharing.createFoodsaver();
    await acceptanceHelper.login(foodsaver.email);

    await page.goto(`/user/${foodsaver.id}/profile`);
    await expect(page.locator("body")).toContainText(foodsaver.name);

    await page.waitForSelector("#bananas > a > span", { state: "visible", timeout: 4000 });
    await page.click("#bananas > a > span");

    await expect(page.locator("body")).not.toContainText(
      `Schenke ${foodsaver.id} eine Banane`,
    );
  });

  test("see buddies modal on own profile", async ({ page, acceptanceHelper }) => {
    const user = await foodsharing.createFoodsharer();
    const buddy = await foodsharing.createFoodsharer();

    await acceptanceHelper.login(user.email);

    // Send buddy request to the other user
    await page.goto(`/profile/${buddy.id}`);
    await page.waitForSelector(`[data-testid="buddy-request-${buddy.id}"]`, { state: 'visible', timeout: 4000 });
    await page.click(`[data-testid="buddy-request-${buddy.id}"]`);
    await page.getByRole('button', { name: 'Ja' }).click();
    await acceptanceHelper.waitForActiveAPICalls();

    // Open own profile and show buddies modal
    await page.goto(`/profile/${user.id}`);
    await page.click('#buddies');
    await page.waitForSelector('.modal', { state: 'visible', timeout: 4000 });
    await expect(page.locator('.modal')).toContainText(buddy.name);
  });

  test("buddies badge not clickable on other profile", async ({ page, acceptanceHelper }) => {
    const user = await foodsharing.createFoodsharer();
    const buddy = await foodsharing.createFoodsharer();

    await acceptanceHelper.login(user.email);
    await page.goto(`/profile/${buddy.id}`);

    // Ensure there's no anchor link for the buddies badge on someone else's profile
    await acceptanceHelper.waitForPageBody();
    await expect(page.locator('a#buddies')).toHaveCount(0);
  });
});
