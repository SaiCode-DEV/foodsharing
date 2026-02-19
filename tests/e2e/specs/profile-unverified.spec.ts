import { test } from "../helpers/acceptance";
import { expect } from "@playwright/test";
import { foodsharing } from "../helpers/foodsharing";

test.describe("Profile - unverified warning", () => {
  test("shows icon for unverified users on profile", async ({
    page,
    acceptanceHelper,
  }) => {
    // Create an unverified foodsaver
    const password = "password";
    const user = await foodsharing.createFoodsaver(password, { verified: 0 });

    // Log in
    await acceptanceHelper.login(user.email, null, password);

    // Visit the profile page
    await page.goto(`/user/${user.id}/profile`);

    // Wait for main content to load
    await acceptanceHelper.waitForPageBody();

    // The icon should be present when isVerified is false
    const alert = page.locator("#profile-unverified-alert");
    await expect(alert).toBeVisible();
  });
  test("shows no unverified icon for verified users on profile", async ({
    page,
    acceptanceHelper,
  }) => {
    // Create a verified foodsaver
    const password = "password";
    const user = await foodsharing.createFoodsaver(password, { verified: 1 });

    // Log in
    await acceptanceHelper.login(user.email, null, password);

    // Visit the profile page
    await page.goto(`/user/${user.id}/profile`);

    // Wait for main content to load
    await acceptanceHelper.waitForPageBody();

    // The icon should be hidden when isVerified is true
    const alert = page.locator("#profile-unverified-alert");
    await expect(alert).toBeHidden();
  });
});
