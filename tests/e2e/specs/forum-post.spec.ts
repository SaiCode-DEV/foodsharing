import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";
import { Database } from "../helpers/database";
import { maildev } from "../helpers/maildev";
import UnitType from "../helpers/constants/Region/UnitType";
import { faker } from "@faker-js/faker";

const testData = {} as {
  ambassador: Awaited<ReturnType<typeof foodsharing.createAmbassador>>;
  foodsaver: Awaited<ReturnType<typeof foodsharing.createFoodsaver>>;
  unverifiedFoodsaver: Awaited<ReturnType<typeof foodsharing.createFoodsaver>>;
  testBezirk: Awaited<ReturnType<typeof foodsharing.createRegion>>;
  bigTestBezirk: Awaited<ReturnType<typeof foodsharing.createRegion>>;
  moderatedTestBezirk: Awaited<ReturnType<typeof foodsharing.createRegion>>;
};

test.beforeAll(async () => {
  testData.testBezirk = await foodsharing.createRegion("testRegion", {}, false);
  testData.bigTestBezirk = await foodsharing.createRegion(
    "bigTestRegion",
    { type: UnitType.BIG_CITY },
    false,
  );
  testData.moderatedTestBezirk = await foodsharing.createRegion(
    "moderatedTestRegion",
    { type: UnitType.CITY, moderated: true },
    false,
  );
  testData.ambassador = await foodsharing.createAmbassador(null, {
    bezirk_id: testData.testBezirk.id,
  });
  testData.foodsaver = await foodsharing.createFoodsaver(null, {
    bezirk_id: testData.testBezirk.id,
  });
  testData.unverifiedFoodsaver = await foodsharing.createFoodsaver(null, {
    bezirk_id: testData.testBezirk.id,
    verified: false,
  });
  await foodsharing.addRegionMember(
    testData.moderatedTestBezirk.id,
    testData.unverifiedFoodsaver.id,
  );
  await foodsharing.addRegionAdmin(
    testData.testBezirk.id,
    testData.ambassador.id,
  );
  await foodsharing.addRegionAdmin(
    testData.bigTestBezirk.id,
    testData.ambassador.id,
  );
  await foodsharing.addRegionAdmin(
    testData.moderatedTestBezirk.id,
    testData.ambassador.id,
  );
  await foodsharing.addRegionMember(
    testData.bigTestBezirk.id,
    testData.foodsaver.id,
  );
  await foodsharing.addRegionMember(
    testData.moderatedTestBezirk.id,
    testData.foodsaver.id,
  );
});

test.describe("Forum Post with Mails", () => {
  test.describe.configure({ mode: "default" });

  test("New thread will be moderated", async ({ page, acceptanceHelper }) => {
    await acceptanceHelper.login(testData.unverifiedFoodsaver.email);
    const title = faker.word.words(5);
    await foodsharing.createForumThread({
      forumId: testData.moderatedTestBezirk.id,
      title,
      body: "TestThreadPost",
      page,
    });
    await expect(page.locator("body")).not.toContainText(title);
    const subject = "bestätigt werden";
    const mail = await maildev.waitForMail(subject);
    expect(mail).not.toBeNull();
  });

  test("New thread will not send email", async ({ page, acceptanceHelper }) => {
    await acceptanceHelper.login(testData.foodsaver.email);
    const title = faker.word.words(5);
    await foodsharing.createForumThread({
      forumId: testData.testBezirk.id,
      title,
      body: "TestThreadPost",
      page,
    });
    await acceptanceHelper.waitForActiveAPICalls();
    await page.goto(`/region?bid=${testData.testBezirk.id}&sub=forum`);
    await acceptanceHelper.waitForPageBody();
    await expect(page.locator("body")).toContainText(title);
    const mail = await maildev.waitForMail(title);
    expect(mail).toBeNull();
  });

  test("New thread will send email", async ({ page, acceptanceHelper }) => {
    await acceptanceHelper.login(testData.foodsaver.email);
    const title = faker.word.words(5);
    await foodsharing.createForumThread({
      forumId: testData.testBezirk.id,
      title,
      body: "TestThreadPost",
      sendMail: true,
      page,
    });
    await acceptanceHelper.waitForActiveAPICalls();
    await page.goto(`/region?bid=${testData.testBezirk.id}&sub=forum`);
    await acceptanceHelper.waitForPageBody();
    await expect(page.locator("body")).toContainText(title);
    const mail = await maildev.waitForMail(title);
    expect(mail).not.toBeNull();
  });

  test("New thread can be activated", async ({ page, acceptanceHelper }) => {
    await acceptanceHelper.login(testData.foodsaver.email);
    const title = faker.word.words(5);
    await foodsharing.createForumThread({
      forumId: testData.moderatedTestBezirk.id,
      title,
      body: "TestThreadPost",
      page,
    });
    await acceptanceHelper.waitForPageBody();
    let mail = await maildev.waitForMail(title);
    expect(mail).not.toBeNull();

    // Extract activation link from mail HTML using helper
    const link = mail.findLink("region");
    await acceptanceHelper.logMeOut();
    await acceptanceHelper.waitForActiveAPICalls();

    // Login as ambassador and activate thread
    await acceptanceHelper.login(testData.ambassador.email);
    await page.goto(link);
    await acceptanceHelper.waitForActiveAPICalls();
    await acceptanceHelper.waitForPageBody();
    await page.waitForSelector('button:has-text("Thema aktivieren")', {
      state: "visible",
    });
    await page.getByRole("button", { name: "Thema aktivieren" }).click();
    await acceptanceHelper.waitForActiveAPICalls();

    // After activation, navigate to forum to verify thread is visible
    await page.goto(`/region?bid=${testData.moderatedTestBezirk.id}&sub=forum`);
    await acceptanceHelper.waitForActiveAPICalls();
    await acceptanceHelper.waitForPageBody();
    await expect(page.locator("body")).toContainText(title, { timeout: 10000 });

    mail = await maildev.waitForMail(title);
    expect(mail).not.toBeNull();
  });

  test("Delete last post and get redirected to forum", async ({
    page,
    acceptanceHelper,
  }) => {
    test.setTimeout(60000);

    await acceptanceHelper.login(testData.foodsaver.email);
    const title = faker.word.words(5);
    await foodsharing.createForumThread({
      forumId: testData.moderatedTestBezirk.id,
      title,
      body: "TestThreadPost",
      page,
    });
    await acceptanceHelper.waitForPageBody();
    await acceptanceHelper.waitForActiveAPICalls();
    const mail = await maildev.waitForMail(title);
    expect(mail).not.toBeNull();
    const link = mail.findLink("region");
    await acceptanceHelper.logMeOut();

    // Login as ambassador and delete last post
    await acceptanceHelper.login(testData.ambassador.email);
    await page.goto(link);
    await acceptanceHelper.waitForActiveAPICalls();
    await expect(page.locator("body")).toContainText(title);
    await page.getByRole("button", { name: "Thema aktivieren" }).click();
    await acceptanceHelper.waitForActiveAPICalls();
    await acceptanceHelper.logMeOut();

    // Login as thread creator and delete the only post
    await acceptanceHelper.login(testData.foodsaver.email);
    await page.goto(`/region?bid=${testData.moderatedTestBezirk.id}&sub=forum`);
    await acceptanceHelper.waitForActiveAPICalls();
    await expect(page.locator("body")).toContainText(title);
    await page.click(".forum_threads a");
    await acceptanceHelper.waitForPageBody();
    await acceptanceHelper.waitForActiveAPICalls();
    await expect(page).toHaveURL(
      new RegExp(
        `/region\\?bid=${testData.moderatedTestBezirk.id}&sub=forum&tid=\\d+`,
      ),
    );
    await page.click('a[title="Beitrag löschen"]');
    await page.waitForSelector("text=Beitrag löschen");
    await page.click('.btn:has-text("Ja, ich bin mir sicher")');
    await expect(page).toHaveURL(
      new RegExp(`/region\\?bid=${testData.moderatedTestBezirk.id}&sub=forum`),
    );
    await page.waitForSelector(`text=${title}`);
  });
});

test.describe("Forum Post without Mail", () => {
  let threadUserAmbassador: Awaited<
    ReturnType<typeof foodsharing.seedForumThread>
  >;
  let threadAmbassadorUser: Awaited<
    ReturnType<typeof foodsharing.seedForumThread>
  >;

  // Threads werden modifiziert (close/open, pin/unpin, rename, follow) -> beforeEach
  test.beforeEach(async () => {
    threadUserAmbassador = await foodsharing.seedForumThread(
      testData.testBezirk.id,
      testData.foodsaver.id,
      false,
      { time: "2 hours ago" },
    );
    await foodsharing.addForumThreadPost(
      threadUserAmbassador.id,
      testData.ambassador.id,
      {
        time: "1 hour 45 minutes ago",
      },
    );
    threadAmbassadorUser = await foodsharing.seedForumThread(
      testData.testBezirk.id,
      testData.ambassador.id,
      false,
      { time: "1 hour ago" },
    );
    await foodsharing.addForumThreadPost(
      threadAmbassadorUser.id,
      testData.foodsaver.id,
      {
        time: "45 minutes ago",
      },
    );
  });

  test("Click follow/unfollow", async ({ page, acceptanceHelper }) => {
    await acceptanceHelper.login(testData.foodsaver.email);
    await page.goto(
      await foodsharing.forumThreadUrl(threadAmbassadorUser.id, null),
    );

    const button = ".subscribe-btn .btn-block";
    const dropdown = ".subscribe-btn .dropdown-toggle";
    const bellSwitch = ".dropdown-menu .bell-switch";
    const emailSwitch = ".dropdown-menu .email-switch";
    const isChecked = " input:checked";
    const isNotChecked = " input:not(:checked)";

    await acceptanceHelper.waitForActiveAPICalls();
    await page.waitForSelector(button);
    await expect(page.locator(button).first()).toContainText("Abonnieren");
    await page.click(dropdown);
    await page.waitForSelector(bellSwitch, { state: "visible" });
    await expect(page.locator(bellSwitch + isNotChecked).first()).toBeVisible();
    await expect(
      page.locator(emailSwitch + isNotChecked).first(),
    ).toBeVisible();
    await page.click(dropdown);
    await expect(page.locator(bellSwitch).first()).toBeHidden();
    await page.click(button);
    await acceptanceHelper.waitForActiveAPICalls();
    expect(
      await Database.seeInDatabase("fs_theme_follower", {
        foodsaver_id: testData.foodsaver.id,
        theme_id: threadAmbassadorUser.id,
        bell_notification: 1,
        infotype: 0,
      }),
    ).toBeTruthy();
    await expect(page.locator(button).first()).toContainText("Abonniert");
    await page.click(dropdown);
    await page.waitForSelector(bellSwitch, { state: "visible" });
    await expect(page.locator(bellSwitch + isChecked).first()).toBeVisible();
    await expect(
      page.locator(emailSwitch + isNotChecked).first(),
    ).toBeVisible();
    await page.click(bellSwitch + " a");
    await acceptanceHelper.waitForActiveAPICalls();
    expect(
      await Database.seeInDatabase("fs_theme_follower", {
        foodsaver_id: testData.foodsaver.id,
        theme_id: threadAmbassadorUser.id,
        bell_notification: 0,
        infotype: 0,
      }),
    ).toBeTruthy();
    await expect(page.locator(bellSwitch + isNotChecked).first()).toBeVisible();
    await page.click(bellSwitch + " a");
    await acceptanceHelper.waitForActiveAPICalls();
    expect(
      await Database.seeInDatabase("fs_theme_follower", {
        foodsaver_id: testData.foodsaver.id,
        theme_id: threadAmbassadorUser.id,
        bell_notification: 1,
        infotype: 0,
      }),
    ).toBeTruthy();
    await expect(page.locator(bellSwitch + isChecked).first()).toBeVisible();
    await expect(
      page.locator(emailSwitch + isNotChecked).first(),
    ).toBeVisible();

    await page.click(emailSwitch + " a");
    await acceptanceHelper.waitForActiveAPICalls();
    expect(
      await Database.seeInDatabase("fs_theme_follower", {
        foodsaver_id: testData.foodsaver.id,
        theme_id: threadAmbassadorUser.id,
        bell_notification: 1,
        infotype: 1,
      }),
    ).toBeTruthy();
    await expect(page.locator(emailSwitch + isChecked).first()).toBeVisible();
    await page.click(emailSwitch + " a");
    await acceptanceHelper.waitForActiveAPICalls();
    expect(
      await Database.seeInDatabase("fs_theme_follower", {
        foodsaver_id: testData.foodsaver.id,
        theme_id: threadAmbassadorUser.id,
        bell_notification: 1,
        infotype: 0,
      }),
    ).toBeTruthy();
    await expect(
      page.locator(emailSwitch + isNotChecked).first(),
    ).toBeVisible();
  });

  test("Close and open thread", async ({ page, acceptanceHelper }) => {
    await acceptanceHelper.login(testData.ambassador.email);
    await page.goto(
      await foodsharing.forumThreadUrl(threadAmbassadorUser.id, null),
    );
    await acceptanceHelper.waitForActiveAPICalls();
    const overflowMenu = ".overflow-menu button";
    await page.waitForSelector(overflowMenu);
    await page.click(overflowMenu);
    await page.getByRole("menuitem", { name: "Thema schließen" }).isVisible();
    await page.getByRole("menuitem", { name: "Thema schließen" }).click();
    await acceptanceHelper.waitForActiveAPICalls();
    expect(
      await Database.seeInDatabase("fs_theme", {
        id: threadAmbassadorUser.id,
        status: 1,
      }),
    ).toBeTruthy();
    await page.click(overflowMenu);
    await page.getByRole("menuitem", { name: "Thema öffnen" }).isVisible();
    await page.getByRole("menuitem", { name: "Thema öffnen" }).click();
    await acceptanceHelper.waitForActiveAPICalls();
    expect(
      await Database.seeInDatabase("fs_theme", {
        id: threadAmbassadorUser.id,
        status: 0,
      }),
    ).toBeTruthy();
  });

  test("Pin and unpin thread", async ({ page, acceptanceHelper }) => {
    await acceptanceHelper.login(testData.ambassador.email);
    await page.goto(
      await foodsharing.forumThreadUrl(threadAmbassadorUser.id, null),
    );
    await acceptanceHelper.waitForActiveAPICalls();
    const overflowMenu = ".overflow-menu button";
    await page.waitForSelector(overflowMenu);
    await page.click(overflowMenu);
    await page.getByRole("menuitem", { name: "Beitrag anheften" }).isVisible();
    await page.getByRole("menuitem", { name: "Beitrag anheften" }).click();
    await acceptanceHelper.waitForActiveAPICalls();
    expect(
      await Database.seeInDatabase("fs_theme", {
        id: threadAmbassadorUser.id,
        sticky: 1,
      }),
    ).toBeTruthy();
    await page.click(overflowMenu);
    await page
      .getByRole("menuitem", { name: "Nicht mehr anheften" })
      .isVisible();
    await page.getByRole("menuitem", { name: "Nicht mehr anheften" }).click();
    await acceptanceHelper.waitForActiveAPICalls();
    expect(
      await Database.seeInDatabase("fs_theme", {
        id: threadAmbassadorUser.id,
        sticky: 0,
      }),
    ).toBeTruthy();
  });

  test("Rename thread", async ({ page, acceptanceHelper }) => {
    await acceptanceHelper.login(testData.ambassador.email);
    await page.goto(
      await foodsharing.forumThreadUrl(threadAmbassadorUser.id, null),
    );
    await acceptanceHelper.waitForActiveAPICalls();
    const overflowMenu = ".overflow-menu button";
    const modalInput = ".modal-dialog input";
    await page.waitForSelector(overflowMenu);
    await page.click(overflowMenu);
    await page.getByRole("menuitem", { name: "Titel bearbeiten" }).isVisible();
    await page.getByRole("menuitem", { name: "Titel bearbeiten" }).click();
    await page.waitForSelector(modalInput, { state: "visible" });
    await page.fill(modalInput, "new title");
    await page.getByRole("button", { name: "Speichern" }).click();
    await acceptanceHelper.waitForActiveAPICalls();
    expect(
      await Database.seeInDatabase("fs_theme", {
        id: threadAmbassadorUser.id,
        name: "new title",
      }),
    ).toBeTruthy();
  });

  test("Foodsaver cannot see close/open menu for ambassador thread", async ({
    page,
    acceptanceHelper,
  }) => {
    await acceptanceHelper.login(testData.foodsaver.email);
    await page.goto(
      await foodsharing.forumThreadUrl(threadAmbassadorUser.id, null),
    );
    await acceptanceHelper.waitForActiveAPICalls();
    await page.locator(".overflow-menu button").first().click();
    const closeTopicVisible = await page
      .getByRole("menuitem", { name: "Thema schließen" })
      .isVisible()
      .catch(() => false);
    const openTopicVisible = await page
      .getByRole("menuitem", { name: "Thema öffnen" })
      .isVisible()
      .catch(() => false);
    expect(closeTopicVisible || openTopicVisible).toBeFalsy();
  });

  test("Foodsaver sees no close/open option for own thread", async ({
    page,
    acceptanceHelper,
  }) => {
    await acceptanceHelper.login(testData.foodsaver.email);
    await page.goto(
      await foodsharing.forumThreadUrl(threadUserAmbassador.id, null),
    );
    await acceptanceHelper.waitForActiveAPICalls();
    await page.locator(".overflow-menu button").first().click();
    // Should NOT see 'Thema schließen' or 'Thema öffnen'
    const closeTopicVisible = await page
      .getByRole("menuitem", { name: "Thema schließen" })
      .isVisible()
      .catch(() => false);
    const openTopicVisible = await page
      .getByRole("menuitem", { name: "Thema öffnen" })
      .isVisible()
      .catch(() => false);
    expect(closeTopicVisible || openTopicVisible).toBeFalsy();
  });

  test("Foodsaver cannot see pin/unpin menu for ambassador thread", async ({
    page,
    acceptanceHelper,
  }) => {
    await acceptanceHelper.login(testData.foodsaver.email);
    await page.goto(
      await foodsharing.forumThreadUrl(threadAmbassadorUser.id, null),
    );
    await acceptanceHelper.waitForActiveAPICalls();
    await page.locator(".overflow-menu button").first().click();
    const pinVisible = await page
      .getByRole("menuitem", { name: "Beitrag anheften" })
      .isVisible()
      .catch(() => false);
    const unpinVisible = await page
      .getByRole("menuitem", { name: "Nicht mehr anheften" })
      .isVisible()
      .catch(() => false);
    expect(pinVisible || unpinVisible).toBeFalsy();
  });

  test("Foodsaver sees no pin/unpin option for own thread", async ({
    page,
    acceptanceHelper,
  }) => {
    await acceptanceHelper.login(testData.foodsaver.email);
    await page.goto(
      await foodsharing.forumThreadUrl(threadUserAmbassador.id, null),
    );
    await acceptanceHelper.waitForActiveAPICalls();
    await page.locator(".overflow-menu button").first().click();
    // Should NOT see 'Beitrag anheften' or 'Nicht mehr anheften'
    const pinVisible = await page
      .getByRole("menuitem", { name: "Beitrag anheften" })
      .isVisible()
      .catch(() => false);
    const unpinVisible = await page
      .getByRole("menuitem", { name: "Nicht mehr anheften" })
      .isVisible()
      .catch(() => false);
    expect(pinVisible || unpinVisible).toBeFalsy();
  });

  test("New thread by ambassador will not be moderated", async ({
    page,
    acceptanceHelper,
  }) => {
    await acceptanceHelper.login(testData.ambassador.email);
    const title = "TestAmbassadorThreadTitle";
    await foodsharing.createForumThread({
      forumId: testData.testBezirk.id,
      title,
      body: "TestThreadPost",
      page,
    });
    await acceptanceHelper.waitForActiveAPICalls();
    await page.goto(`/region?bid=${testData.testBezirk.id}&sub=forum`);
    await acceptanceHelper.waitForPageBody();
    await expect(page.locator("body")).toContainText(title);
  });

  test("Can not see inactive threads", async ({ page, acceptanceHelper }) => {
    await acceptanceHelper.login(testData.foodsaver.email);
    const title = "TestThreadTitle";
    await foodsharing.createForumThread({
      forumId: testData.bigTestBezirk.id,
      title,
      body: "TestThreadPost",
      page,
    });
    await page.goto(`/region?bid=${testData.bigTestBezirk.id}&sub=forum`);
    await acceptanceHelper.waitForPageBody();
    await expect(page.locator(".forum_threads")).not.toContainText(title);
  });
});
