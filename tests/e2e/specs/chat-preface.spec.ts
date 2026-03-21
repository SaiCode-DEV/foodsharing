import { test, expect } from "../helpers/acceptance";
import { ChatHelper } from "../helpers/chat";
import { foodsharing } from "../helpers/foodsharing";

// Tests that clicking the "Nachricht an Betriebsverantwortliche" and the
// pickup "Slot-Chat starten" both show the expected preface in the reply box.

test.describe("Chat preface flows", () => {
  let chatHelper: ChatHelper;
  let region: Awaited<ReturnType<typeof foodsharing.createRegion>>;
  let store: Awaited<ReturnType<typeof foodsharing.createStore>>;
  let coordinator: Awaited<
    ReturnType<typeof foodsharing.createStoreCoordinator>
  >;
  let member: Awaited<ReturnType<typeof foodsharing.createFoodsaver>>;

  test.beforeEach(async ({ page, acceptanceHelper }) => {
    chatHelper = new ChatHelper(page);
    region = await foodsharing.createRegion();
    store = await foodsharing.createStore(region.id);

    coordinator = await foodsharing.createStoreCoordinator(null, {
      bezirk_id: region.id,
    });
    member = await foodsharing.createFoodsaver(null, { bezirk_id: region.id });

    // Add both to store team: coordinator is responsible
    await foodsharing.addStoreTeam(store.id, coordinator.id, true);
    await foodsharing.addStoreTeam(store.id, member.id, false);

    // Login as the regular team member
    await acceptanceHelper.login(member.email);
    await page.evaluate(() =>
      localStorage.setItem("askForPushNotifications", "false"),
    );
  });

  test.afterEach(async ({ acceptanceHelper }) => {
    await acceptanceHelper.logMeOut();
  });

  test("manager button opens chat with manager-preface containing store name", async ({
    page,
  }) => {
    await page.goto(`/store/${store.id}`);
    await page.waitForLoadState("networkidle");

    // Click the manager chat button
    await page.click('[data-test="store-chat-managers"]');

    // Wait for chat UI to initialize and check the reply box preface
    await chatHelper.waitForAllChatsToInitialize();
    const replyContent = page.locator(".vac-reply-content").first();
    await replyContent.waitFor({ state: "visible", timeout: 10000 });
    expect(await replyContent.textContent()).toContain(store.name);
  });

  test("slot chat starts with pickup preface containing store name when users signed up", async ({
    page,
    acceptanceHelper,
  }) => {
    // Create a pickup and sign up both users
    const pickup = await foodsharing.addPickup(store.id);
    await foodsharing.addPicker(store.id, coordinator.id, {
      date: pickup.time,
    });
    await foodsharing.addPicker(store.id, member.id, { date: pickup.time });

    // Reload as member and open the store page
    await page.goto(`/store/${store.id}`);
    await acceptanceHelper.waitForActiveAPICalls();

    // Open pickup options and click the slot multi-chat
    await page.waitForSelector('[data-test="pickup-options-dropdown"]');
    await page.click('[data-test="pickup-options-dropdown"]');
    await page.waitForSelector('[data-test="slot-multi-chat"]');
    await page.click('[data-test="slot-multi-chat"]');

    // Wait for chat UI to initialize and check the reply box preface
    await chatHelper.waitForAllChatsToInitialize();
    const replyContent = page.locator(".vac-reply-content").first();
    await replyContent.waitFor({ state: "visible", timeout: 10000 });
    expect(await replyContent.textContent()).toContain(store.name);
  });
});
