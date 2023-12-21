import AxeBuilder from "@axe-core/playwright";
import { expect, test } from "@playwright/test";

test.describe("homepage", () => {
  test.fixme(
    "should not have any automatically detectable accessibility issues",
    async ({ page }) => {
      await page.goto("/");

      const accessibilityScanResults = await new AxeBuilder({ page }).analyze();

      expect(accessibilityScanResults.violations).toEqual([]);
    },
  );
});
