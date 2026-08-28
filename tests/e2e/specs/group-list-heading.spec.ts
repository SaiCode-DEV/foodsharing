import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";

// #2853: the heading of a subgroup list showed only the count, the name of the
// working group was missing.
test("subgroup list names the working group it belongs to", async ({
  page,
  acceptanceHelper,
}) => {
  const region = await foodsharing.createRegion();
  const user = await foodsharing.createFoodsaver(null, {
    bezirk_id: region.id,
  });
  const parent = await foodsharing.createWorkingGroup("Heading group");
  await foodsharing.createWorkingGroup("A subgroup", { parent_id: parent.id });
  await foodsharing.addRegionMember(parent.id, user.id);
  await foodsharing.addRegionAdmin(parent.id, user.id);

  await acceptanceHelper.login(user.email);
  await page.goto(`/groups?p=${parent.id}`);

  const heading = page.locator("h5").first();
  await expect(heading).toContainText("Heading group");
  await expect(heading).toContainText("Untergruppen");
});
