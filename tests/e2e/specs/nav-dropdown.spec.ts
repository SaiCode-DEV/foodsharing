import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";

// #2833: the dropdown entries are router links, so nothing tears the dropdown down
// any more when the page changes underneath it.
test("closes the header dropdown when an entry navigates", async ({
  page,
  acceptanceHelper,
  viewport,
}) => {
  // On a narrow screen the menu is a sidenav with its entries already unfolded,
  // there is no floating dropdown that could stay behind.
  test.skip((viewport?.width ?? 0) < 768, "no dropdown in the mobile layout");

  const region = await foodsharing.createRegion();
  const user = await foodsharing.createFoodsaver(null, {
    bezirk_id: region.id,
  });
  await foodsharing.addRegionMember(region.id, user.id);
  await acceptanceHelper.login(user.email);

  await page.goto("/dashboard");
  await acceptanceHelper.waitForPageBody();

  await page.getByRole("button", { name: /Hallo/ }).first().click();

  const entry = page
    .locator(".dropdown-menu.show a")
    .filter({ hasText: /Profil/ })
    .first();
  await expect(entry).toBeVisible();

  await entry.click();

  await page.waitForURL(/\/profile/, { timeout: 15000 });
  await expect(page.locator(".dropdown-menu.show")).toHaveCount(0);
});
