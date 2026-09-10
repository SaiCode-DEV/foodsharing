import { test, expect } from "../helpers/acceptance";
import { ChatHelper } from "../helpers/chat";
import { Database } from "../helpers/database";
import { foodsharing } from "../helpers/foodsharing";
import { Tags } from "../helpers/tags";

test.describe("Chat message idempotency", () => {
  let chatHelper: ChatHelper;
  let region: Awaited<ReturnType<typeof foodsharing.createRegion>>;
  let foodsaver1: Awaited<ReturnType<typeof foodsharing.createFoodsaver>>;
  let foodsaver2: Awaited<ReturnType<typeof foodsharing.createFoodsaver>>;
  let conversation: Awaited<ReturnType<typeof foodsharing.createConversation>>;

  test.beforeEach(async ({ page, acceptanceHelper }) => {
    chatHelper = new ChatHelper(page);

    region = await foodsharing.createRegion();
    foodsaver1 = await foodsharing.createFoodsaver(null, {
      bezirk_id: region.id,
    });
    foodsaver2 = await foodsharing.createFoodsaver(null, {
      bezirk_id: region.id,
    });
    conversation = await foodsharing.createConversation([
      foodsaver1.id,
      foodsaver2.id,
    ]);
    // Give the conversation an initial message so it shows up in the nav list.
    await foodsharing.addConversationMessage(
      foodsaver2.id,
      conversation.id,
      {},
      false,
    );

    await acceptanceHelper.login(foodsaver1.email);
    await page.evaluate(() =>
      localStorage.setItem("askForPushNotifications", "false"),
    );
  });

  test.afterEach(async ({ acceptanceHelper }) => {
    await acceptanceHelper.logMeOut();
  });

  const messageCount = (body: string) =>
    Database.grabColumnFromDatabase("fs_msg", "id", {
      conversation_id: conversation.id,
      body,
    }).then((ids) => ids.length);

  // The server-side deduplication is covered by the API tests (MessageApiCest).
  // This checks the client half: retrying a failed message reuses the same
  // idempotency key, so a retry after a lost response is deduplicated by the
  // server instead of creating a second message.
  test(
    "retrying a failed message reuses the idempotency key and stores it once",
    { tag: [Tags.DESKTOP_ONLY] },
    async ({ page, acceptanceHelper }) => {
      await chatHelper.navConversations.open();
      await chatHelper.openChatFromNavConversations();
      await acceptanceHelper.waitForActiveAPICalls();
      await chatHelper.textbox.waitFor({ state: "visible", timeout: 10000 });

      const sentKeys: (string | undefined)[] = [];
      page.on("request", (req) => {
        if (
          req.method() === "POST" &&
          /\/conversations\/\d+\/messages$/.test(req.url())
        ) {
          try {
            sentKeys.push(JSON.parse(req.postData() || "{}").clientKey);
          } catch {
            sentKeys.push(undefined);
          }
        }
      });

      // Make the first send fail so the client shows the message as failed.
      let sendCount = 0;
      await page.route("**/api/conversations/*/messages", async (route) => {
        if (route.request().method() !== "POST") {
          await route.continue();
          return;
        }
        sendCount++;
        if (sendCount === 1) {
          await route.fulfill({
            status: 500,
            contentType: "application/json",
            body: JSON.stringify({ error: "simulated failure" }),
          });
        } else {
          await route.continue();
        }
      });

      const body = "did this send?";
      await chatHelper.fillText(body);
      await chatHelper.clickSend();

      // The failed send happened and stored nothing.
      await expect.poll(() => sentKeys.length).toBe(1);
      await expect.poll(() => messageCount(body)).toBe(0);

      // Retry the failed message (the first failure placeholder has id -1).
      await chatHelper.element.evaluate((chat, cid) => {
        chat.dispatchEvent(
          new CustomEvent("open-failed-message", {
            detail: [{ roomId: String(cid), message: { indexId: -1 } }],
          }),
        );
      }, conversation.id);
      await acceptanceHelper.waitForActiveAPICalls();

      // Exactly one message stored, and the retry carried the same idempotency key
      // as the original send.
      await expect.poll(() => messageCount(body)).toBe(1);
      expect(sentKeys).toHaveLength(2);
      expect(sentKeys[0]).toBeTruthy();
      expect(sentKeys[1]).toBe(sentKeys[0]);
    },
  );

  // A deduplicated send can acknowledge without a message body (the stored row was
  // not readable back). The retry must not blow up on it.
  test(
    "a retry acknowledged without a message keeps the failed entry",
    { tag: [Tags.DESKTOP_ONLY] },
    async ({ page, acceptanceHelper }) => {
      const jsErrors: string[] = [];
      page.on("pageerror", (error) => jsErrors.push(String(error)));
      page.on("console", (message) => {
        if (message.type() === "error") jsErrors.push(message.text());
      });

      await chatHelper.navConversations.open();
      await chatHelper.openChatFromNavConversations();
      await acceptanceHelper.waitForActiveAPICalls();
      await chatHelper.textbox.waitFor({ state: "visible", timeout: 10000 });

      // First send fails, the retry is answered with 200 and a null body.
      let sendCount = 0;
      await page.route("**/api/conversations/*/messages", async (route) => {
        if (route.request().method() !== "POST") {
          await route.continue();
          return;
        }
        sendCount++;
        await route.fulfill({
          status: sendCount === 1 ? 500 : 200,
          contentType: "application/json",
          body:
            sendCount === 1 ? JSON.stringify({ error: "simulated" }) : "null",
        });
      });

      const body = "acknowledged without a body";
      await chatHelper.fillText(body);
      await chatHelper.clickSend();
      await expect.poll(() => sendCount).toBe(1);

      await chatHelper.element.evaluate((chat, cid) => {
        chat.dispatchEvent(
          new CustomEvent("open-failed-message", {
            detail: [{ roomId: String(cid), message: { indexId: -1 } }],
          }),
        );
      }, conversation.id);
      await expect.poll(() => sendCount).toBe(2);
      await acceptanceHelper.waitForActiveAPICalls();

      // The first send fails on purpose and logs that, so only null-dereferences count.
      expect(
        jsErrors.filter((text) => /of null|of undefined/.test(text)),
      ).toEqual([]);
      // The text is still on screen, so it can be sent again.
      await expect(page.getByText(body)).toBeVisible();
    },
  );
});
