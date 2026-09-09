import { test, expect } from "../helpers/acceptance";
import type { Page } from "@playwright/test";

test.describe("server data loading", () => {
  const failServerDataRequest = async (page: Page) => {
    await page.route("**/api/server/data", async (route) => {
      await route.fulfill({
        status: 503,
        contentType: "application/json",
        body: "{}",
      });
    });
  };

  test("boots with fallback data when the initial request fails", async ({
    page,
  }) => {
    await failServerDataRequest(page);

    await page.goto("/");

    await expect(page.locator("#app-content")).toBeVisible();
    await expect(page.locator("#fs-initial-loader")).not.toBeAttached();
  });

  test("uses cached server data before a failed request", async ({ page }) => {
    await page.addInitScript(() => {
      localStorage.setItem("serverData", JSON.stringify({ locale: "en" }));
    });
    await failServerDataRequest(page);

    await page.goto("/");

    await expect(page.locator("body")).toContainText("Log in");
  });

  test("does not restore private server data from the cache", async ({
    page,
  }) => {
    await page.addInitScript(() => {
      localStorage.setItem(
        "serverData",
        JSON.stringify({
          user: { id: 1, firstname: "Previous user", may: true },
          permissions: { mayAdministrateUsers: true },
          locations: [{ lat: 52.5, lon: 13.4 }],
          groups: [{ id: 1, name: "Private group" }],
          regions: [{ id: 2, name: "Private region" }],
        }),
      );
    });
    await failServerDataRequest(page);

    await page.goto("/");

    await expect(page.locator("#app-content")).toBeVisible();
    await expect
      .poll(() =>
        page.evaluate(() => JSON.parse(localStorage.getItem("serverData")!)),
      )
      .toEqual({ locale: "de" });
  });
});
