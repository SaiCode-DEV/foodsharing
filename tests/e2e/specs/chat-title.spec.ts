import { test, expect } from "../helpers/acceptance";
import { ChatHelper } from "../helpers/chat";
import { foodsharing } from "../helpers/foodsharing";

// Covers the participant count in chat titles (#2756): group chats show how
// many people a message reaches, named functional chats (store team/jumper)
// already at two people, unnamed private 1:1 chats stay undecorated.
test.describe("Chat title participant count", () => {
  let chatHelper: ChatHelper;
  let region: Awaited<ReturnType<typeof foodsharing.createRegion>>;
  let me: Awaited<ReturnType<typeof foodsharing.createFoodsaver>>;
  let others: Awaited<ReturnType<typeof foodsharing.createFoodsaver>>[];

  test.beforeEach(async ({ page, acceptanceHelper }) => {
    chatHelper = new ChatHelper(page);
    region = await foodsharing.createRegion();
    me = await foodsharing.createFoodsaver(null, { bezirk_id: region.id });
    others = [];
    for (let i = 0; i < 3; i++) {
      others.push(
        await foodsharing.createFoodsaver(null, { bezirk_id: region.id }),
      );
    }
    await acceptanceHelper.login(me.email);
    await page.evaluate(() =>
      localStorage.setItem("askForPushNotifications", "false"),
    );
  });

  test.afterEach(async ({ acceptanceHelper }) => {
    await acceptanceHelper.logMeOut();
  });

  test("shows the participant count for a group chat", async ({ page }) => {
    const conversation = await foodsharing.createConversation(
      [me.id, others[0].id, others[1].id, others[2].id],
      { name: "Team XY" },
    );
    await foodsharing.addConversationMessage(others[0].id, conversation.id);
    await chatHelper.messagePage.goto();
    await chatHelper.messagePage.roomListEntries.first().click();
    const count = page.locator("#header .participant-count");
    await expect(count).toBeVisible();
    await expect(count).toContainText("4");
  });

  test("shows no count for a private one-to-one chat", async ({ page }) => {
    const conversation = await foodsharing.createConversation([
      me.id,
      others[0].id,
    ]);
    await foodsharing.addConversationMessage(others[0].id, conversation.id);
    await chatHelper.messagePage.goto();
    await chatHelper.messagePage.roomListEntries.first().click();
    await expect(page.locator("#header")).toBeVisible();
    await expect(page.locator("#header .participant-count")).toHaveCount(0);
  });

  test("shows the count for a named chat already at two people", async ({
    page,
  }) => {
    const conversation = await foodsharing.createConversation(
      [me.id, others[0].id],
      { name: "Team betrieb_Klein" },
    );
    await foodsharing.addConversationMessage(others[0].id, conversation.id);
    await chatHelper.messagePage.goto();
    await chatHelper.messagePage.roomListEntries.first().click();
    const count = page.locator("#header .participant-count");
    await expect(count).toBeVisible();
    await expect(count).toContainText("2");
  });
});
