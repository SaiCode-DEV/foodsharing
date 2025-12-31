import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";
import { Database } from "../helpers/database";

test.describe("Chat", () => {
  test("can send and receive chat messages", async ({
    page,
    acceptanceHelper,
  }) => {
    test.setTimeout(60000); // Extended timeout for multi-user interaction

    const region = await foodsharing.createRegion();
    const foodsaver1 = await foodsharing.createFoodsaver(null, {
      bezirk_id: region.id,
    });
    const foodsaver2 = await foodsharing.createFoodsaver(null, {
      bezirk_id: region.id,
    });

    // Login as foodsaver1 and start a chat
    await acceptanceHelper.login(foodsaver1.email);

    // Set localStorage item to avoid push notification prompt
    await page.evaluate(() =>
      localStorage.setItem("askForPushNotifications", "false"),
    );

    // View the other user's profile and start a chat
    await page.goto(`/profile/${foodsaver2.id}`);
    await page.click("text=Nachricht schreiben");

    await page.waitForSelector("#roomTextarea", {
      state: "visible",
      timeout: 50000,
    });

    // Write a message to them
    const chatTextForFoodsaver1 = "is anyone there?";
    await page.fill("#roomTextarea", chatTextForFoodsaver1);
    await acceptanceHelper.clickChatSendButton();
    await expect(
      page.locator(".vac-message-card.vac-message-current"),
    ).toContainText(chatTextForFoodsaver1, { timeout: 20000 });
    expect(
      await Database.seeInDatabase("fs_msg", {
        foodsaver_id: foodsaver1.id,
        body: chatTextForFoodsaver1,
      }),
    ).toBeTruthy();

    await acceptanceHelper.logMeOut();

    // Login as foodsaver2 in the second context
    await acceptanceHelper.login(foodsaver2.email);

    // Set localStorage item to avoid push notification prompt
    await page.evaluate(() =>
      localStorage.setItem("askForPushNotifications", "false"),
    );

    // Check they have the notification badge on conversations icon
    const conversationsButton = page.getByRole("button", {
      name: /Nachrichten/i,
    });
    await expect(conversationsButton.locator(".badge")).toContainText("1", {
      timeout: 10000,
    });

    // Open the conversation menu and open the new conversation
    await conversationsButton.click();
    await page.waitForSelector(".dropdown-menu.show .dropdown-item", {
      state: "visible",
      timeout: 4000,
    });
    await page.locator(".dropdown-menu.show .dropdown-item").first().click();
    await page.waitForSelector(".vac-container, #roomTextarea", {
      state: "visible",
      timeout: 6000,
    });

    // Write a nice reply - find the textarea in the chat component
    const chatTextForFoodsaver2 = "yes! I am here!";
    const chatTextarea2 = page.locator(".vac-textarea, #roomTextarea").first();
    await chatTextarea2.fill(chatTextForFoodsaver2);
    await acceptanceHelper.clickChatSendButton();

    // First verify the message was sent on page2
    await expect(
      page.locator(".vac-message-card.vac-message-current"),
    ).toContainText(chatTextForFoodsaver2, { timeout: 10000 });

    // Check that foodsaver1 receives the reply in the chatbox
    await expect(page.locator(".vac-message-card").first()).toContainText(
      chatTextForFoodsaver1,
      { timeout: 15000 },
    );

    expect(
      await Database.seeInDatabase("fs_msg", {
        foodsaver_id: foodsaver2.id,
        body: chatTextForFoodsaver2,
      }),
    ).toBeTruthy();
  });
});
