import { test, expect } from "../helpers/acceptance";

test.describe("homepage", () => {
  test.beforeEach(async ({ page }) => {
    await page.goto("/");
  });

  test("has title", async ({ page }) => {
    await expect(page).toHaveTitle(/foodsharing | Rette mit!/);
  });

  test("has imprint link", async ({ page }) => {
    await page.getByLabel("Impressum").click();
    await expect(page).toHaveURL(/.*impressum/);
    await expect(page).toHaveTitle(/foodsharing | Impressum/);
  });
});
