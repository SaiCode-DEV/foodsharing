import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";
import { Database } from "../helpers/database";

// #2755/#2742: colliding/empty tab titles (fr has two empty ones today) used to
// blank the whole tabbed page; content must resolve by position, not title.
test.describe("Tabbed page with colliding tab titles", () => {
  test("renders the French settings page and keeps the empty-titled tab reachable", async ({
    page,
    acceptanceHelper,
  }) => {
    const user = await foodsharing.createFoodsaver();
    await acceptanceHelper.login(user.email);
    // set the locale only after login - the login helper asserts the German greeting
    await Database.addToDatabase("fs_foodsaver_has_options", {
      foodsaver_id: user.id,
      option_type: 1, // UserOptionType::LOCALE
      option_value: "fr",
    });
    await page.goto("/user/current/settings");

    const tabs = page.locator(".list-group-item");
    await expect(tabs.first()).toBeVisible();
    expect(await tabs.count()).toBeGreaterThan(5);

    // first tab = profile, whose fr title is empty - must stay reachable
    await tabs.first().click();
    await expect(page.locator("#about_me_intern")).toBeVisible();
  });
});
