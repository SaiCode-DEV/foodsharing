import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";

// #2803: scrolling over a paginated widget flipped it to the next page, because
// a trackpad gesture carries a small sideways component.

function germanNoonInDays(days: number): string {
  const day = new Date(Date.now() + days * 86400000).toLocaleDateString(
    "en-CA",
    { timeZone: "Europe/Berlin" },
  );
  return `${day} 12:00:00`;
}

test.describe("Paginated widget", () => {
  test("vertical scrolling does not change the page", async ({
    page,
    acceptanceHelper,
  }) => {
    const region = await foodsharing.createRegion();
    const user = await foodsharing.createFoodsaver(null, {
      bezirk_id: region.id,
    });
    await foodsharing.addRegionMember(region.id, user.id);
    const store = await foodsharing.createStore(region.id, null, null, {
      bezirk_id: region.id,
    });
    await foodsharing.addStoreTeam(store.id, user.id, true);
    // page size is 5, so six slots make two pages
    for (let i = 1; i <= 6; i++) {
      await foodsharing.addPickup(store.id, {
        time: germanNoonInDays(i),
        fetchercount: 2,
      });
    }

    await acceptanceHelper.login(user.email);
    await page.goto("/dashboard");
    await acceptanceHelper.waitForActiveAPICalls();

    const widget = page
      .locator(".pickup-options .paginated-content:visible")
      .first();
    await widget.waitFor({ timeout: 20000 });
    const activePage = page
      .locator(".pickup-options .pagination:visible .page-item.active")
      .first();
    await expect(activePage).toContainText("1");

    // a downward gesture with the sideways jitter a trackpad produces
    await widget.hover();
    await page.mouse.wheel(2, 1);
    await page.mouse.wheel(3, 2);
    await page.waitForTimeout(500);
    await expect(activePage).toContainText("1");

    // a clear sideways gesture still pages
    await page.mouse.wheel(150, 0);
    await page.waitForTimeout(500);
    await expect(activePage).toContainText("2");

    // A trackpad delivers a swipe as many small deltas in quick succession, that
    // has to page as well. Dispatching them directly keeps them inside one
    // gesture; driving the mouse would be too slow for that.
    await page.waitForTimeout(2200);
    await widget.evaluate((el: HTMLElement) => {
      for (let i = 0; i < 12; i++) {
        el.dispatchEvent(
          new WheelEvent("wheel", {
            deltaX: -6,
            deltaY: 0,
            bubbles: true,
            cancelable: true,
          }),
        );
      }
    });
    await page.waitForTimeout(500);
    await expect(activePage).toContainText("1");
  });
});
