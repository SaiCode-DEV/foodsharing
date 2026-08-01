import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";

// The router fetches each page with X-Content-Only and injects the response instead of
// loading a document. If that fetch fails it has to fall back to a full page load,
// otherwise the user is left looking at the previous page with a new URL. Only the logo
// uses :to so far, so it is the one link that goes through the router.
test.describe("Client side navigation", () => {
  const markPage = (page) =>
    page.evaluate(() => {
      (window as any).__sameDocument = true;
    });
  const stillSameDocument = (page) =>
    page.evaluate(() => (window as any).__sameDocument);

  test("navigates to the dashboard without reloading the document", async ({
    page,
    acceptanceHelper,
  }) => {
    const user = await foodsharing.createFoodsaver();
    await acceptanceHelper.login(user.email);
    await page.goto("/karte");
    await markPage(page);

    await page.locator(".foodsharing a").click();

    await expect(page).toHaveURL(/\/dashboard/);
    expect(await stillSameDocument(page)).toBe(true);
  });

  test("falls back to a full load when the content fetch fails", async ({
    page,
    acceptanceHelper,
  }) => {
    const user = await foodsharing.createFoodsaver();
    await acceptanceHelper.login(user.email);
    await page.goto("/karte");
    await markPage(page);

    // only the content-only fetch fails, the document request behind the fallback
    // has to still go through
    await page.route("**/*", async (route) => {
      if (route.request().headers()["x-content-only"]) {
        await route.fulfill({ status: 500, body: "" });
        return;
      }
      await route.continue();
    });

    await page.locator(".foodsharing a").click();

    await expect(page).toHaveURL(/\/dashboard/);
    // the marker is gone, so the document really was replaced
    expect(await stillSameDocument(page)).toBeUndefined();
    await expect(page.locator("#main")).toBeVisible();

    await page.unrouteAll({ behavior: "ignoreErrors" });
  });
});
