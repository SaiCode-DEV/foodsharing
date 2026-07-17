import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";

test.describe("ID Cards", () => {
  test("ambassador can create id card", async ({ page, acceptanceHelper }) => {
    const region = await foodsharing.createRegion();
    const foodSaver = await foodsharing.createFoodsaver(null, {
      name: "fs1",
      nachname: "saver1",
      image: true,
      bezirk_id: region.id,
    });
    const ambassador = await foodsharing.createAmbassador(null, {
      photo: "does-not-exist.jpg",
      bezirk_id: region.id,
    });
    await foodsharing.addRegionAdmin(region.id, ambassador.id);

    await acceptanceHelper.login(ambassador.email);

    await page.goto(`/region?bid=${region.id}&sub=members`);
    await page.waitForSelector("text=Foodsaver:innen im Bezirk");

    await page.click("text=Ausweise");
    await page.waitForSelector("#filterMember", {
      state: "visible",
      timeout: 30000,
    });

    await page.fill("#filterMember", foodSaver.name);

    const row = page.locator("tr", { hasText: foodSaver.name });
    await expect(row).toBeVisible();

    const checkbox = row.locator('input[type="checkbox"]');
    await checkbox.dispatchEvent("click");

    const downloadPromise = page.waitForEvent("download");
    await page.click("text=ausführen");
    await downloadPromise;
  });
});
