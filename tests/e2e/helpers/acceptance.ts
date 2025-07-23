import { Page, expect, test as base } from '@playwright/test';

export class AcceptanceHelper {
  constructor(private page: Page) {}

  async waitForPageBody() {
    return this.page.waitForSelector('body');
  }

  async login(email: string, password: string = 'password') {
    await this.page.goto('/');
    await this.page.evaluate('window.localStorage.clear();');
    const mobileMenuButton = this.page.locator('button.navbar-toggler');
    if (await mobileMenuButton.isVisible()) {
      await mobileMenuButton.click();
      // Wait for mobile menu animation
      await this.page.waitForTimeout(300);
    }
    await this.page.waitForSelector('.testing-login-dropdown');
    await this.page.click('.testing-login-dropdown');
    await this.page.fill('.testing-login-input-email', email);
    await this.page.fill('#testing-login-input-password > input', password);
    await this.page.click('.testing-login-click-submit');
    await this.waitForActiveAPICalls();
    await this.page.waitForSelector('#pulse-success', { state: 'hidden' });
    await this.waitForPageBody();
    await this.page.waitForSelector('.testing-intro-field');
    await expect(this.page.locator('.testing-intro-field')).toContainText('Hallo');
  }

  async logMeOut() {
    await this.page.goto('/logout');
    await this.waitForPageBody();
  }

  async seeMatches(regexp: string | RegExp, selector: string = 'html') {
    const text = await this.page.locator(selector).textContent();
    expect(text).toMatch(regexp);
  }

  async waitForActiveAPICalls(timeout: number = 60000) {
    await this.page.waitForFunction(
      () => !(window as any).hasActiveRequests(),
      { timeout }
    );
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
