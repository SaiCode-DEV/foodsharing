import { Page, expect, test as base } from "@playwright/test";
import { authenticator } from "otplib";

export class AcceptanceHelper {
  constructor(private page: Page) {
    // Fail on console errors
    page.on("console", (msg) => {
      if (msg.type() === "error") {
        const text = msg.text();

        // Missing translations
        if (text.includes("Missing translation for")) {
          throw new Error(text);
        }

        // Network errors shown to the user (these are real problems)
        // These errors come from base.js showNetworkError() function
        if (text.includes("net_errors.") || text.includes("network_errors.")) {
          throw new Error(`User-facing error detected: ${text}`);
        }
      }
    });
  }

  async waitForPageBody() {
    return this.page.waitForSelector("body");
  }

  async isMobile() {
    const mobileMenuButton = this.page.locator("button.navbar-toggler");
    return mobileMenuButton.isVisible();
  }

  async openMobileMenuIfNeeded() {
    const mobileMenuButton = this.page.locator("button.navbar-toggler");
    if (await mobileMenuButton.isVisible()) {
      await mobileMenuButton.click();
      // Wait for mobile menu animation
      await this.page.waitForTimeout(300);
    }
  }

  async login(
    email: string,
    rememberMe: boolean = true,
    password: string = "password",
    totpSecretOrCode?: string,
  ) {
    await this.page.goto("/");
    await this.page.evaluate("window.localStorage.clear();");
    await this.page.evaluate("sessionStorage.clear();");

    // Generate TOTP code if needed
    let totpCode: string | undefined;
    if (totpSecretOrCode) {
      totpCode = totpSecretOrCode.match(/^[A-Z2-7]{32}$/)
        ? authenticator.generate(totpSecretOrCode)
        : totpSecretOrCode;
    }

    // Call login API directly
    const response = await this.page.request.post("/api/login", {
      data: {
        email,
        password,
        code: totpCode,
        remember_me: rememberMe,
      },
    });

    if (!response.ok()) {
      throw new Error(
        `Login failed: ${response.status()} ${await response.text()}`,
      );
    }

    // Wait a moment to ensure session is written
    await this.page.waitForTimeout(250);

    // Navigate to dashboard
    await this.page.goto("/dashboard");
    await this.waitForPageBody();
    await this.page.waitForSelector(".testing-intro-field", { timeout: 5000 });
    await expect(this.page.locator(".testing-intro-field")).toContainText(
      "Hallo",
    );
  }

  /**
   * Logs the user out by navigating to the logout URL.
   */
  async logMeOut() {
    await this.page.goto("/logout");
    await this.waitForPageBody();
  }

  /**
   * Asserts that the given regular expression matches the text content of the specified selector.
   * @param regexp The regular expression to match against.
   * @param selector The CSS selector to get text content from. Defaults to 'html'.
   */
  async seeMatches(regexp: string | RegExp, selector: string = "html") {
    const text = await this.page.locator(selector).textContent();
    expect(text).toMatch(regexp);
  }

  /**
   * Waits until there are no active API calls.
   * @param timeout Maximum time to wait in milliseconds. Default is 60000 ms.
   */
  async waitForActiveAPICalls(timeout: number = 60000) {
    await this.page.waitForFunction(
      () => !(window as any).hasActiveRequests(),
      { timeout },
    );
    await this.page.waitForTimeout(1000);
  }
}

// Define the custom fixtures
type CustomFixtures = {
  acceptanceHelper: AcceptanceHelper;
};

// Extend the base test with our custom fixtures
export const test = base.extend<CustomFixtures>({
  acceptanceHelper: async ({ page }, use) => {
    // Create the acceptance helper instance
    const acceptanceHelper = new AcceptanceHelper(page);

    // Use the fixture in the test
    await use(acceptanceHelper);
  },
});

// Re-export other things if needed
export { expect } from "@playwright/test";
