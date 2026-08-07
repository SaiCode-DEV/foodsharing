import { test, expect } from "../helpers/acceptance";
import { ChatHelper } from "../helpers/chat";
import { foodsharing } from "../helpers/foodsharing";

// #2786: replying to a long message filled the popup with the unbounded reply
// preview - no scrolling, input field and options unreachable.
test.describe("Chat popup and long quotes", () => {
  let chatHelper: ChatHelper;
  let user: Awaited<ReturnType<typeof foodsharing.createFoodsaver>>;
  let other: Awaited<ReturnType<typeof foodsharing.createFoodsaver>>;
  let conversation: Awaited<ReturnType<typeof foodsharing.createConversation>>;

  const longText = Array.from(
    { length: 40 },
    (_, i) =>
      `Sentence ${i} of a really long message that wraps many times inside the small popup and makes the quoted block very tall.`,
  ).join(" ");

  test.beforeEach(async ({ page, acceptanceHelper, isMobile }) => {
    test.skip(isMobile, "the chat dock popup only exists on desktop");

    const region = await foodsharing.createRegion();
    user = await foodsharing.createFoodsaver(null, { bezirk_id: region.id });
    other = await foodsharing.createFoodsaver(null, { bezirk_id: region.id });
    conversation = await foodsharing.createConversation([user.id, other.id]);

    chatHelper = new ChatHelper(page);
    await acceptanceHelper.login(user.email);
    await page.evaluate(() =>
      localStorage.setItem("askForPushNotifications", "false"),
    );
  });

  async function openPopup(page) {
    await page.goto("/dashboard");
    await chatHelper.navConversations.open();
    await chatHelper.navConversations.openChatFromEntry(0);
    await chatHelper.popupChat.waitUntilLoaded();
  }

  test("replying to a long message keeps the popup usable", async ({
    page,
  }) => {
    await foodsharing.addConversationMessage(other.id, conversation.id, {
      body: longText,
    });
    await openPopup(page);
    const popup = chatHelper.popupChat;

    // open the message actions dropdown and start a reply
    await popup.messages.first().hover();
    await popup.element.locator(".vac-message-options").first().click();
    await popup.element
      .locator(".vac-menu-list .vac-menu-item", { hasText: "Antworten" })
      .click();
    const replyBox = popup.element.locator(".vac-reply-box");
    await replyBox.waitFor({ state: "visible", timeout: 5000 });

    // the preview is capped instead of filling the popup
    expect(await replyBox.evaluate((el) => el.clientHeight)).toBeLessThan(150);

    // input field and message list stay usable
    const card = popup.element.locator(".vac-card-window");
    const cardBox = await card.boundingBox();
    const inputBox = await popup.textbox.boundingBox();
    expect(inputBox.y + inputBox.height).toBeLessThanOrEqual(
      cardBox.y + cardBox.height + 1,
    );
    expect(
      await popup.messageList.evaluate((el) => el.clientHeight),
    ).toBeGreaterThan(50);
  });

  test("a sent long quote is capped and scrolls within the message", async ({
    page,
  }) => {
    const quoted = longText
      .match(/.{1,80}/g)
      .map((line) => "> " + line)
      .join("\n");
    await foodsharing.addConversationMessage(other.id, conversation.id, {
      body: `${quoted}\n\nanswer below the quote`,
    });
    await openPopup(page);

    const blockquote = chatHelper.popupChat.element
      .locator(".vac-message-card blockquote")
      .first();
    await expect(blockquote).toBeVisible();
    expect(
      await blockquote.evaluate((el) => el.scrollHeight > el.clientHeight),
    ).toBe(true);
  });
});
