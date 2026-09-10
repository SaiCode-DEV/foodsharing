import { Database } from "./database";
import { expect, type Locator, type Page } from "@playwright/test";

export class NavConversationsHelper {
  public readonly button: Locator;
  public readonly unread: Locator;
  public readonly list: Locator;
  public readonly entries: Locator;

  constructor(private readonly page: Page) {
    this.button = page.getByRole("button", { name: /Nachrichten/i });
    this.unread = this.button.locator(".badge");
    this.list = page
      .locator(".nav-item", { has: this.button })
      .locator(".dropdown-menu.show");
    this.entries = this.list.locator(".dropdown-item.dropdown-header");
  }

  getEntry(index: number = 0): Locator {
    return this.entries.nth(index);
  }

  getEntryUnread(index: number = 0): Locator {
    return this.getEntry(index).locator(".b-avatar-badge");
  }

  async open() {
    if (await this.list.isHidden()) {
      await this.button.click();
      await this.list.waitFor({ state: "visible", timeout: 5000 });
    }
  }

  async close() {
    if (await this.list.isVisible()) {
      await this.button.click();
      await this.list.waitFor({ state: "hidden", timeout: 5000 });
    }
  }

  async openChatFromEntry(index: number = 0) {
    await this.getEntry(index).click();
    await this.list.waitFor({ state: "hidden", timeout: 10000 });
  }

  async toggleEntryUnread(index: number = 0) {
    const entry = this.getEntry(index);
    // Save the handle for state checking, as the entry might move in the DOM
    const entryHandle = await entry.elementHandle();
    const wasUnread = await this.getEntryUnread(index).isVisible();

    await entry.hover();
    await entry.locator(".mark-read-button").click();

    // Check if the entry state was toggled
    await entryHandle.waitForSelector(".b-avatar-badge", {
      state: wasUnread ? "hidden" : "visible",
      timeout: 5000,
    });
  }
}

class ChatElementHelper {
  public readonly textbox: Locator;
  public readonly messageList: Locator;
  public readonly messages: Locator;
  public readonly currentMessage: Locator;
  public readonly messageListSpinner: Locator;
  public readonly sendButton: Locator;
  public readonly unread: Locator;
  public readonly menuToggle: Locator;
  public readonly menuItems: Locator;

  constructor(public readonly element: Locator) {
    this.textbox = element.locator(".vac-textarea");
    this.messageList = element.locator(
      ".vac-col-messages .vac-container-scroll",
    );
    this.messages = element.locator(".vac-message-card");
    this.currentMessage = element.locator(
      ".vac-message-card.vac-message-current",
    );
    this.messageListSpinner = this.messageList.locator(
      ".vac-loader-wrapper.vac-vontainer-center",
    );
    this.sendButton = element.locator(
      ".vac-icon-textarea > .vac-svg-button:not(.vac-send-disabled)",
    );
    this.unread = element.getByRole("status", { name: /ungelesen/i });
    this.menuToggle = element.getByRole("button", { name: /Optionen/i });
    this.menuItems = element
      .getByRole("menu", { name: /Optionen/i })
      .getByRole("menuitem");
  }

  async fillText(text: string) {
    await this.textbox.fill(text);
  }

  async clickSend() {
    expect(await this.sendButton.count()).toBeGreaterThan(0);
    expect(this.sendButton).toBeVisible({ timeout: 2000 });
    await this.sendButton.click();
  }

  async scrollMessageListBy(deltaY: number) {
    this.messageList.evaluate(
      (element, deltaY) => element.scrollBy(0, deltaY),
      deltaY,
    );
    await this.waitForMessageListScroll();
  }

  async scrollMessageListToBottom() {
    this.messageList.evaluate((element) =>
      element.scrollTo(0, element.scrollHeight),
    );
    await this.waitForMessageListScroll();
  }

  async waitForMessageListScroll() {
    await (
      await this.messageList.elementHandle()
    ).waitForElementState("stable", {
      timeout: 5000,
    });
    await this.element.page().waitForTimeout(1000);
  }

  async openMenu() {
    if (!(await this.menuItems.first().isVisible())) {
      await this.menuToggle.click();
      await this.menuItems.first().waitFor({ state: "visible", timeout: 5000 });
    }
  }

  async markAsUnread() {
    await this.openMenu();
    await this.menuItems.filter({ hasText: "ungelesen" }).click();
    await this.unread.waitFor({ state: "visible", timeout: 5000 });
  }
}

export class PopupChatHelper extends ChatElementHelper {
  async waitUntilLoaded() {
    await this.messageList.waitFor({ state: "visible", timeout: 10000 });
    await this.messageListSpinner.waitFor({ state: "hidden", timeout: 10000 });
  }
}

export class MessagePageHelper extends ChatElementHelper {
  public readonly roomList: Locator;
  public readonly roomListEntries: Locator;
  public readonly roomListSpinner: Locator;

  constructor(protected readonly page: Page) {
    // The router mounts every page inside #app-content. Anchoring on the wrapper
    // the layout emits instead would tie the helper to a full page load.
    super(page.locator("#app-content vue-advanced-chat"));
    this.roomList = this.element.locator(".vac-room-list");
    this.roomListEntries = this.roomList.locator(".vac-room-item");
    this.roomListSpinner = this.roomList.locator(
      ".vac-loader-wrapper.vac-vontainer-center",
    );
  }

  async goto(conversationId?: number) {
    await this.page.goto(
      `/msg${conversationId ? `?cid=${conversationId}` : ""}`,
    );
    await this.waitUntilLoaded();
  }

  async waitUntilLoaded() {
    await Promise.race([
      this.roomList.waitFor({ state: "visible", timeout: 10000 }),
      this.messageList.waitFor({ state: "visible", timeout: 10000 }),
    ]);

    await this.roomListSpinner.waitFor({ state: "hidden", timeout: 10000 });
    await this.messageListSpinner.waitFor({ state: "hidden", timeout: 10000 });
  }

  async getRoomListEntryUnread(index: number = 0): Promise<Locator> {
    const entry = this.roomListEntries.nth(index);
    // The avatar is slotted into the room list entry, so we
    // get the slot name and search for it outside the shadowRoot
    const avatarSlotName = await entry
      .locator("slot[name^='room-list-avatar_']")
      .getAttribute("name");
    return this.page.locator(`*[slot="${avatarSlotName}"] .b-avatar-badge`);
  }
}

/*
 * This helper combines all chat-related helpers and provides common functions for chat interactions in the tests.
 * It also includes some database helper functions to verify the state of chats and conversations in the database.
 * It includes helpers to interact specifically with either popup chats or the message page,
 * and additionally extends ChatElementHelper to allow interacting with one chat, ignoring the type and location.
 */
export class ChatHelper extends ChatElementHelper {
  public readonly navConversations: NavConversationsHelper;
  public readonly popupChat: PopupChatHelper;
  public readonly messagePage: MessagePageHelper;

  constructor(private readonly page: Page) {
    super(page.locator("vue-advanced-chat").first());
    this.navConversations = new NavConversationsHelper(page);
    this.popupChat = this.getPopupChatHelper(0);
    this.messagePage = new MessagePageHelper(page);
  }

  getPopupChatHelper(index: number): PopupChatHelper {
    return new PopupChatHelper(
      this.page.locator(`.chat-dock .chat-dock__box`).nth(index),
    );
  }

  async waitForAllChatsToInitialize() {
    await expect(
      this.page.locator(".vac-loader-wrapper.vac-container-center"),
    ).toHaveCount(0, { timeout: 10000 });
  }

  async openChatFromProfilePage(foodsaverId: number) {
    await this.page.goto(`/profile/${foodsaverId}`);
    await this.page.click("text=Nachricht schreiben");
    await this.waitForAllChatsToInitialize();
  }

  async openChatFromNavConversations(index: number = 0) {
    await this.navConversations.open();
    await this.navConversations.getEntry(index).click();
    await this.waitForAllChatsToInitialize();
  }

  async toggleConversationUnreadViaNavConversations(index: number = 0) {
    await this.navConversations.open();
    await this.navConversations.toggleEntryUnread(index);
    await this.navConversations.close();
  }

  async databaseHasMessage({
    conversationId,
    foodsaverId,
    body,
  }: {
    conversationId?: number;
    foodsaverId?: number;
    body?: string;
  }) {
    return Database.seeInDatabase("fs_msg", {
      conversation_id: conversationId,
      foodsaver_id: foodsaverId,
      body,
    });
  }

  async databaseHasUserConversation({
    foodsaverId,
    conversationId,
    unread,
  }: {
    foodsaverId: number;
    conversationId: number;
    unread?: number;
  }) {
    return Database.seeInDatabase("fs_foodsaver_has_conversation", {
      foodsaver_id: foodsaverId,
      conversation_id: conversationId,
      unread,
    });
  }
}
