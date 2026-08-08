import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";

// #2801: a typo in the menu computation left the region submenu empty, so clicking
// a region in the navbar looked like nothing happened.

test.describe("Region navigation", () => {
  test("the region submenu lists its entries", async ({
    page,
    acceptanceHelper,
  }) => {
    const homeRegion = await foodsharing.createRegion("HomeRegionTest");
    const otherRegion = await foodsharing.createRegion("OtherRegionTest");
    const user = await foodsharing.createFoodsaver(null, {
      bezirk_id: homeRegion.id,
    });
    await foodsharing.addRegionMember(homeRegion.id, user.id);
    await foodsharing.addRegionMember(otherRegion.id, user.id);

    await acceptanceHelper.login(user.email);
    await page.goto("/dashboard");
    // pulse notifications overlay the navbar and would swallow the clicks
    await page.evaluate(() => {
      document
        .querySelectorAll(".vue-notification-group, .vue-notification-wrapper")
        .forEach((el) => el.remove());
    });

    // on mobile the same component sits in the side navigation
    await acceptanceHelper.openMobileMenuIfNeeded();

    await page
      .getByRole("button", { name: /Bezirke/ })
      .first()
      .click();

    await page
      .getByRole("menuitem", { name: /OtherRegionTest/ })
      .first()
      .click();

    const submenu = page.locator(`#MenuGroupsEntry_${otherRegion.id}`);
    await expect(submenu).toBeVisible();
    await expect(submenu).toContainText("Forum");
    await expect(submenu).toContainText("Mitglieder");
  });
});
