import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";
import { Database } from "../helpers/database";

test.describe("Region", () => {
  test("can click through vue generated store list pages", async ({
    page,
    acceptanceHelper,
  }) => {
    const region = await foodsharing.createRegion("A region I test with");

    const storeCoordinator = await foodsharing.createStoreCoordinator(null, {
      bezirk_id: region.id,
    });

    // Create 30 stores to ensure pagination
    for (let i = 0; i < 30; i++) {
      await foodsharing.createStore(region.id);
    }

    await acceptanceHelper.login(storeCoordinator.email);
    await page.goto(`/region/${region.id}/stores`);

    // Page 1 active and Page 2 available
    await expect(page.locator(".page-item.active .page-link")).toContainText(
      "1",
    );
    await expect(
      page.locator(".page-item .page-link").filter({ hasText: "2" }),
    ).toBeVisible();

    // Go to page 2
    await page.click('.page-link[aria-posinset="2"]');

    // Page 2 active and Page 1 available
    await expect(
      page.locator(".page-item .page-link").filter({ hasText: "1" }),
    ).toBeVisible();
    await expect(page.locator(".page-item.active .page-link")).toContainText(
      "2",
    );
  });
});

test.describe("Region Wallposts", () => {
  test("region member can see and add wallposts", async ({
    page,
    acceptanceHelper,
  }) => {
    const testGroup = await foodsharing.createWorkingGroup("a top group");
    const regionMember = await foodsharing.createFoodsaver();
    await foodsharing.addRegionMember(testGroup.id, regionMember.id);

    await acceptanceHelper.login(regionMember.email);
    await page.goto(`/region?bid=${testGroup.id}&sub=wall`);
    await acceptanceHelper.waitForActiveAPICalls();

    await expect(page.getByRole("heading", { name: "Pinnwand" })).toBeVisible();

    const wallPostText = "Hey there, this is my new wallpost!";
    await page.fill(".md-text-area", wallPostText);
    await page.getByRole("button", { name: "Senden" }).click();
    await page.waitForSelector(".wallpost");
    await expect(page.locator(".wallpost")).toContainText(wallPostText);

    await expect(
      Database.seeInDatabase("fs_wallpost", {
        body: wallPostText,
        foodsaver_id: regionMember.id,
      }),
    ).resolves.toBeTruthy();
  });

  test("cannot add empty wall post", async ({ page, acceptanceHelper }) => {
    const testGroup = await foodsharing.createWorkingGroup("a top group");
    const regionMember = await foodsharing.createFoodsaver();
    await foodsharing.addRegionMember(testGroup.id, regionMember.id);

    await acceptanceHelper.login(regionMember.email);
    await page.goto(`/region?bid=${testGroup.id}&sub=wall`);
    await page.waitForSelector(".md-text-area");
    await page.fill(".md-text-area", " ");

    await expect(page.getByRole("button", { name: "Senden" })).toHaveCount(0);
  });
});

test("group admin can add user to working group by seeing their ID in tag select", async ({ page, acceptanceHelper }) => {
  const region = await foodsharing.createRegion();
  const group = await foodsharing.createWorkingGroup('a test group', { parent_id: region.id });
  const foodsaver = await foodsharing.createFoodsaver(null, { name: 'WorkingGroupTestUser', nachname: 'lastNameOfThat' });
  await foodsharing.addRegionMember(region.id, foodsaver.id);
  const admin = await foodsharing.createFoodsaver();
  await foodsharing.addRegionMember(group.id, admin.id);
  await foodsharing.addRegionAdmin(group.id, admin.id);
  await foodsharing.addRegionMember(region.id, admin.id);
  await foodsharing.addRegionAdmin(region.id, admin.id);

  await acceptanceHelper.login(admin.email);
  await page.goto(`/region?sub=members&bid=${group.id}`);

  await page.waitForSelector('#new-foodsaver-search');
  await page.fill('#new-foodsaver-search div input', foodsaver.name);
  await page.waitForSelector('.suggestions');
  await page.click(`li[id$="suggestion-${foodsaver.id}"]`);
  await page.click('.fa-user-plus');
  await acceptanceHelper.waitForActiveAPICalls();

  await expect(Database.seeInDatabase('fs_foodsaver_has_bezirk', { bezirk_id: group.id, foodsaver_id: foodsaver.id })).resolves.toBeTruthy();
});
