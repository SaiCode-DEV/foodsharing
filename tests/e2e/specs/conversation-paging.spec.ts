import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";

// #2845: the conversation list stopped after the first page. Two reasons, both
// visible in the requests it makes: the page was fetched twice (the header and
// the messages page both ask for it), and the offset was derived from the number
// of conversations in the store, which also counts ones opened by id.
const PAGE_SIZE = 20;
const TOTAL = 25;

test("pages through the conversation list without skipping", async ({
  page,
  acceptanceHelper,
}) => {
  const region = await foodsharing.createRegion();
  const me = await foodsharing.createFoodsaver(null, { bezirk_id: region.id });
  await foodsharing.addRegionMember(region.id, me.id);
  const partner = await foodsharing.createFoodsaver(null, {
    bezirk_id: region.id,
  });

  // distinct, strictly decreasing timestamps, otherwise the server order is not
  // stable and offset paging would skip entries for that reason alone
  for (let i = 0; i < TOTAL; i++) {
    const when = `2026-08-10 ${String(23 - (i % 24)).padStart(2, "0")}:${String(i).padStart(2, "0")}:00`;
    const conv = await foodsharing.createConversation([me.id, partner.id], {
      last: when,
    });
    await foodsharing.addConversationMessage(partner.id, conv.id, {
      body: `Nachricht ${i}`,
      time: when,
    });
  }

  const offsets: string[] = [];
  page.on("response", (r) => {
    const m = r.url().match(/conversations\?.*offset=(\d+)/);
    if (m) offsets.push(m[1]);
  });

  await acceptanceHelper.login(me.email);
  await page.goto("/msg");
  await acceptanceHelper.waitForPageBody();
  await acceptanceHelper.waitForActiveAPICalls();
  await expect(page.locator(".vac-room-item")).toHaveCount(PAGE_SIZE);

  // scrolling asks for the next page, which has to continue where the first ended
  await page.locator(".vac-room-item").last().scrollIntoViewIfNeeded();
  await acceptanceHelper.waitForActiveAPICalls();
  await page.waitForTimeout(2000);

  // no offset beyond one page: that is what happened when the same page was
  // fetched twice and both fetches were counted
  expect(offsets.filter((o) => Number(o) > PAGE_SIZE)).toEqual([]);
  await expect(page.locator(".vac-room-item")).toHaveCount(TOTAL);
});
