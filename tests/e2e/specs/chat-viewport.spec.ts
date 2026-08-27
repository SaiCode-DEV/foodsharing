import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";

// #2841: the chat needs `interactive-widget=resizes-content`, otherwise the
// on-screen keyboard pushes its header out of view (#2775). Setting it in the
// document head by route stopped working, and it cannot survive client side
// navigation either.
const viewport = (page) =>
  page.getAttribute('meta[name="viewport"]', "content");

test("sets the chat viewport, also when reached without a reload", async ({
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
  expect(await viewport(page)).not.toContain("interactive-widget");

  // reach the chat without reloading the document
  await acceptanceHelper.openMobileMenuIfNeeded();
  await page
    .getByRole("button", { name: /Nachrichten/ })
    .first()
    .click();
  await page.waitForTimeout(1000);
  await page.locator('.dropdown-menu.show a[href^="/msg"]').first().click();
  await expect(page).toHaveURL(/\/msg/);
  await page.waitForTimeout(1500);

  expect(await viewport(page)).toContain("interactive-widget=resizes-content");

  // and it is gone again after leaving the chat
  await page.goBack();
  await expect(page).toHaveURL(/\/dashboard/);
  await page.waitForTimeout(1500);
  expect(await viewport(page)).not.toContain("interactive-widget");
});
