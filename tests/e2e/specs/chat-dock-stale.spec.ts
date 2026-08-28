import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";

// #2872: the dock restores its windows from the browser, and a conversation the
// user lost access to left a broken box plus two raw errors on the page.
test("dock drops a restored conversation it may not access", async ({
  page,
  acceptanceHelper,
}) => {
  const region = await foodsharing.createRegion();
  const user = await foodsharing.createFoodsaver(null, {
    bezirk_id: region.id,
  });
  const other = await foodsharing.createFoodsaver(null, {
    bezirk_id: region.id,
  });
  const foreign = await foodsharing.createConversation([other.id]);

  await acceptanceHelper.login(user.email);
  await page.evaluate(() =>
    localStorage.setItem("askForPushNotifications", "false"),
  );

  // a window from an earlier session, pointing at a conversation of someone else
  await page.evaluate((id) => {
    // Storage prefixes its keys and wraps the value, see client/src/storage.js
    localStorage.setItem(
      "conversations:msg-chats",
      JSON.stringify({ v: [{ id, min: false }] }),
    );
  }, foreign.id);

  await page.goto("/dashboard");
  await page.waitForTimeout(4000);

  const state = await page.evaluate(() => ({
    boxes: document.querySelectorAll(".chat-dock vue-advanced-chat").length,
    errors: Array.from(
      document.querySelectorAll(".vue-notification-wrapper"),
    ).map((el) => (el.textContent || "").replace(/\s+/g, " ").slice(0, 60)),
  }));
  console.log("STATE:", JSON.stringify(state));

  expect(state.boxes).toBe(0);
});
