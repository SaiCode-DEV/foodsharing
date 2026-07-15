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

  test("shows avatars and an overflow count in the dock for a big named chat", async ({
    page,
    isMobile,
  }) => {
    // mobile has no chat dock: openChat navigates to the message page instead
    test.skip(isMobile, "the chat dock only exists on desktop viewports");
    const extra = [];
    for (let i = 0; i < 17; i++) {
      extra.push(
        await foodsharing.createFoodsaver(null, { bezirk_id: region.id }),
      );
    }
    const conversation = await foodsharing.createConversation(
      [
        me.id,
        others[0].id,
        others[1].id,
        others[2].id,
        ...extra.map((u) => u.id),
      ],
      { name: "Team Dock" },
    );
    await foodsharing.addConversationMessage(others[0].id, conversation.id);
    await page.goto("/dashboard");
    await chatHelper.navConversations.open();
    await chatHelper.navConversations.getEntry(0).click();
    const title = page.locator(".chat-dock .chatboxtitle");
    await expect(title).toContainText("Team Dock");

    // members: 3 others + 17 extra = 20 total others.
    // Avatars count depends on viewport width. We expect at least one to be visible.
    const avatars = page.locator(".chat-dock .chatboxhead .b-avatar");
    await expect(avatars.first()).toBeVisible();

    const overflow = page.locator(
      ".chat-dock .chatboxhead .participant-overflow",
    );
    await expect(overflow).toContainText("+");

    // clicking the chip opens the participants dialog (all members except me)
    await overflow.click();
    const dialog = page.locator(".modal-dialog", {
      hasText: "Teilnehmer:innen",
    });
    await expect(dialog).toBeVisible();
    await expect(dialog.locator(".participant-card")).toHaveCount(20);
  });

  test("shows the single avatar without a chip in the dock for a small named chat", async ({
    page,
    isMobile,
  }) => {
    // mobile has no chat dock: openChat navigates to the message page instead
    test.skip(isMobile, "the chat dock only exists on desktop viewports");
    const conversation = await foodsharing.createConversation(
      [me.id, others[0].id],
      { name: "Team Klein" },
    );
    await foodsharing.addConversationMessage(others[0].id, conversation.id);
    await page.goto("/dashboard");
    await chatHelper.navConversations.open();
    await chatHelper.navConversations.getEntry(0).click();
    const title = page.locator(".chat-dock .chatboxtitle");
    await expect(title).toContainText("Team Klein");
    await expect(title.locator(".b-avatar")).toHaveCount(1);
    await expect(title.locator(".participant-overflow")).toHaveCount(0);
  });

  test("shows the title without a spinner for a named chat where I am the only member", async ({
    page,
    isMobile,
  }) => {
    // mobile has no chat dock: openChat navigates to the message page instead
    test.skip(isMobile, "the chat dock only exists on desktop viewports");
    const conversation = await foodsharing.createConversation([me.id], {
      name: "Team Allein",
    });
    await foodsharing.addConversationMessage(me.id, conversation.id);
    await page.goto("/dashboard");
    await chatHelper.navConversations.open();
    await chatHelper.navConversations.getEntry(0).click();
    const title = page.locator(".chat-dock .chatboxtitle");
    await expect(title).toContainText("Team Allein");
    // no members besides me, so no avatars and - crucially - no loading spinner
    await expect(title.locator(".b-avatar")).toHaveCount(0);
    await expect(title.locator(".fa-spinner")).toHaveCount(0);
  });
});
