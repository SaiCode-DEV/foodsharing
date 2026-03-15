import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";
import { Database } from "../helpers/database";
import WorkgroupFunctions from "../helpers/constants/Region/WorkgroupFunctions";

test("Foodsaver can join another region", async ({
  page,
  acceptanceHelper,
}) => {
  const foodSaver = await foodsharing.createFoodsaver(null, { verified: 0 });
  const region = await foodsharing.createRegion(null, { parent_id: 0 });
  const ambassador = await foodsharing.createAmbassador(null, {
    bezirk_id: region.id,
  });
  await foodsharing.addRegionAdmin(region.id, ambassador.id);
  const welcomeAdmin = await foodsharing.createFoodsaver(null, { verified: 0 });
  const welcomeGroup = await foodsharing.createWorkingGroup("Begrüßung", {
    parent_id: region.id,
  });

  await Database.addToDatabase("fs_region_function", {
    region_id: welcomeGroup.id,
    function_id: WorkgroupFunctions.WELCOME,
    target_id: region.id,
  });
  await foodsharing.addRegionAdmin(welcomeGroup.id, welcomeAdmin.id);

  await acceptanceHelper.login(foodSaver.email);

  await page.goto("/dashboard");
  await acceptanceHelper.waitForActiveAPICalls();
  await page.waitForSelector(".testing-region-join");
  await expect(page.locator(".testing-region-join-select")).toContainText(
    "Bitte auswählen",
  );
  await page
    .locator(".testing-region-join-select")
    .selectOption({ label: region.name });
  await page.click(".testing-region-join .btn.btn-primary");
  await expect(page.locator(".regionTopClass")).toBeVisible();
  await acceptanceHelper.waitForActiveAPICalls();

  expect(
    await Database.seeInDatabase("fs_foodsaver_has_bezirk", {
      foodsaver_id: foodSaver.id,
      bezirk_id: region.id,
    }),
  ).toBe(true);
  expect(
    await Database.seeInDatabase("fs_foodsaver_has_bell", {
      foodsaver_id: welcomeAdmin.id,
    }),
  ).toBe(true);
  expect(
    await Database.seeInDatabase("fs_foodsaver_has_bell", {
      foodsaver_id: ambassador.id,
    }),
  ).toBe(false);
});
