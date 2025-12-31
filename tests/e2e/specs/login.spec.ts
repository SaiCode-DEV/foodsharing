import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";

test.describe("Login", () => {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  let foodsharer: any;

  test.beforeEach(async () => {
    foodsharer = await foodsharing.createFoodsharer();
  });

  test("can login", async ({ page, acceptanceHelper }) => {
    await acceptanceHelper.login(foodsharer.email);
    await expect(page.locator(".testing-intro-field")).toContainText(
      `Hallo ${foodsharer.name}`,
    );

    // Check that cookie expires in ~24 hours (no "remember me")
    const cookies = await page.context().cookies();
    const sessionCookie = cookies.find((c) => c.name === "FS_SESSID");
    expect(sessionCookie).toBeDefined();
    const now = Date.now() / 1000;
    const oneDayInSeconds = 24 * 60 * 60;
    const toleranceInSeconds = 60 * 60; // 1 hour tolerance
    expect(sessionCookie?.expires).toBeGreaterThan(
      now + oneDayInSeconds - toleranceInSeconds,
    );
    expect(sessionCookie?.expires).toBeLessThan(
      now + oneDayInSeconds + toleranceInSeconds,
    );
  });

  test("can remember login", async ({ page, acceptanceHelper }) => {
    await acceptanceHelper.login(foodsharer.email, true);
    await expect(page.locator(".testing-intro-field")).toContainText(
      `Hallo ${foodsharer.name}`,
    );

    // Check that cookie expires in ~30 days ("remember me" enabled)
    const cookies = await page.context().cookies();
    const sessionCookie = cookies.find((c) => c.name === "FS_SESSID");
    expect(sessionCookie).toBeDefined();
    const now = Date.now() / 1000;
    const thirtyDaysInSeconds = 30 * 24 * 60 * 60;
    const toleranceInSeconds = 60 * 60; // 1 hour tolerance
    expect(sessionCookie?.expires).toBeGreaterThan(
      now + thirtyDaysInSeconds - toleranceInSeconds,
    );
    expect(sessionCookie?.expires).toBeLessThan(
      now + thirtyDaysInSeconds + toleranceInSeconds,
    );

    // Logout
    await page.goto("/logout");

    // Return to login page and verify remember me is still checked
    await page.goto("/");
    await acceptanceHelper.openMobileMenuIfNeeded();
    await page.click(".testing-login-dropdown");
    await page.fill(".testing-login-input-email", foodsharer.email);
    await expect(page.locator(".testing-login-input-remember")).toBeChecked();
  });
});
