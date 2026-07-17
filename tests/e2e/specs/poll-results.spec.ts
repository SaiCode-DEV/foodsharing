import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";
import { Database } from "../helpers/database";
import VotingType from "../helpers/constants/Voting/VotingType";

// #2563: the sum column must sort numerically, not by the signed label
// ("+9" sorts above "+10" as a string).
test.describe("Poll results", () => {
  test("sorts the sum column numerically", async ({
    page,
    acceptanceHelper,
    isMobile,
  }) => {
    // the sum column only renders on desktop widths
    test.skip(isMobile, "the sum column is hidden on mobile viewports");

    const region = await foodsharing.createRegion();
    const user = await foodsharing.createFoodsaver(null, {
      bezirk_id: region.id,
    });
    const poll = await foodsharing.createPoll(region.id, user.id, {
      type: VotingType.THUMB_VOTING,
      start: "2026-01-01 00:00:00",
      end: "2026-01-08 00:00:00",
      votes: 10,
    });
    // sums 10, 9, 0: as signed strings "+9" sorts above "+10" and "0" misplaces
    const sums = [
      { text: "Zehn", up: 10 },
      { text: "Neun", up: 9 },
      { text: "Null", up: 0 },
    ];
    for (const [i, o] of sums.entries()) {
      await Database.addToDatabase("fs_poll_has_options", {
        poll_id: poll.id,
        option: i,
        option_text: o.text,
      });
      for (const value of [1, 0, -1]) {
        await Database.addToDatabase("fs_poll_option_has_value", {
          poll_id: poll.id,
          option: i,
          value,
          votes: value === 1 ? o.up : 0,
        });
      }
    }

    await acceptanceHelper.login(user.email);
    await page.goto(`/poll?id=${poll.id}`);
    const rows = page.locator("table tbody tr");
    await expect(rows).toHaveCount(3);

    // sort ascending by sum: Null (0), Neun (9), Zehn (10)
    await page.locator("th", { hasText: "Summe" }).click();
    await expect(rows.nth(0)).toContainText("Null");
    await expect(rows.nth(1)).toContainText("Neun");
    await expect(rows.nth(2)).toContainText("Zehn");
  });
});
