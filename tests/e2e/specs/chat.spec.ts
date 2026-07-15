import type { BrowserContext, Page } from "@playwright/test";
import { test, expect, AcceptanceHelper } from "../helpers/acceptance";
import { ChatHelper } from "../helpers/chat";
import { foodsharing } from "../helpers/foodsharing";
import { Tags } from "../helpers/tags";

test.describe("Chat", () => {
  let chatHelper: ChatHelper;
  let region: Awaited<ReturnType<typeof foodsharing.createRegion>>;
  let foodsaver1: Awaited<ReturnType<typeof foodsharing.createFoodsaver>>;
  let foodsaver2: Awaited<ReturnType<typeof foodsharing.createFoodsaver>>;

  test.beforeEach(async ({ page, acceptanceHelper }) => {
    chatHelper = new ChatHelper(page);

    region = await foodsharing.createRegion();
    foodsaver1 = await foodsharing.createFoodsaver(null, {
      bezirk_id: region.id,
    });
    foodsaver2 = await foodsharing.createFoodsaver(null, {
      bezirk_id: region.id,
    });

    await acceptanceHelper.login(foodsaver1.email);
    await page.evaluate(() =>
      localStorage.setItem("askForPushNotifications", "false"),
    );
  });

  test.afterEach(async ({ acceptanceHelper }) => {
    await acceptanceHelper.logMeOut();
  });

  test("can send and receive chat messages", async ({
    page,
    acceptanceHelper,
  }) => {
    await chatHelper.openChatFromProfilePage(foodsaver2.id);

    // Write a message to them
    const chatTextForFoodsaver1 = "is anyone there?";
    await chatHelper.fillText(chatTextForFoodsaver1);
    await chatHelper.clickSend();
    await expect(chatHelper.currentMessage).toContainText(
      chatTextForFoodsaver1,
      { timeout: 20000 },
    );
    expect(
      await chatHelper.databaseHasMessage({
        foodsaverId: foodsaver1.id,
        body: chatTextForFoodsaver1,
      }),
    ).toBeTruthy();

    await acceptanceHelper.logMeOut();

    // Login as foodsaver2 in the second context
    await acceptanceHelper.login(foodsaver2.email);
    await page.evaluate(() =>
      localStorage.setItem("askForPushNotifications", "false"),
    );

    // Check they have the notification badge on conversations icon
    await expect(chatHelper.navConversations.unread).toContainText("1", {
      timeout: 10000,
    });

    // Open the conversation menu and open the new conversation
    await chatHelper.navConversations.open();
    await expect(chatHelper.navConversations.getEntryUnread()).toContainText(
      "1",
    );
    await chatHelper.navConversations.getEntry().click();
    await chatHelper.textbox.waitFor({ state: "visible", timeout: 6000 });

    // Check that the chat contains the message from foodsaver1
    await expect(chatHelper.messages.first()).toContainText(
      chatTextForFoodsaver1,
      { timeout: 15000 },
    );

    // Write a nice reply - find the textarea in the chat component
    const chatTextForFoodsaver2 = "yes! I am here!";
    await chatHelper.fillText(chatTextForFoodsaver2);
    await chatHelper.clickSend();

    // Verify the message was added to the chatbox and database
    await expect(chatHelper.currentMessage).toContainText(
      chatTextForFoodsaver2,
      { timeout: 10000 },
    );
    expect(
      await chatHelper.databaseHasMessage({
        foodsaverId: foodsaver2.id,
        body: chatTextForFoodsaver2,
      }),
    ).toBeTruthy();
  });

  test("chat input is focused on non-mobile devices when opening a chat", async ({
    acceptanceHelper,
  }) => {
    await chatHelper.openChatFromProfilePage(foodsaver2.id);
    if (await acceptanceHelper.isMobile()) {
      await expect(chatHelper.textbox).not.toBeFocused();
    } else {
      await expect(chatHelper.textbox).toBeFocused();
    }
  });

  test.describe("with two simultaneous users chatting", () => {
    let context2: Awaited<BrowserContext>;
    let page2: Awaited<Page>;
    let acceptanceHelper2: AcceptanceHelper;
    let chatHelper2: ChatHelper;

    test.beforeEach(async ({ browser }) => {
      // Create a second browser context and page for the second user
      context2 = await browser.newContext();
      page2 = await context2.newPage();
      acceptanceHelper2 = new AcceptanceHelper(page2);
      chatHelper2 = new ChatHelper(page2);

      // Login as foodsaver2 in the second context
      await acceptanceHelper2.login(foodsaver2.email);
      await page2.evaluate(() =>
        localStorage.setItem("askForPushNotifications", "false"),
      );
    });

    test.afterEach(async () => {
      await acceptanceHelper2.logMeOut();
      await context2.close();
    });

    test("message delivery via websocket and status handling", async ({
      acceptanceHelper,
    }) => {
      // User 1 starts a conversation with user 2
      await chatHelper.openChatFromProfilePage(foodsaver2.id);
      await acceptanceHelper.waitForActiveAPICalls();
      const conversationId = (
        await foodsharing.getConversationsIdsForUser(foodsaver2.id)
      )[0];
      expect(
        await chatHelper.databaseHasUserConversation({
          foodsaverId: foodsaver2.id,
          conversationId,
          unread: 0,
        }),
      ).toBeTruthy();

      // User 1 sends a message to user 2
      const chatTextForFoodsaver1 = "is anyone there?";
      await chatHelper.fillText(chatTextForFoodsaver1);
      await chatHelper.clickSend();
      await expect(chatHelper.currentMessage).toContainText(
        chatTextForFoodsaver1,
      );

      // Message should be in the database
      expect(
        await chatHelper.databaseHasMessage({
          conversationId,
          foodsaverId: foodsaver1.id,
          body: chatTextForFoodsaver1,
        }),
      ).toBeTruthy();

      // User 2 should have the conversation with an unread message
      expect(
        await chatHelper.databaseHasUserConversation({
          conversationId,
          foodsaverId: foodsaver2.id,
          unread: 1,
        }),
      ).toBeTruthy();

      // User 2 sees the notification badge on the navbar
      await expect(chatHelper2.navConversations.unread).toContainText("1");

      // User 2 opens the conversation list and open the new conversation
      await chatHelper2.navConversations.open();
      await expect(chatHelper2.navConversations.getEntryUnread()).toContainText(
        "1",
      );
      await chatHelper2.openChatFromNavConversations();

      // User 2 should have the conversation marked as read after opening
      await acceptanceHelper2.waitForActiveAPICalls();
      expect(
        await chatHelper.databaseHasUserConversation({
          foodsaverId: foodsaver2.id,
          conversationId,
          unread: 0,
        }),
      ).toBeTruthy();
    });

    test("message delivery via websocket to messages page", async ({
      acceptanceHelper,
    }) => {
      // User 2 goes to the messages page
      await chatHelper2.messagePage.goto();

      // User 1 starts a conversation with user 2
      await chatHelper.openChatFromProfilePage(foodsaver2.id);
      await acceptanceHelper.waitForActiveAPICalls();

      // User 1 sends a message to user 2
      const chatTextForFoodsaver1 = "is anyone there?";
      await chatHelper.fillText(chatTextForFoodsaver1);
      await chatHelper.clickSend();
      await expect(chatHelper.currentMessage).toContainText(
        chatTextForFoodsaver1,
      );

      // User 2 sees the unread conversation in nav and room list
      await expect(chatHelper2.navConversations.unread).toContainText("1");
      await expect(chatHelper2.messagePage.roomListEntries).toHaveCount(1);
      await expect(
        chatHelper2.messagePage.roomListEntries.first(),
      ).toContainText(chatTextForFoodsaver1);
      await expect(
        chatHelper2.messagePage.roomListEntries.first(),
      ).toContainText(foodsaver1.name);
      await expect(
        await chatHelper2.messagePage.getRoomListEntryUnread(),
      ).toContainText("1");

      // User 2 opens the conversation
      await chatHelper2.messagePage.roomListEntries.first().click();
      await expect(chatHelper2.messagePage.textbox).toBeVisible();

      // User 2 should have the conversation marked as read after opening
      await acceptanceHelper2.waitForActiveAPICalls();
      await expect(chatHelper2.messagePage.unread).toBeHidden();
      await expect(chatHelper2.navConversations.getEntryUnread()).toBeHidden();
    });

    test("message via websocket stays unread when previously not loaded", async ({
      page,
    }) => {
      // Create a conversation with a read message
      const initiallyReadConversation = await foodsharing.createConversation(
        [foodsaver1.id, foodsaver2.id],
        { name: "First Conversation" },
      );
      await foodsharing.addConversationMessage(
        foodsaver2.id,
        initiallyReadConversation.id,
        {},
        false,
      );

      // Create enough unread conversations to push the conversation out of the loaded conversations list
      const CONVERSATION_FETCH_LIMIT = 20;
      for (let i = 0; i < CONVERSATION_FETCH_LIMIT; i++) {
        const conversation = await foodsharing.createConversation([
          foodsaver1.id,
          foodsaver2.id,
        ]);
        await foodsharing.addConversationMessage(
          foodsaver2.id,
          conversation.id,
        );
      }

      // User 1 has the unread conversations count at the limit
      await page.reload();
      await expect(chatHelper.navConversations.unread).toContainText(
        `${CONVERSATION_FETCH_LIMIT}`,
        { timeout: 10000 },
      );

      // ...and the initially read conversation is not in the loaded conversations list
      await chatHelper.navConversations.open();
      expect(await chatHelper.navConversations.entries.count()).toBe(
        CONVERSATION_FETCH_LIMIT,
      );
      await expect(
        chatHelper.navConversations.entries.locator(
          `:has-text("${initiallyReadConversation.name}")`,
        ),
      ).toHaveCount(0);

      // User 2 opens the first conversation and sends a message to user 1
      await chatHelper2.messagePage.goto(initiallyReadConversation.id);
      await acceptanceHelper2.waitForActiveAPICalls();
      const chatText = "is anyone there?";
      await chatHelper2.messagePage.fillText(chatText);
      await chatHelper2.messagePage.clickSend();
      await acceptanceHelper2.waitForActiveAPICalls();

      expect(
        await chatHelper.databaseHasUserConversation({
          foodsaverId: foodsaver1.id,
          conversationId: initiallyReadConversation.id,
          unread: 1,
        }),
      ).toBeTruthy();

      // User 1 has an additional unread conversation
      await expect(chatHelper.navConversations.unread).toContainText(
        `${CONVERSATION_FETCH_LIMIT + 1}`,
        { timeout: 10000 },
      );

      // User 1 sees the additional conversation in the conversations list
      await expect(chatHelper.navConversations.getEntry()).toContainText(
        chatText,
      );
      await expect(chatHelper.navConversations.getEntryUnread()).toContainText(
        "1",
      );

      // Message should still be marked as unread
      await acceptanceHelper2.waitForActiveAPICalls();
      expect(
        await chatHelper.databaseHasUserConversation({
          foodsaverId: foodsaver1.id,
          conversationId: initiallyReadConversation.id,
          unread: 1,
        }),
      ).toBeTruthy();
    });
  });

  test.describe("with a conversation with some read messages", () => {
    let conversation: Awaited<
      ReturnType<typeof foodsharing.createConversation>
    >;

    test.beforeEach(async ({ page }) => {
      // Create a conversation with some read messages to allow scrolling
      conversation = await foodsharing.createConversation([
        foodsaver1.id,
        foodsaver2.id,
      ]);
      for (let i = 0; i < 10; i++) {
        await foodsharing.addConversationMessage(
          foodsaver2.id,
          conversation.id,
          {},
          false,
        );
      }

      await page.reload();
    });

    test.describe(
      "with a popup chat and one unread message",
      { tag: [Tags.DESKTOP_ONLY] },
      () => {
        test.beforeEach(async ({ page, acceptanceHelper }) => {
          // Open the popup chat
          await chatHelper.navConversations.open();
          await expect(
            chatHelper.navConversations.getEntryUnread(),
          ).toBeHidden();
          await chatHelper.openChatFromNavConversations();
          await acceptanceHelper.waitForActiveAPICalls();

          // Add a new unread message to the conversation
          await foodsharing.addConversationMessage(
            foodsaver2.id,
            conversation.id,
          );
          expect(
            await chatHelper.databaseHasUserConversation({
              foodsaverId: foodsaver1.id,
              conversationId: conversation.id,
              unread: 1,
            }),
          ).toBeTruthy();

          await page.reload();
        });

        test("reload does not mark as read", async () => {
          // Check that the conversation is not marked as read after reloading the page
          await expect(chatHelper.popupChat.unread).toContainText("1", {
            timeout: 10000,
          });
          await expect(chatHelper.navConversations.unread).toContainText("1");
          expect(
            await chatHelper.databaseHasUserConversation({
              foodsaverId: foodsaver1.id,
              conversationId: conversation.id,
              unread: 1,
            }),
          ).toBeTruthy();
        });

        test("can deliberately mark as read and unread", async ({
          page,
          acceptanceHelper,
        }) => {
          const popupChat = chatHelper.popupChat;

          // Check that the conversation is not marked as read after reloading the page
          await popupChat.messages
            .first()
            .waitFor({ state: "visible", timeout: 10000 });
          await expect(popupChat.unread).toContainText("1");
          await expect(chatHelper.navConversations.unread).toContainText("1");
          expect(
            await chatHelper.databaseHasUserConversation({
              foodsaverId: foodsaver1.id,
              conversationId: conversation.id,
              unread: 1,
            }),
          ).toBeTruthy();

          // Click the chat textbox to mark as read
          await popupChat.textbox.click();
          await expect(popupChat.unread).toBeHidden();
          await expect(chatHelper.navConversations.unread).toBeHidden();
          await acceptanceHelper.waitForActiveAPICalls();
          expect(
            await chatHelper.databaseHasUserConversation({
              foodsaverId: foodsaver1.id,
              conversationId: conversation.id,
              unread: 0,
            }),
          ).toBeTruthy();

          // Mark as unread through the popup chat menu
          await popupChat.markAsUnread();
          await expect(chatHelper.navConversations.unread).toContainText("1");
          await acceptanceHelper.waitForActiveAPICalls();
          expect(
            await chatHelper.databaseHasUserConversation({
              foodsaverId: foodsaver1.id,
              conversationId: conversation.id,
              unread: -1,
            }),
          ).toBeTruthy();

          // Scroll up, should stay unread
          const scrollDistance = 200;
          await popupChat.messageList.focus();
          await page.mouse.wheel(0, -scrollDistance);
          await popupChat.waitForMessageListScroll();
          await expect(popupChat.unread).toBeVisible();

          // Scroll back to the bottom to mark as read
          await page.mouse.wheel(0, scrollDistance * 2); // Add some extra to ensure we are at the bottom
          await popupChat.waitForMessageListScroll();
          await expect(popupChat.unread).toBeHidden();

          // Mark as unread through the nav conversations menu
          await chatHelper.toggleConversationUnreadViaNavConversations();

          // Minimize popup chat, it should still be unread
          const popupChatHead = popupChat.element.locator(".chatboxhead");
          await popupChatHead.click();
          expect(popupChat.messageList).not.toBeInViewport();
          await expect(popupChat.unread).toBeVisible();

          // Un-minimize and check it gets marked as read
          await popupChatHead.click();
          expect(popupChat.messageList).toBeInViewport();
          await expect(popupChat.unread).toBeHidden();
        });
      },
    );

    test("messages page allows marking as read and unread", async ({
      page,
      acceptanceHelper,
    }, testInfo) => {
      const isDesktop = !testInfo.project.name.startsWith("Mobile");

      await chatHelper.messagePage.goto(conversation.id);
      await acceptanceHelper.waitForActiveAPICalls();
      const messagePage = chatHelper.messagePage;

      // Conversation should be marked as read when opening in messages page
      if (isDesktop) {
        // On desktop, check the unread badge on the room list entry
        await expect(await messagePage.getRoomListEntryUnread()).toBeHidden();
      }
      // On mobile, the room list is hidden, so only check the unread badge in the nav
      await expect(chatHelper.navConversations.unread).toBeHidden();
      expect(
        await chatHelper.databaseHasUserConversation({
          foodsaverId: foodsaver1.id,
          conversationId: conversation.id,
          unread: 0,
        }),
      ).toBeTruthy();

      // Mark as unread through the chat entry in the conversations list
      await chatHelper.toggleConversationUnreadViaNavConversations();
      if (isDesktop) {
        await expect(await messagePage.getRoomListEntryUnread()).toBeVisible();
      }
      await expect(chatHelper.navConversations.unread).toBeVisible();
      await expect(messagePage.unread).toBeVisible();
      await acceptanceHelper.waitForActiveAPICalls();
      expect(
        await chatHelper.databaseHasUserConversation({
          foodsaverId: foodsaver1.id,
          conversationId: conversation.id,
          unread: -1,
        }),
      ).toBeTruthy();

      // Click the chat textbox to mark as read
      await messagePage.textbox.click();
      await expect(chatHelper.navConversations.unread).toBeHidden();
      await expect(messagePage.unread).toBeHidden();
      await acceptanceHelper.waitForActiveAPICalls();
      expect(
        await chatHelper.databaseHasUserConversation({
          foodsaverId: foodsaver1.id,
          conversationId: conversation.id,
          unread: 0,
        }),
      ).toBeTruthy();

      // Mark as unread again
      await messagePage.markAsUnread();

      // Click the messages area to mark as read
      await messagePage.messageList.click();
      await expect(chatHelper.navConversations.unread).toBeHidden();

      // Mark as unread through the nav conversations menu
      await chatHelper.toggleConversationUnreadViaNavConversations();

      if (isDesktop) {
        // Type in the chat textbox to mark as read
        await messagePage.textbox.press("a");
        await expect(chatHelper.navConversations.unread).toBeHidden();

        // Mark as unread again
        await messagePage.markAsUnread();
      }

      // Scroll up, should stay unread
      const scrollDistance = 100;
      const boundingBox = await messagePage.messageList.boundingBox();
      await messagePage.messageList.focus();
      await page.mouse.move(boundingBox.x + 50, boundingBox.y + 50);
      if (isDesktop) {
        await page.mouse.wheel(0, -scrollDistance);
        await messagePage.waitForMessageListScroll();
      } else {
        await messagePage.messageList.dispatchEvent("touchstart", {});
        await messagePage.scrollMessageListBy(-scrollDistance);
        await messagePage.messageList.dispatchEvent("touchend", {});
      }
      await expect(chatHelper.navConversations.unread).toBeVisible();

      // Scroll back to the bottom to mark as read
      await messagePage.messageList.focus();
      if (isDesktop) {
        await page.mouse.wheel(
          0,
          (await messagePage.messageList.boundingBox()).height,
        );
        await messagePage.waitForMessageListScroll();
      } else {
        await messagePage.messageList.dispatchEvent("touchstart", {});
        await messagePage.scrollMessageListToBottom();
        await messagePage.messageList.dispatchEvent("touchend", {});
      }
      await expect(chatHelper.navConversations.unread).toBeHidden();
    });
  });
});
