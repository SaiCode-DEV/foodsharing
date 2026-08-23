import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";
import { Database } from "../helpers/database";

// The e2e helper documents the folders as [0, 1, 2], but the backend counts from 1
// (MailboxFolder::FOLDER_INBOX), so a mail in folder 0 never shows up anywhere.
const MailboxFolder = { INBOX: 1 };

// A store coordinator gets a mailbox prefilled with random mails, which would bury
// the one this test is about. So the mailbox is replaced by an empty one.
async function emptyMailbox() {
  const coordinator = await foodsharing.createStoreCoordinator();
  const mailbox = await foodsharing.createMailbox(null, false, true);
  const conn = await Database.connect();
  await conn.execute("UPDATE fs_foodsaver SET mailbox_id = ? WHERE id = ?", [
    mailbox.id,
    coordinator.id,
  ]);
  return { coordinator, mailbox };
}

// #2469: the mail body is plain text but gets rendered through v-html. Without
// escaping, everything between angle brackets is swallowed by the browser.
test.describe("Mailbox body rendering", () => {
  test("shows angle brackets in a plain text body instead of dropping them", async ({
    page,
    acceptanceHelper,
  }) => {
    const { coordinator, mailbox } = await emptyMailbox();
    const body = "Schreib an <foo@bar.de> und dann geht es hier weiter";
    await foodsharing.createEmail(mailbox, MailboxFolder.INBOX, {
      subject: "Angle bracket test",
      body,
      body_html: "",
      read: 0,
    });

    await acceptanceHelper.login(coordinator.email);
    await page.goto(`/mailbox?mailbox=${mailbox.id}`);
    await page.getByText("Angle bracket test").first().click();

    await expect(page.locator("#app-content")).toContainText(body);
  });

  test("does not execute markup that arrives in a plain text body", async ({
    page,
    acceptanceHelper,
  }) => {
    const { coordinator, mailbox } = await emptyMailbox();
    const body = '<img src="x" onerror="window.__xss = true">Ende';
    await foodsharing.createEmail(mailbox, MailboxFolder.INBOX, {
      subject: "Markup test",
      body,
      body_html: "",
      read: 0,
    });

    await acceptanceHelper.login(coordinator.email);
    await page.goto(`/mailbox?mailbox=${mailbox.id}`);
    await page.getByText("Markup test").first().click();

    await expect(page.locator("#app-content")).toContainText(body);
    expect(await page.evaluate(() => (window as any).__xss)).toBeUndefined();
  });
});
