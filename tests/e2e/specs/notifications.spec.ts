import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";
import { Database } from "../helpers/database";

test.describe("Notifications", () => {
  test.fixme("can view and interact with notifications", async ({
    page,
    acceptanceHelper,
  }) => {
    const region = await foodsharing.createRegion();
    const foodsaver = await foodsharing.createFoodsaver(null, {
      bezirk_id: region.id,
    });

    // Create some test notifications for the user
    await foodsharing.addBells([foodsaver], {
      name: "store_new_request",
      body: "Test notification body",
      vars: { test: "data" },
      attr: { href: "/test" },
      icon: "fa-bell",
      closeable: 1,
    });

    await foodsharing.addBells([foodsaver], {
      name: "banana_given",
      body: "You received a banana!",
      vars: { name: "TestUser" },
      attr: { href: "/profile/1" },
      icon: "fa-gift",
      closeable: 1,
    });

    // Login and navigate to home
    await acceptanceHelper.login(foodsaver.email);
    await page.goto("/");

    // Wait for page to load
    await acceptanceHelper.waitForPageBody();

    // Check that notification bell button exists with badge count
    const notificationButton = page.getByRole("button", {
      name: "Benachrichtigungen",
    });
    await expect(notificationButton).toBeVisible();

    // Should show badge with "2" notifications
    await expect(notificationButton).toContainText("2");

    // Click on the notification button to open dropdown
    await notificationButton.click();

    // Wait for notifications dropdown menu to be visible
    const notificationsList = page.locator(
      'ul.dropdown-menu.dropdown-menu-right.show[aria-labelledby*="BV_toggle_"]',
    );
    await notificationsList.waitFor({ state: "visible" });

    // Verify notifications are displayed
    await expect(notificationsList).toContainText("Test notification body");
    await expect(notificationsList).toContainText("You received a banana!");

    // Click on a notification link to mark it as read
    const firstNotification = notificationsList.getByRole("link", {
      name: /Test notification body/,
    });
    await firstNotification.click();

    // Wait for the action to complete
    await acceptanceHelper.waitForActiveAPICalls();

    // Verify in database that notification was marked as seen
    const bellId = await Database.grabFromDatabase("fs_bell", "id", {
      name: "store_new_request",
    });

    await expect(
      Database.seeInDatabase("fs_foodsaver_has_bell", {
        foodsaver_id: foodsaver.id,
        bell_id: bellId,
        seen: 1,
      }),
    ).resolves.toBeTruthy();
  });

  test.fixme("can delete notifications", async ({ page, acceptanceHelper }) => {
    const region = await foodsharing.createRegion();
    const foodsaver = await foodsharing.createFoodsaver(null, {
      bezirk_id: region.id,
    });

    // Create a closeable notification
    const bellId = await foodsharing.addBells([foodsaver], {
      name: "test_notification",
      body: "This notification can be deleted",
      vars: {},
      attr: { href: "/" },
      icon: "fa-info",
      closeable: 1,
    });

    await acceptanceHelper.login(foodsaver.email);
    await page.goto("/");
    await acceptanceHelper.waitForPageBody();

    // Open notifications dropdown
    const notificationButton = page.getByRole("button", {
      name: "Benachrichtigungen",
    });
    await notificationButton.click();
    const notificationsList = page.locator(
      'ul.dropdown-menu.dropdown-menu-right.show[aria-labelledby*="BV_toggle_"]',
    );
    await notificationsList.waitFor({ state: "visible" });

    // Verify notification exists
    await expect(notificationsList).toContainText(
      "This notification can be deleted",
    );

    // Click on the close/delete button (look for close link with # href)
    const deleteButton = notificationsList
      .getByRole("link")
      .filter({ hasText: "" })
      .first();

    await deleteButton.click();
    await acceptanceHelper.waitForActiveAPICalls();

    // Verify notification was deleted from database
    await expect(
      Database.seeInDatabase("fs_foodsaver_has_bell", {
        foodsaver_id: foodsaver.id,
        bell_id: bellId,
      }),
    ).resolves.toBeFalsy();
  });

  test.fixme("notifications show correct time", async ({
    page,
    acceptanceHelper,
  }) => {
    const region = await foodsharing.createRegion();
    const foodsaver = await foodsharing.createFoodsaver(null, {
      bezirk_id: region.id,
    });

    // Create a notification with specific time
    await foodsharing.addBells([foodsaver], {
      name: "test_notification_title",
      body: "test_notification_body",
      vars: {},
      attr: { href: "/" },
      icon: "fa-clock",
      time: new Date(Date.now() - 3600000)
        .toISOString()
        .slice(0, 19)
        .replace("T", " "), // 1 hour ago
    });

    await acceptanceHelper.login(foodsaver.email);
    await page.goto("/");
    await acceptanceHelper.waitForPageBody();

    // Open notifications
    const notificationButton = page.getByRole("button", {
      name: "Benachrichtigungen",
    });
    await notificationButton.click();
    await page.waitForTimeout(50);

    // Get the notifications dropdown menu specifically
    const notificationsList = page.locator(
      'ul.dropdown-menu.dropdown-menu-right.show[aria-labelledby*="BV_toggle_"]',
    );
    await notificationsList.waitFor({ state: "visible" });

    // Verify time is displayed (should show something like "vor X")
    const notificationWithTime = notificationsList.getByRole("link", {
      name: /seems to have worked/,
    });
    await expect(notificationWithTime).toContainText("vor");
  });

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
      name: "notification_1",
      body: "First notification",
      vars: {},
      attr: { href: "/" },
    });
    await foodsharing.addBells([foodsaver], {
      name: "notification_2",
      body: "Second notification",
      vars: {},
      attr: { href: "/" },
    });
    await foodsharing.addBells([foodsaver], {
      name: "notification_3",
      body: "Third notification",
      vars: {},
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

  test.fixme("can refresh and load more notifications", async ({
    page,
    acceptanceHelper,
  }) => {
    const region = await foodsharing.createRegion();
    const foodsaver = await foodsharing.createFoodsaver(null, {
      bezirk_id: region.id,
    });

    // Create several notifications
    for (let i = 1; i <= 5; i++) {
      await foodsharing.addBells([foodsaver], {
        name: `notification_${i}`,
        body: `Notification number ${i}`,
        vars: {},
        attr: { href: "/" },
      });
    }

    await acceptanceHelper.login(foodsaver.email);
    await page.goto("/");
    await acceptanceHelper.waitForPageBody();

    // Open notifications dropdown
    const notificationButton = page.getByRole("button", {
      name: "Benachrichtigungen",
    });
    await notificationButton.click();
    const notificationsList = page.locator(
      'ul.dropdown-menu.dropdown-menu-right.show[aria-labelledby*="BV_toggle_"]',
    );
    await notificationsList.waitFor({ state: "visible" });

    // Count initial notifications
    const notifications = notificationsList.getByRole("listitem");
    const initialCount = await notifications.count();
    expect(initialCount).toBeGreaterThanOrEqual(5);

    // Check for "Aktualisieren" (Refresh) menu item
    const refreshButton = page.getByRole("menuitem", {
      name: /Aktualisieren/,
    });
    await expect(refreshButton).toBeVisible();

    // Generate more notifications to test refresh
    for (let i = 6; i <= 10; i++) {
      await foodsharing.addBells([foodsaver], {
        name: `notification_${i}`,
        body: `Notification number ${i}`,
      });
    }

    // Click refresh button
    await refreshButton.click();
    await acceptanceHelper.waitForActiveAPICalls();

    // Verify new notifications are loaded
    const newCount = await notifications.count();
    expect(newCount).toBeGreaterThan(initialCount);
  });
});
