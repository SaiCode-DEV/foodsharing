import { test, expect } from "../helpers/acceptance";

test.describe("press page", () => {
  test("is served at /presse", async ({ page }) => {
    await page.goto("/presse");
    await expect(page).toHaveTitle(/Presseinformation/);
  });

  test("redirects the legacy query url to /presse", async ({ page }) => {
    await page.goto("/content?sub=presse");
    await expect(page).toHaveURL(/\/presse$/);
    await expect(page).toHaveTitle(/Presseinformation/);
  });

  test("redirects the legacy path url to /presse", async ({ page }) => {
    await page.goto("/content/presse");
    await expect(page).toHaveURL(/\/presse$/);
    await expect(page).toHaveTitle(/Presseinformation/);
  });
});
