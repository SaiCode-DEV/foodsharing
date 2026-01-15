import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";

test.describe("Store user", () => {
  test("shows slot multi chat when multiple pickers", async ({ page, acceptanceHelper }) => {
    const region = await foodsharing.createRegion("A region for multi chat test", {}, false);
    const storeCoordinator = await foodsharing.createStoreCoordinator(null, { bezirk_id: region.id });
    await acceptanceHelper.login(storeCoordinator.email);

    // Set localStorage item to avoid push notification prompt
    await page.evaluate(() =>
      localStorage.setItem("askForPushNotifications", "false"),
    );

    const store = await foodsharing.createStore(region.id);
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
          .getByText("Abholmenge pro Person"),
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
