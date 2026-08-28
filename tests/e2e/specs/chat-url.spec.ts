import { test, expect } from "../helpers/acceptance";
import { ChatHelper } from "../helpers/chat";
import { foodsharing } from "../helpers/foodsharing";

// #2847: opening a chat from the list never put it in the address, so every
// reload fell back to the conversation list.
test.describe("Message page address", () => {
  let chatHelper: ChatHelper;
  let user: Awaited<ReturnType<typeof foodsharing.createFoodsaver>>;
  let other: Awaited<ReturnType<typeof foodsharing.createFoodsaver>>;
  let conversation: Awaited<ReturnType<typeof foodsharing.createConversation>>;

  const message = "the message that has to survive a reload";

  test.beforeEach(async ({ page, acceptanceHelper }) => {
    const region = await foodsharing.createRegion();
    user = await foodsharing.createFoodsaver(null, { bezirk_id: region.id });
    other = await foodsharing.createFoodsaver(null, { bezirk_id: region.id });
    conversation = await foodsharing.createConversation([user.id, other.id]);
    await foodsharing.addConversationMessage(other.id, conversation.id, {
      body: message,
    });

    chatHelper = new ChatHelper(page);
    await acceptanceHelper.login(user.email);
    await page.evaluate(() =>
      localStorage.setItem("askForPushNotifications", "false"),
    );
  });

  test("keeps the open conversation after a reload", async ({ page }) => {
    await chatHelper.messagePage.goto();
    await chatHelper.messagePage.roomListEntries.first().click();
    await expect(chatHelper.messagePage.messages.first()).toContainText(
      message,
    );

    await expect(page).toHaveURL(new RegExp(`cid=${conversation.id}\\b`));

    await page.reload();
    await chatHelper.messagePage.waitUntilLoaded();
    await expect(chatHelper.messagePage.messages.first()).toContainText(
      message,
    );
  });
});
