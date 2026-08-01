import { expect, test } from "../helpers/acceptance";

test.describe("karte", () => {
  test.beforeEach(async ({ page }) => {
    await page.goto("/karte");
  });

  test("is visible", async ({ page }) => {
    await expect(page.getByText("Essenskörbe")).toBeVisible();
    await expect(page.getByText("Fairteiler", { exact: true })).toBeVisible();
    await expect(
      page.getByRole("button", { name: "Ortsgruppen" }),
    ).toBeVisible();
    await expect(page.locator("#map-control")).toBeVisible();
  });
});
