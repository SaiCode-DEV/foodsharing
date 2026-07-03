import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";

// Berlin wall-clock date string for a day far enough in the future, at noon.
function germanNoonInDays(days: number): string {
  const day = new Date(Date.now() + days * 86400000).toLocaleDateString("en-CA", {
    timeZone: "Europe/Berlin",
  });
  return `${day} 12:00:00`;
}

test.describe("Dashboard", () => {
  // Regression for !5165: the event widget built its dates from the removed
  // start_ts/end_ts query fields and rendered "Invalid Date". This covers the
  // whole path (database row, gateway query, dashboard payload, widget), so a
  // query change cannot break the widget unnoticed again.
  test("event widget renders a real event date", async ({
    page,
    acceptanceHelper,
  }) => {
    const region = await foodsharing.createRegion();
    const author = await foodsharing.createFoodsaver(null, {
      bezirk_id: region.id,
    });
    const user = await foodsharing.createFoodsaver(null, {
      bezirk_id: region.id,
    });
    const event = await foodsharing.createEvents(region.id, author.id, {
      name: "DashboardDateProbe",
      start: germanNoonInDays(2),
      end: germanNoonInDays(2).replace("12:00:00", "13:00:00"),
    });
    await foodsharing.addEventInvitation(event.id, user.id, { status: 1 });

    await acceptanceHelper.login(user.email);
    await page.goto("/dashboard");
    await expect(page.getByText("DashboardDateProbe")).toBeVisible();
    await expect(page.locator("body")).not.toContainText("Invalid Date");
    await expect(page.getByText("12:00").first()).toBeVisible();
  });
});
