import { test, expect } from "../helpers/acceptance";
import { ChatHelper } from "../helpers/chat";
import { foodsharing } from "../helpers/foodsharing";
import { Tags } from "../helpers/tags";

// #2868: the full view opened from a chat popup was left half usable. The dock read
// the path once per document, so its popup survived into the message page, and the
// content only layout dropped the `<main>` the page hangs its height on.
// The dock only exists from 900px on, so the whole flow is desktop only.
test.describe(
  "Full view opened from a chat popup",
  { tag: [Tags.DESKTOP_ONLY] },
  () => {
    let chatHelper: ChatHelper;
    let user: Awaited<ReturnType<typeof foodsharing.createFoodsaver>>;
    let other: Awaited<ReturnType<typeof foodsharing.createFoodsaver>>;
    let conversation: Awaited<
      ReturnType<typeof foodsharing.createConversation>
    >;

    const message = "the newest message of this conversation";

    test.beforeEach(async ({ page, acceptanceHelper }) => {
      const region = await foodsharing.createRegion();
      user = await foodsharing.createFoodsaver(null, { bezirk_id: region.id });
      other = await foodsharing.createFoodsaver(null, { bezirk_id: region.id });
      conversation = await foodsharing.createConversation([user.id, other.id]);
      // more than the first page of messages, so the list has to scroll
      for (let i = 0; i < 30; i++) {
        await foodsharing.addConversationMessage(other.id, conversation.id);
      }
      // the newest one, so it is part of the first page the full view asks for
      await foodsharing.addConversationMessage(other.id, conversation.id, {
        body: message,
        time: foodsharing.toDateTime(new Date()),
      });

      chatHelper = new ChatHelper(page);
      await acceptanceHelper.login(user.email);
      await page.evaluate(() =>
        localStorage.setItem("askForPushNotifications", "false"),
      );
      await page.goto("/dashboard");
      await chatHelper.openChatFromNavConversations();
      await chatHelper.popupChat.waitUntilLoaded();
    });

    async function openFullView() {
      await chatHelper.popupChat.openMenu();
      await chatHelper.popupChat.menuItems
        .filter({ hasText: "Vollansicht" })
        .click();
      await chatHelper.messagePage.waitUntilLoaded();
      await chatHelper.waitForAllChatsToInitialize();
      await expect(chatHelper.messagePage.messageList).toContainText(message);
    }

    test("closes the popup it was opened from", async ({ page }) => {
      await openFullView();

      await expect(page).toHaveURL(new RegExp(`cid=${conversation.id}\\b`));
      await expect(page.locator(".chat-dock .chat-dock__box")).toHaveCount(0);
    });

    test("keeps the conversation inside the window", async ({ page }) => {
      await openFullView();

      const chat = await chatHelper.messagePage.element.boundingBox();
      const viewport = page.viewportSize();
      expect(chat.y + chat.height).toBeLessThanOrEqual(viewport.height);

      // the older messages are only reachable if the list scrolls itself
      const list = chatHelper.messagePage.messageList;
      expect(
        await list.evaluate((el) => el.scrollHeight - el.clientHeight),
      ).toBeGreaterThan(10);
    });

    test("brings the popup back when the full view is left again", async ({
      page,
    }) => {
      await openFullView();
      await expect(page.locator(".chat-dock .chat-dock__box")).toHaveCount(0);

      await page.locator(".foodsharing a").click();
      await expect(page).toHaveURL(/\/dashboard/);

      await expect(page.locator(".chat-dock .chat-dock__box")).toHaveCount(1);
    });
  },
);
