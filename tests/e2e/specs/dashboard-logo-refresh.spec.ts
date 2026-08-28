import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";

// #2844: the router drops a navigation to the page that is already open, so
// clicking the logo while on the dashboard did nothing at all.
test("refreshes the dashboard when the logo is clicked there", async ({
  page,
  acceptanceHelper,
}) => {
  const region = await foodsharing.createRegion();
  const user = await foodsharing.createFoodsaver(null, {
    bezirk_id: region.id,
  });
  await foodsharing.addRegionMember(region.id, user.id);
  await acceptanceHelper.login(user.email);

  await page.goto("/dashboard");
  await acceptanceHelper.waitForPageBody();

  // marker on the window object: it only survives as long as the document does
  await page.evaluate(() => {
    (window as any).__beforeRefresh = true;
  });

  await page.locator(".foodsharing a").first().click();

  // the click reloads the page, which is done once the marker is gone
  await page.waitForFunction(
    () => (window as any).__beforeRefresh === undefined,
    null,
    { timeout: 15000 },
  );
  await expect(page).toHaveURL(/\/dashboard/);
  await expect(page.locator(".testing-intro-field")).toContainText("Hallo");
});
