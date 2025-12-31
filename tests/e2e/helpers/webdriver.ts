import { Page } from "@playwright/test";
import { DateTime } from "luxon";

export class WebDriver {
  private page: Page;

  constructor(page: Page) {
    this.page = page;
  }

  /**
   * Checks if the element is REALLY visible in the current viewport.
   */
  async elementVisibleInViewport(selector: string): Promise<boolean> {
    const visible = await this.page.evaluate((sel) => {
      const elem = document.querySelector(sel);
      if (!elem) return false;

      const box = elem.getBoundingClientRect();
      const cx = box.left + box.width / 2;
      const cy = box.top + box.height / 2;

      return document.elementFromPoint(cx, cy) === elem;
    }, selector);

    return visible;
  }

  async seeFormattedDateInRange(
    min: Date,
    max: Date,
    format: string,
    actual: string,
  ): Promise<void> {
    const date = DateTime.fromFormat(actual, format, { zone: "Europe/Berlin" });
    const dateTime = date.toJSDate();
    expect(dateTime).toBeGreaterThanOrEqual(min);
    expect(dateTime).toBeLessThanOrEqual(max);
  }

  async waitForFileExists(
    filename: string,
    timeout: number = 4,
  ): Promise<void> {
    const fs = require("fs").promises;
    const startTime = Date.now();

    while (Date.now() - startTime < timeout * 1000) {
      try {
        await fs.access(filename);
        return;
      } catch {
        await new Promise((resolve) => setTimeout(resolve, 100));
      }
    }
    throw new Error(`File ${filename} did not exist within ${timeout} seconds`);
  }

  async waitUrlEquals(url: string, timeout: number = 4): Promise<void> {
    await this.page.waitForURL(url, { timeout: timeout * 1000 });
  }

  async unlockAllInputFields(): Promise<void> {
    await this.page.evaluate(() => {
      document
        .querySelectorAll("*[readOnly]")
        .forEach((el: Element) => ((el as HTMLInputElement).readOnly = false));
    });
  }

  async fillFieldJs(selector: string, value: string): Promise<void> {
    await this.page.evaluate(
      ({ sel, val }) => {
        document
          .querySelectorAll(sel)
          .forEach((el: Element) => ((el as HTMLInputElement).value = val));
      },
      { sel: selector, val: value },
    );
  }

  async seeCookieHasSessionExpiry(cookieName: string): Promise<void> {
    const cookie = await this.page
      .context()
      .cookies()
      .then((cookies) => cookies.find((c) => c.name === cookieName));
    expect(cookie?.expires).toBeNull();
  }

  async seeCookieHasNoSessionExpiry(cookieName: string): Promise<void> {
    const cookie = await this.page
      .context()
      .cookies()
      .then((cookies) => cookies.find((c) => c.name === cookieName));
    expect(cookie?.expires).not.toBeNull();
  }
}

export const webdriver = new WebDriver(null as any); // Will be initialized with proper page context
