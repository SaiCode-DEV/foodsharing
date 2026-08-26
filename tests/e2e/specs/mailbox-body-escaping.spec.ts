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

  // #2835: the url detection took everything up to the next space, so sentence
  // punctuation ended up inside the link and the link ran into a 404.
  test("keeps trailing punctuation out of a link", async ({
    page,
    acceptanceHelper,
  }) => {
    const { coordinator, mailbox } = await emptyMailbox();
    const profileUrl = "https://foodsharing.de/profile/1";
    const wikiUrl = "https://de.wikipedia.org/wiki/Berlin_(Stadt)";
    await foodsharing.createEmail(mailbox, MailboxFolder.INBOX, {
      subject: "Link test",
      body: `Schau [${profileUrl}] an, dann ${wikiUrl} lesen.`,
      body_html: "",
      read: 0,
    });

    await acceptanceHelper.login(coordinator.email);
    await page.goto(`/mailbox?mailbox=${mailbox.id}`);
    await page.getByText("Link test").first().click();

    // wait for the mail to be open before reading the rendered links
    await expect(page.locator("#app-content")).toContainText("dann");

    const hrefs = await page
      .locator("#app-content a[href^='https://']")
      .evaluateAll((links) => links.map((a) => a.getAttribute("href")));

    // the bracket belongs to the text, the one inside the wikipedia path does not
    expect(hrefs).toContain(profileUrl);
    expect(hrefs).toContain(wikiUrl);
  });
});
