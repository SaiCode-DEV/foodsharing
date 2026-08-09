import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";

// #2806: the group and region lists load asynchronously, so the select could not
// match the preselected unit and fell back to its first entry.

test.describe("Event form", () => {
  test("preselects the unit it was opened from", async ({
    page,
    acceptanceHelper,
  }) => {
    // the group sorts first, so a fallback to the first entry is visible
    const group = await foodsharing.createWorkingGroup("AAA First Group");
    const region = await foodsharing.createRegion("ZZZ Target Region");
    const user = await foodsharing.createFoodsaver(null, {
      bezirk_id: region.id,
    });
    await foodsharing.addRegionMember(region.id, user.id);
    await foodsharing.addRegionMember(group.id, user.id);

    await acceptanceHelper.login(user.email);
    await page.goto(`/event/add?bid=${region.id}`);
    await acceptanceHelper.waitForActiveAPICalls();

    // the unit select is the one holding the region as an option
    const select = page.locator("select").first();
    await expect(select).toHaveValue(String(region.id));
  });
});
