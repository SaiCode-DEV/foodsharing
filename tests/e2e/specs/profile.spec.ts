import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";

test.describe("Profile", () => {
  test("does not allow giving self banana", async ({ page, acceptanceHelper }) => {
    const foodsaver = await foodsharing.createFoodsaver();
    await acceptanceHelper.login(foodsaver.email);

    await page.goto(`/user/${foodsaver.id}/profile`);
    await expect(page.locator("body")).toContainText(foodsaver.name);

    await page.waitForSelector("#bananas > a > span", { state: "visible", timeout: 4000 });
    await page.click("#bananas > a > span");

    await expect(page.locator("body")).not.toContainText(
      `Schenke ${foodsaver.id} eine Banane`,
    );
  });
});
