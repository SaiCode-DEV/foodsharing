import { Page, expect, test as base } from '@playwright/test';
import { authenticator } from 'otplib';

export class AcceptanceHelper {
  constructor(private page: Page) {
    // on Missing translation for console errors, fail the test
    page.on('console', msg => {
      if (msg.type() === 'error' && msg.text().includes('Missing translation for')) {
        throw new Error(msg.text());
      }
    });
  }

  async waitForPageBody() {
    return this.page.waitForSelector('body');
  }

  async isMobile() {
    const mobileMenuButton = this.page.locator('button.navbar-toggler');
    return mobileMenuButton.isVisible();
  }

  async openMobileMenuIfNeeded() {
    const mobileMenuButton = this.page.locator('button.navbar-toggler');
    if (await mobileMenuButton.isVisible()) {
      await mobileMenuButton.click();
      // Wait for mobile menu animation
      await this.page.waitForTimeout(300);
    }
  }

  async login(email: string, rememberMe: boolean = false, password: string = 'password', totpSecretOrCode?: string) {
    await this.page.goto('/');
    await this.page.evaluate('window.localStorage.clear();');
    await this.openMobileMenuIfNeeded();
    await this.page.waitForSelector('.testing-login-dropdown');
    await this.page.click('.testing-login-dropdown');
    await this.page.fill('.testing-login-input-email', email);
    await this.page.fill('#testing-login-input-password > input', password);
    if (rememberMe) {
      await this.page.click('.testing-login-input-remember');
    }
    await this.page.click('.testing-login-click-submit');
    await this.waitForActiveAPICalls();

    if (totpSecretOrCode) {
      // Wait for TOTP input to appear
      await this.page.waitForSelector('#testing-login-input-totp > input', { timeout: 5000 });
      
      // If it looks like a secret (32 chars, base32), generate the code from it
      // Otherwise, use it directly as a code
      const totpCode = totpSecretOrCode.match(/^[A-Z2-7]{32}$/) 
        ? authenticator.generate(totpSecretOrCode)
        : totpSecretOrCode;
      
      await this.page.fill('#testing-login-input-totp > input', totpCode);
      await this.page.click('.testing-login-click-submit');
      await this.waitForActiveAPICalls();
    }

    await this.page.waitForSelector('#pulse-success', { state: 'hidden', timeout: 10000 });
    await this.waitForPageBody();
    await this.page.waitForSelector('.testing-intro-field', { timeout: 5000 });
    await expect(this.page.locator('.testing-intro-field')).toContainText('Hallo');
  }

  /**
   * Logs the user out by navigating to the logout URL.
   */
  async logMeOut() {
    await this.page.goto('/logout');
    await this.waitForPageBody();
  }

  /**
   * Asserts that the given regular expression matches the text content of the specified selector.
   * @param regexp The regular expression to match against.
   * @param selector The CSS selector to get text content from. Defaults to 'html'.
   */
  async seeMatches(regexp: string | RegExp, selector: string = 'html') {
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
      { timeout }
    );
    await this.page.waitForTimeout(1000);
  }

  async clickChatSendButton() {
    const mobileMenuButton = this.page.locator('button.navbar-toggler')
    // eslint-disable-next-line playwright/no-conditional-in-test
    if (await mobileMenuButton.isVisible()) {
      const lastSvgButton = this.page.locator('.vac-svg-button').last();
      await lastSvgButton.click();
    } else {
      await this.page.press('#roomTextarea', 'Enter');
    }
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
  }
});

// Re-export other things if needed
export { expect } from '@playwright/test';
