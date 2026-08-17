import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";

// Pickup times must render in the timezone of the store's region, never in the
// browser's timezone (#775, #2762). Emulating UTC reproduces the reported scenario
// (privacy browsers report UTC instead of the real timezone).
test.use({ timezoneId: "UTC" });

// Berlin wall-clock date string for a day far enough in the future, at noon. Stored
// datetimes are German wall-clock time, so this is what goes into the database.
function germanNoonInDays(days: number): string {
  const day = new Date(Date.now() + days * 86400000).toLocaleDateString(
    "en-CA",
    {
      timeZone: "Europe/Berlin",
    },
  );
  return `${day} 12:00:00`;
}

async function createStoreWithNoonPickup() {
  const region = await foodsharing.createRegion();
  const coordinator = await foodsharing.createFoodsaver(null, {
    bezirk_id: region.id,
  });
  const store = await foodsharing.createStore(region.id, null, null, {
    bezirk_id: region.id,
  });
  await foodsharing.addStoreTeam(store.id, coordinator.id, true);
  await foodsharing.addPickup(store.id, {
    time: germanNoonInDays(2),
    fetchercount: 2,
  });
  return { region, coordinator, store };
}

test.describe("Region timezones", () => {
  test("a CET store shows German pickup time in a UTC browser", async ({
    page,
    acceptanceHelper,
  }) => {
    const { coordinator, store } = await createStoreWithNoonPickup();

    await acceptanceHelper.login(coordinator.email);
    await page.goto(`/store/${store.id}`);
    const pickupDate = page.locator(".pickup-date").first();
    // 12:00 German time must not appear as 10:00 (UTC, summer) or 11:00 (winter).
    await expect(pickupDate).toContainText("12:00");
    // a UTC viewer reads a different wall clock, so the own-time hint appears
    await expect(pickupDate.locator(".viewer-time-hint")).toBeVisible();
  });

  test("the dashboard event widget shows German time in a UTC browser", async ({
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
      name: "DashboardTimezoneEvent",
      start: germanNoonInDays(2),
      end: germanNoonInDays(2).replace("12:00:00", "13:00:00"),
    });
    await foodsharing.addEventInvitation(event.id, user.id, { status: 1 });

    await acceptanceHelper.login(user.email);
    await page.goto("/dashboard");
    await expect(page.getByText("DashboardTimezoneEvent")).toBeVisible();
    await expect(page.locator("body")).not.toContainText("Invalid Date");
    // 12:00 German wall clock must not shift to 10:00 (UTC parse) or 14:00 (double shift).
    await expect(page.getByText("12:00").first()).toBeVisible();
    // the own-time hint mirrors the whole span, not just the start time (#2762)
    const hint = page.locator(".viewer-time-hint").first();
    await expect(hint).toBeVisible();
    await expect(hint).toHaveText(/\d{1,2}:\d{2}.*\d{1,2}:\d{2}/);
  });

  test("a store in a Riga region shows Riga pickup time", async ({
    page,
    acceptanceHelper,
  }) => {
    const latvia = await foodsharing.createRegion(null, {
      timezone: "Europe/Riga",
    });
    const coordinator = await foodsharing.createFoodsaver(null, {
      bezirk_id: latvia.id,
    });
    const store = await foodsharing.createStore(latvia.id, null, null, {
      bezirk_id: latvia.id,
    });
    await foodsharing.addStoreTeam(store.id, coordinator.id, true);
    await foodsharing.addPickup(store.id, {
      time: germanNoonInDays(2),
      fetchercount: 2,
    });

    await acceptanceHelper.login(coordinator.email);
    await page.goto(`/store/${store.id}`);
    const pickupDate = page.locator(".pickup-date").first();
    // Stored 12:00 German wall clock is 13:00 in Riga (EET/EEST is Berlin+1 all year).
    await expect(pickupDate).toContainText("13:00");
  });
});

test.describe("Region timezones for a viewer in the store's timezone", () => {
  test.use({ timezoneId: "Europe/Berlin" });

  test("no viewer-time hint when the browser is in the store's timezone", async ({
    page,
    acceptanceHelper,
  }) => {
    const { coordinator, store } = await createStoreWithNoonPickup();

    await acceptanceHelper.login(coordinator.email);
    await page.goto(`/store/${store.id}`);
    const pickupDate = page.locator(".pickup-date").first();
    await expect(pickupDate).toContainText("12:00");
    await expect(pickupDate.locator(".viewer-time-hint")).toHaveCount(0);
  });
});
