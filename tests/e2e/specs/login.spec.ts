import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";

test.describe("Login", () => {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  let foodsharer: any;

  test.beforeEach(async () => {
    foodsharer = await foodsharing.createFoodsharer();
  });

  test("can login", async ({ page, acceptanceHelper }) => {
    // Login via UI
    await page.goto("/");
    await page.evaluate("window.localStorage.clear();");
    await acceptanceHelper.openMobileMenuIfNeeded();
    await page.waitForSelector(".testing-login-dropdown");
    await page.click(".testing-login-dropdown", { position: { x: 10, y: 10 } });
    await page.fill(".testing-login-input-email", foodsharer.email);
    await page.fill("#testing-login-input-password > input", "password");
    await page.getByRole("checkbox", { name: "Dauerhaft eingeloggt bleiben" }).uncheck();
    await page.click(".testing-login-click-submit");
    await acceptanceHelper.waitForActiveAPICalls();

    await page.waitForSelector("#pulse-success", {
      state: "hidden",
      timeout: 10000,
    });
    await acceptanceHelper.waitForPageBody();
    await page.waitForSelector(".testing-intro-field", { timeout: 5000 });
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
    // Login via UI with remember me checked
    await page.goto("/");
    await page.evaluate("window.localStorage.clear();");
    await acceptanceHelper.openMobileMenuIfNeeded();
    await page.waitForSelector(".testing-login-dropdown");
    await page.click(".testing-login-dropdown", { position: { x: 10, y: 10 } });
    await page.fill(".testing-login-input-email", foodsharer.email);
    await page.fill("#testing-login-input-password > input", "password");
    await page.getByRole("checkbox", { name: "Dauerhaft eingeloggt bleiben" }).check();
    await page.waitForTimeout(250);
    await page.click(".testing-login-click-submit");
    await acceptanceHelper.waitForActiveAPICalls();

    await page.waitForSelector("#pulse-success", {
      state: "hidden",
      timeout: 10000,
    });
    await acceptanceHelper.waitForPageBody();
    await page.waitForSelector(".testing-intro-field", { timeout: 5000 });
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
    await page.click(".testing-login-dropdown", { position: { x: 10, y: 10 } });
    await page.fill(".testing-login-input-email", foodsharer.email);
    await expect(page.locator(".testing-login-input-remember")).toBeChecked();
  });
});
