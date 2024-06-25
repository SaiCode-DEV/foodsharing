import { expect, test } from "@playwright/test";

test.describe("karte", () => {
  test.beforeEach(async ({ page }) => {
    await page.goto("/karte");
  });

  test("is visible", async ({ page }) => {
    await expect(page.getByText("Essenskörbe")).toBeVisible();
    await expect(page.getByText("Fairteiler", { exact: true })).toBeVisible();
    await expect(page.getByText("Ortsgruppen")).toBeVisible();
    await expect(page.locator("#map-control")).toBeVisible();
  });
});
