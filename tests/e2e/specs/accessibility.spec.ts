import AxeBuilder from "@axe-core/playwright";
import { expect, test } from "@playwright/test";

test.describe("Accessibility check", () => {
  test.describe.configure({ retries: 0 });

  test.fixme("index", async ({ page }) => {
    await page.goto("/");

    const accessibilityScanResults = await new AxeBuilder({ page })
      .exclude(".foodsharing.part") // Ignore Logo Color Contrast
      .exclude(".navigation.navbar") // Navbar Tint
      .analyze();

    expect(accessibilityScanResults.violations).toEqual([]);
  });
});
