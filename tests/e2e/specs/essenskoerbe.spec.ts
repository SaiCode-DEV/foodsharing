import { expect, test } from "../helpers/acceptance";

test.describe("essenskoerbe", () => {
  test.beforeEach(async ({ page }) => {
    await page.goto("/essenskoerbe");
  });

  test("has map", async ({ page }) => {
    await expect(
      page.getByRole("heading", { name: "Essenskörbe" }),
    ).toBeVisible();
    await expect(page.locator(".vue2leaflet-map")).toBeVisible();
  });
});
