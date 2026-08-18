import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";

// The router fetches each page with X-Content-Only and injects the response instead of
// loading a document. If that fetch fails it has to fall back to a full page load,
// otherwise the user is left looking at the previous page with a new URL. Links that
// were migrated to <router-link>/<FsLink>/:to go through the router, everything still
// using a plain href (external targets, mailto:, tel:) keeps loading a document.
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

  test("opens a forum thread without reloading the document", async ({
    page,
    acceptanceHelper,
  }) => {
    const region = await foodsharing.createRegion();
    const user = await foodsharing.createFoodsaver(null, {
      bezirk_id: region.id,
    });
    await foodsharing.addRegionMember(region.id, user.id);
    const thread = await foodsharing.seedForumThread(region.id, user.id);
    await acceptanceHelper.login(user.email);
    await page.goto(`/region?bid=${region.id}&sub=forum`);
    await acceptanceHelper.waitForPageBody();
    await markPage(page);

    await page.locator(".forum_threads a").first().click();

    await expect(page).toHaveURL(
      new RegExp(`/region\\?bid=${region.id}&sub=forum&tid=${thread.id}`),
    );
    expect(await stillSameDocument(page)).toBe(true);
  });

  // Module scripts run once per session, because Catchall caches them in
  // window.__loadedScripts. A script that only registers the components of the path
  // that was open when it first loaded leaves the next page empty.
  test("keeps rendering donation pages when navigating between them", async ({
    page,
    acceptanceHelper,
  }) => {
    const orga = await foodsharing.createOrga();
    await acceptanceHelper.login(orga.email);

    await page.goto("/donation");
    await expect(page.locator("#app-content")).toContainText("Deine Spende");
    await markPage(page);

    // the entry in the admin menu goes through the router
    await acceptanceHelper.openMobileMenuIfNeeded();
    await page.getByText("Systemadministration").first().click();
    await page.getByText("Spendenseite bearbeiten").first().click();

    await expect(page).toHaveURL(/\/donation\/admin/);
    expect(await stillSameDocument(page)).toBe(true);
    // Donation.js registers DonationPage only, so the admin wrapper would stay empty
    await expect(page.locator("#app-content")).toContainText(
      "Spendenverwaltung",
    );
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
