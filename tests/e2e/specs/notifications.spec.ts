import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";
import { Database } from "../helpers/database";

// Bell entry behaviour, converted from the NavNotificationsEntry mocha test (#2767).
test.describe("Notification bell entries", () => {
  let user: Awaited<ReturnType<typeof foodsharing.createFoodsaver>>;
  let bellId: number;

  test.beforeEach(async ({ page, acceptanceHelper, isMobile }) => {
    // the bell dropdown only exists in the desktop side navigation
    test.skip(isMobile, "the notification dropdown only exists on desktop");
    const region = await foodsharing.createRegion();
    user = await foodsharing.createFoodsaver(null, { bezirk_id: region.id });
    bellId = await foodsharing.addBells([user], {
      name: "store_new_request_title",
      body: "store_new_request",
      vars: { name: "Teststore" },
      attr: { href: "/?page=dashboard" },
      icon: "fas fa-bell",
      closeable: 1,
    });
    await acceptanceHelper.login(user.email);
    await page.goto("/dashboard");
    await page.getByRole("button", { name: "Benachrichtigungen" }).click();
  });

  test.afterEach(async ({ acceptanceHelper }) => {
    await acceptanceHelper.logMeOut();
  });

  test("renders a bell entry with text, avatar, time and mark-read button", async ({
    page,
  }) => {
    const entry = page.locator("a.dropdown-item", {
      hasText: "Neue Teamanfrage",
    });
    await expect(entry).toBeVisible();
    await expect(entry).toContainText("Anfrage für Teststore");
    await expect(entry.locator(".avatar-light")).toBeVisible();
    await expect(entry.locator(".time")).toBeVisible();
    // the mark-read button only shows while hovering the entry
    await entry.hover();
    await expect(entry.locator(".mark-read-button")).toBeVisible();
  });

  test("marks the bell as read when the entry is clicked", async ({
    page,
    acceptanceHelper,
  }) => {
    const entry = page.locator("a.dropdown-item", {
      hasText: "Neue Teamanfrage",
    });
    await entry.hover();
    await entry.locator(".mark-read-button").click();
    // the eye icon flips once the read state is stored
    await expect(entry.locator(".fa-eye-slash")).toBeVisible();
    await acceptanceHelper.waitForActiveAPICalls();

    await expect(
      Database.seeInDatabase("fs_foodsaver_has_bell", {
        foodsaver_id: user.id,
        bell_id: bellId,
        seen: 1,
      }),
    ).resolves.toBeTruthy();
  });

  test("closes the bell via the avatar click", async ({
    page,
    acceptanceHelper,
  }) => {
    const entry = page.locator("a.dropdown-item", {
      hasText: "Neue Teamanfrage",
    });
    await entry.locator(".avatar-light").click();
    await acceptanceHelper.waitForActiveAPICalls();

    await expect(entry).toHaveCount(0);
    await expect(
      page.getByText("Du hast derzeit keine Benachrichtigungen."),
    ).toBeVisible();
  });
});

test.describe("Notifications", () => {
  test("unread notifications badge shows correct count", async ({
    page,
    acceptanceHelper,
  }) => {
    const region = await foodsharing.createRegion();
    const foodsaver = await foodsharing.createFoodsaver(null, {
      bezirk_id: region.id,
    });

    // Create 3 unread notifications
    await foodsharing.addBells([foodsaver], {
      name: "store_new_title",
      body: "store_new",
      vars: { name: "Store 1" },
      attr: { href: "/" },
    });
    await foodsharing.addBells([foodsaver], {
      name: "store_new_title",
      body: "store_new",
      vars: { name: "Store 2" },
      attr: { href: "/" },
    });
    await foodsharing.addBells([foodsaver], {
      name: "store_new_title",
      body: "store_new",
      vars: { name: "Store 3" },
      attr: { href: "/" },
    });

    await acceptanceHelper.login(foodsaver.email);
    await page.goto("/");
    await acceptanceHelper.waitForPageBody();

    // Check for notification button with badge count showing "3"
    const notificationButton = page.getByRole("button", {
      name: "Benachrichtigungen",
    });
    await expect(notificationButton).toContainText("3");
  });

  // reminders for behaviour still without coverage (kept from the old mocha fixmes)
  test.fixme("shows the relative time on a bell entry", async () => {});
  test.fixme("loads more bells on demand", async () => {});
});
