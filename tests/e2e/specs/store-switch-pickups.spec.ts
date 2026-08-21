import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";

// #2825: the pickup store keeps its slots across a client side navigation, so the next
// store used to show the previous store's pickups until its own request returned.
test("does not show the previous store's pickups while the next one loads", async ({
  page,
  acceptanceHelper,
}) => {
  const region = await foodsharing.createRegion();
  const user = await foodsharing.createStoreCoordinator(null, {
    bezirk_id: region.id,
  });
  const withPickup = await foodsharing.createStore(region.id, null, null, {
    bezirk_id: region.id,
    name: "StoreWithPickup",
    betrieb_status_id: 5,
  });
  const empty = await foodsharing.createStore(region.id, null, null, {
    bezirk_id: region.id,
    name: "StoreWithoutPickup",
    betrieb_status_id: 5,
  });
  await foodsharing.addStoreTeam(withPickup.id, user.id, true);
  await foodsharing.addStoreTeam(empty.id, user.id, true);

  const day = new Date(Date.now() + 2 * 86400000).toLocaleDateString("en-CA", {
    timeZone: "Europe/Berlin",
  });
  await foodsharing.addPickup(withPickup.id, {
    time: `${day} 12:00:00`,
    fetchercount: 2,
  });

  await acceptanceHelper.login(user.email);
  await page.goto(`/store/${withPickup.id}`);
  await expect(page.locator(".pickup-date").first()).toBeVisible();

  // hold the pickup request of the second store so the intermediate state is stable
  let release: () => void = () => {};
  const held = new Promise<void>((resolve) => (release = resolve));
  await page.route(`**/api/stores/${empty.id}/pickups**`, async (route) => {
    await held;
    await route.continue();
  });

  await page
    .getByRole("button", { name: /Betriebe/ })
    .first()
    .click();
  await page
    .getByRole("menuitem", { name: "StoreWithoutPickup" })
    .first()
    .click();
  await expect(page).toHaveURL(new RegExp(`/store/${empty.id}`));

  // the slots of the first store must not be on screen while the request is open
  await expect(page.locator(".pickup-date")).toHaveCount(0);

  release();
  await acceptanceHelper.waitForActiveAPICalls();
  await page.unrouteAll({ behavior: "ignoreErrors" });
});
