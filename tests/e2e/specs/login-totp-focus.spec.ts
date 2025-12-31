import { test, expect } from "../helpers/acceptance";

test.describe("Login TOTP focus", () => {
  test("reveals TOTP input and focuses it after 403 login response", async ({
    page,
    acceptanceHelper,
  }) => {
    // Intercept login request and return 403 to simulate TOTP required
    await page.route("**/api/user/login", async (route) => {
      await route.fulfill({
        status: 403,
        contentType: "application/json",
        body: JSON.stringify({ message: "TOTP required" }),
      });
    });

    // Open app and the login dropdown.
    await page.goto("/");
    await acceptanceHelper.openMobileMenuIfNeeded();
    await page.click(".testing-login-dropdown");

    // Fill credentials and submit
    await page.fill(".testing-login-input-email", "foo@example.test");
    await page.fill("#testing-login-input-password > input", "password");
    await page.click(".testing-login-click-submit");

    // Wait until the TOTP component wrapper is visible
    await page.waitForSelector("#testing-login-input-totp", {
      state: "visible",
      timeout: 5000,
    });

    // Wait until its inner input becomes the activeElement (focused)
    await page.waitForFunction(
      () => {
        const totpInput = document.querySelector(
          "#testing-login-input-totp input",
        ) as HTMLElement | null;
        return !!totpInput && document.activeElement === totpInput;
      },
      null,
      { timeout: 3000 },
    );

    const focused = await page.evaluate(() => {
      const totpInput = document.querySelector(
        "#testing-login-input-totp input",
      ) as HTMLElement | null;
      return !!totpInput && document.activeElement === totpInput;
    });

    expect(focused).toBe(true);
  });
});
