import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";
import { Database } from "../helpers/database";

test.describe("Store invitations", () => {
  let region: Awaited<ReturnType<typeof foodsharing.createRegion>>;
  let store: Awaited<ReturnType<typeof foodsharing.createStore>>;

  test.beforeEach(async () => {
    region = await foodsharing.createRegion(
      "Invitation test region",
      {},
      false,
    );
    store = await foodsharing.createStore(region.id);
  });

  test("user not in store region cannot accept invitation", async ({
    page,
    acceptanceHelper,
  }) => {
    const coordinator = await foodsharing.createStoreCoordinator(null, {
      bezirk_id: region.id,
    });

    const invited = await foodsharing.createFoodsaver();

    // Make coordinator a store manager and create invitation in DB
    await foodsharing.addStoreTeam(store.id, coordinator.id, true);
    await Database.addToDatabase("fs_betrieb_team", {
      betrieb_id: store.id,
      foodsaver_id: invited.id,
      active: 3,
      verantwortlich: 0,
    });

    // Login as invited user and try to accept
    await acceptanceHelper.logMeOut();
    await acceptanceHelper.login(invited.email);

    const csrf2 =
      (await page.context().cookies()).find((c) => c.name === "FS_CSRF_TOKEN")
        ?.value ?? "";
    const acceptResp = await page.request.patch(
      `/api/stores/${store.id}/invitations`,
      { headers: { "X-CSRF-Token": csrf2 } },
    );

    // Should be forbidden because user is not member of the store's region
    expect(acceptResp.status()).toBe(403);
  });

  test("user with incomplete profile cannot accept invitation", async ({
    page,
    acceptanceHelper,
  }) => {
    const coordinator = await foodsharing.createStoreCoordinator(null, {
      bezirk_id: region.id,
    });

    const invited = await foodsharing.createFoodsaver(null, {
      bezirk_id: region.id,
    });

    // Make coordinator a store manager and create invitation in DB
    await foodsharing.addStoreTeam(store.id, coordinator.id, true);
    await Database.addToDatabase("fs_betrieb_team", {
      betrieb_id: store.id,
      foodsaver_id: invited.id,
      active: 3,
      verantwortlich: 0,
    });

    // Login as invited user and try to accept
    await acceptanceHelper.logMeOut();
    await acceptanceHelper.login(invited.email);

    const csrf2 =
      (await page.context().cookies()).find((c) => c.name === "FS_CSRF_TOKEN")
        ?.value ?? "";
    const acceptResp = await page.request.patch(
      `/api/stores/${store.id}/invitations`,
      { headers: { "X-CSRF-Token": csrf2 } },
    );

    // Should be forbidden because user has incomplete profile (even when in
    // store region)
    expect(acceptResp.status()).toBe(403);
  });

  test("user in store's region can accept invitation", async ({
    page,
    acceptanceHelper,
  }) => {
    const coordinator = await foodsharing.createStoreCoordinator(null, {
      bezirk_id: region.id,
    });

    // Create invited user in the store's region with complete profile
    const invited = await foodsharing.createFoodsaver(null, {
      name: 'User',
      nachname: 'Test',
      geb_datum: '2000-01-01',
      anschrift: 'Musterstrasse 1',
      stadt: 'Musterstadt',
      plz: '12345',
      lat: '48.123456',
      lon: '11.123456',
      photo: 'somephoto.jpg',
      verified: 1,
      bezirk_id: region.id,
    });

    // Make coordinator a store manager and create invitation in DB
    await foodsharing.addStoreTeam(store.id, coordinator.id, true);
    await Database.addToDatabase("fs_betrieb_team", {
      betrieb_id: store.id,
      foodsaver_id: invited.id,
      active: 3,
      verantwortlich: 0,
    });

    // Login as invited user and accept
    await acceptanceHelper.logMeOut();
    await acceptanceHelper.login(invited.email);

    const csrf2 =
      (await page.context().cookies()).find((c) => c.name === "FS_CSRF_TOKEN")
        ?.value ?? "";
    const acceptResp = await page.request.patch(
      `/api/stores/${store.id}/invitations`,
      { headers: { "X-CSRF-Token": csrf2 } },
    );

    expect(acceptResp.ok()).toBeTruthy();

    // After acceptance the client redirects to the store page; verify URL
    // by navigating to the store page and confirming it loads
    await page.goto(`/store/${store.id}`);
    await acceptanceHelper.waitForActiveAPICalls();
    await expect(page).toHaveURL(new RegExp(`/store/${store.id}$`));
  });
});
