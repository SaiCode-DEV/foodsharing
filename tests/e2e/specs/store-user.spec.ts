import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";
import { faker } from "@faker-js/faker";

function dateForOccupiedSlot(d: Date) {
  const fmt = new Intl.DateTimeFormat("de-DE", {
    day: "numeric",
    month: "long",
  });
  return `${fmt.format(d)}`;
}

test.describe("Store user", () => {
  let region: Awaited<ReturnType<typeof foodsharing.createRegion>>;
  let store: Awaited<ReturnType<typeof foodsharing.createStore>>;

  test.beforeEach(async () => {
    region = await foodsharing.createRegion("A region for store-user tests", {}, false);
    store = await foodsharing.createStore(region.id);
  });

  test("shows slot multi chat when multiple pickers", async ({ page, acceptanceHelper }) => {
    const storeCoordinator = await foodsharing.createStoreCoordinator(null, { bezirk_id: region.id });
    await acceptanceHelper.login(storeCoordinator.email);

    // Set localStorage item to avoid push notification prompt
    await page.evaluate(() =>
      localStorage.setItem("askForPushNotifications", "false"),
    );

    await foodsharing.addStoreTeam(store.id, storeCoordinator.id, true);

    const otherUser = await foodsharing.createFoodsaver();
    await foodsharing.addStoreTeam(store.id, otherUser.id, false);

    // Create a pickup and sign up both users
    const pickup = await foodsharing.addPickup(store.id);
    await foodsharing.addPicker(store.id, storeCoordinator.id, { date: pickup.time });
    await foodsharing.addPicker(store.id, otherUser.id, { date: pickup.time });

    // Load store page
    await page.goto(`/store/${store.id}`);
    await acceptanceHelper.waitForActiveAPICalls();

    // Go to the pickup section and check for the multi chat button
    await page.waitForSelector('[data-test="pickup-options-dropdown"]');
    await page.click('[data-test="pickup-options-dropdown"]');
    await page.waitForSelector('[data-test="slot-multi-chat"]');
    await page.click('[data-test="slot-multi-chat"]');

    // Check that the chat UI appears and contains the other user's name
    if (await acceptanceHelper.isMobile()) {
      await expect(
        page.locator("#header").getByText(otherUser.name),
      ).toBeVisible();
    } else {
      await page.waitForSelector('.chatboxtitle', { timeout: 5000 });
      await expect(page.locator('.chatboxtitle')).toContainText(otherUser.name);
    }
  });

  /*
  * The "TakenSlotDialog" appears upon clicking a slot or user avatar within
  * the slots list. Within this dialog, the store coordinator can confirm or
  * reject the request and view the contact options for the foodsaver, along
  * with other pertinent details. This assists in making an informed decision
  * regarding the confirmation or rejection of a slot.
  */
  test.describe("TakenSlotDialog", () => {

    /**
    * The store coordinator should be able to view the slots occupied
    * by the foodsaver along with the respective status. This information can be
    * leveraged by the coordinator to make informed decisions regarding the
    * approval or rejection of the pickup slots. For instance, this is
    * particularly valuable in cases where a foodsaver has occupied a
    * significant number of slots in a short period of time.
    */
    test("can see user occupied slot details in taken slot dialog", async ({ page, acceptanceHelper }) => {
      const foodsaver = await foodsharing.createFoodsaver(null, { bezirk_id: region.id });
      const coordinator = await foodsharing.createStoreCoordinator(null, { bezirk_id: region.id });

      await foodsharing.addStoreTeam(store.id, coordinator.id, true);
      await foodsharing.addStoreTeam(store.id, foodsaver.id, false);

      // add two pickups for the foodsaver using faker-generated dates
      const slot1DateObj = faker.date.future({ years: 1, refDate: new Date() });
      const slot2DateObj = faker.date.soon({ days: 1, refDate: slot1DateObj });
      await foodsharing.addPickup(store.id, { time: foodsharing.toDateTime(slot1DateObj) });
      await foodsharing.addPickup(store.id, { time: foodsharing.toDateTime(slot2DateObj) });
      await foodsharing.addPicker(store.id, foodsaver.id, { date: foodsharing.toDateTime(slot1DateObj) });
      await foodsharing.addPicker(store.id, foodsaver.id, { date: foodsharing.toDateTime(slot2DateObj) });

      // navigate as coordinator
      await acceptanceHelper.login(coordinator.email);
      await page.goto(`/store/${store.id}`);
      await acceptanceHelper.waitForActiveAPICalls();

      // open taken slot dialog for first slot and toggle details
      await page.locator("xpath=(//*[contains(@role,'taken-slot-dialog-button')])").first().click();
      await page.click("[role~='occupied-slot-details-button']");

      await expect(
        page.locator('.modal-dialog .modal-body', { hasText: dateForOccupiedSlot(slot1DateObj) }).first(),
      ).toBeVisible();
      await expect(
        page.locator('.modal-dialog .modal-body', { hasText: dateForOccupiedSlot(slot2DateObj) }).first(),
      ).toBeVisible();
    });

   /**
   * Among the occupied slots, make sure we only display those taken by the
   * same user who occupies the currently opened slot.
   */
    test("does not show other users occupied slots in details", async ({ page, acceptanceHelper }) => {
      const foodsaver1 = await foodsharing.createFoodsaver(null, { bezirk_id: region.id });
      const foodsaver2 = await foodsharing.createFoodsaver(null, { bezirk_id: region.id });
      const coordinator = await foodsharing.createStoreCoordinator(null, { bezirk_id: region.id });

      await foodsharing.addStoreTeam(store.id, coordinator.id, true);
      await foodsharing.addStoreTeam(store.id, foodsaver1.id, false);
      await foodsharing.addStoreTeam(store.id, foodsaver2.id, false);

      // create three future pickups using faker-generated dates
      const pickup1DateObj = faker.date.future({ years: 1, refDate: new Date() });
      const pickup2DateObj = faker.date.future({ years: 1, refDate: pickup1DateObj });
      const pickup3DateObj = faker.date.future({ years: 1, refDate: pickup2DateObj });
      await foodsharing.addPickup(store.id, { time: foodsharing.toDateTime(pickup1DateObj) });
      await foodsharing.addPickup(store.id, { time: foodsharing.toDateTime(pickup2DateObj) });
      await foodsharing.addPickup(store.id, { time: foodsharing.toDateTime(pickup3DateObj) });

      await foodsharing.addPicker(store.id, foodsaver1.id, { date: foodsharing.toDateTime(pickup1DateObj) });
      await foodsharing.addPicker(store.id, foodsaver1.id, { date: foodsharing.toDateTime(pickup2DateObj) });
      await foodsharing.addPicker(store.id, foodsaver2.id, { date: foodsharing.toDateTime(pickup3DateObj) });

      // navigate as coordinator
      await acceptanceHelper.login(coordinator.email);
      await page.goto(`/store/${store.id}`);
      await acceptanceHelper.waitForActiveAPICalls();

      // open taken slot dialog for third slot (occupied by foodsaver2)
      await page.locator("xpath=(//*[contains(@role,'taken-slot-dialog-button')])").nth(2).click();
      await page.click("[role~='occupied-slot-details-button']");

      await expect(page.locator("[role~='user-occupied-slots-listitem']")).toHaveCount(1);

      await expect(
        page.locator('.modal-dialog .modal-body', { hasText: dateForOccupiedSlot(pickup3DateObj) }).first(),
      ).toBeVisible();
    });
  });
});

test.describe("Store user and chat interactions", () => {
  // shared test data created in beforeEach and reused in tests
  let testData: any;

  test.beforeEach(async () => {
    // create region and team members used by multiple tests
    const region = await foodsharing.createRegion(
      "A region I test with",
      false,
    );
    // create three coordinators and two foodsavers
    const coord1 = await foodsharing.createStoreCoordinator(null, {
      bezirk_id: region.id,
    });
    const coord2 = await foodsharing.createStoreCoordinator(null, {
      bezirk_id: region.id,
    });
    const coord3 = await foodsharing.createStoreCoordinator(null, {
      bezirk_id: region.id,
    });
    const foodsaver1 = await foodsharing.createFoodsaver(null, {
      bezirk_id: region.id,
    });
    const foodsaver2 = await foodsharing.createFoodsaver(null, {
      bezirk_id: region.id,
    });

    // store in a local variable accessible to tests
    testData = { coord1, coord2, coord3, foodsaver1, foodsaver2, region };
  });

  const quantityExamples: Array<[number, string]> = [
    [1, "1-3 kg"],
    [2, "3-5 kg"],
    [3, "5-10 kg"],
    [4, "10-20 kg"],
    [5, "20-30 kg"],
    [6, "30-40 kg"],
    [7, "40-50 kg"],
    [8, "mehr als 50 kg"],
  ];

  for (const [num, label] of quantityExamples) {
    test(`See the fetched quantity: ${label}`, async ({
      page,
      acceptanceHelper,
    }) => {
      await acceptanceHelper.login(testData.coord1.email);

      const store = await foodsharing.createStore(
        testData.region.id,
        null,
        null,
        { abholmenge: num },
      );
      await foodsharing.addStoreTeam(store.id, testData.coord1.id, true);

      await page.goto(`/store/${store.id}`);
      await acceptanceHelper.waitForActiveAPICalls();

      await expect(
        page
          .locator("#inputAverageCollectionQuantity")
          .getByText("Menge pro Person"),
      ).toBeVisible();
      await expect(page.locator("html")).toContainText(label);
    });
  }

  const pressExamples: Array<[number, string]> = [
    [0, "private"],
    [1, "public"],
  ];

  for (const [number, val] of pressExamples) {
    test(`See store mentioning (presse=${val})`, async ({
      page,
      acceptanceHelper,
    }) => {
      await acceptanceHelper.login(testData.coord1.email);

      const store = await foodsharing.createStore(
        testData.region.id,
        null,
        null,
        { presse: number },
      );
      await foodsharing.addStoreTeam(store.id, testData.coord1.id, true);

      await page.goto(`/store/${store.id}`);
      await acceptanceHelper.waitForActiveAPICalls();

      await expect(page.locator("text=Namensnennung")).toBeVisible();
      const text = number === 1 ? "darf öffentlich" : "niemals öffentlich";
      await expect(page.locator("text=" + text)).toBeVisible();
    });
  }

  test("Open managers chat from store page as manager", async ({
    page,
    acceptanceHelper,
  }) => {
    // create store and attach team
    const store = await foodsharing.createStore(testData.region.id);
    await foodsharing.addStoreTeam(store.id, testData.coord1.id, true);
    await foodsharing.addStoreTeam(store.id, testData.coord2.id, true);
    await foodsharing.addStoreTeam(store.id, testData.coord3.id, true);

    await acceptanceHelper.login(testData.coord1.email);
    await page.goto(`/store/${store.id}`);
    await acceptanceHelper.waitForActiveAPICalls();

    // Set localStorage item to avoid push notification prompt
    await page.evaluate(() =>
      localStorage.setItem("askForPushNotifications", "false"),
    );

    await page.click('[data-test="store-chat-managers"]');

    if (await acceptanceHelper.isMobile()) {
      await expect(
        page.locator("#header").getByText(testData.coord2.name),
      ).toBeVisible();
      await expect(
        page.locator("#header").getByText(testData.coord3.name),
      ).toBeVisible();
    } else {
      await page.waitForSelector(".chatboxtitle", {
        state: "visible",
        timeout: 5000,
      });
      await expect(page.locator(".chatboxtitle")).toContainText(
        testData.coord2.name,
      );
      await expect(page.locator(".chatboxtitle")).toContainText(
        testData.coord3.name,
      );
    }
  });

  test("Open managers chat from store page as member", async ({
    page,
    acceptanceHelper,
  }) => {
    const store = await foodsharing.createStore(testData.region.id);
    await foodsharing.addStoreTeam(store.id, testData.coord1.id, true);
    await foodsharing.addStoreTeam(store.id, testData.coord2.id, true);
    await foodsharing.addStoreTeam(store.id, testData.coord3.id, true);
    await foodsharing.addStoreTeam(store.id, testData.foodsaver1.id);
    await acceptanceHelper.login(testData.foodsaver1.email);
    await page.goto(`/store/${store.id}`);
    await acceptanceHelper.waitForActiveAPICalls();

    // Set localStorage item to avoid push notification prompt
    await page.evaluate(() =>
      localStorage.setItem("askForPushNotifications", "false"),
    );

    await page.click('[data-test="store-chat-managers"]');

    if (await acceptanceHelper.isMobile()) {
      await expect(
        page.locator("#header").getByText(testData.coord2.name),
      ).toBeVisible();
      await expect(
        page.locator("#header").getByText(testData.coord3.name),
      ).toBeVisible();
    } else {
      await page.waitForSelector(".chatboxtitle", {
        state: "visible",
        timeout: 5000,
      });
      await expect(page.locator(".chatboxtitle")).toContainText(
        testData.coord1.name,
      );

      await expect(page.locator(".chatboxtitle")).toContainText(
        testData.coord2.name,
      );
      await expect(page.locator(".chatboxtitle")).toContainText(
        testData.coord3.name,
      );
    }
  });
});
