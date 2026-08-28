import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";

// #2855: linking a post warned that it could not be found whenever a post with
// a higher id came earlier in the list, which is ordered by time.
test("linking a post that exists shows no warning", async ({
  page,
  acceptanceHelper,
}) => {
  const region = await foodsharing.createRegion();
  const author = await foodsharing.createFoodsaver(null, {
    bezirk_id: region.id,
  });
  const thread = await foodsharing.seedForumThread(
    region.id,
    author.id,
    false,
    {
      time: "2026-08-01 10:00:00",
    },
  );

  // The second post is older than the third, so the list order by time differs
  // from the order by id.
  const linked = await foodsharing.addForumThreadPost(thread.id, author.id, {
    body: "the linked post",
    time: "2026-08-01 12:00:00",
  });
  await foodsharing.addForumThreadPost(thread.id, author.id, {
    body: "a post written later but shown earlier",
    time: "2026-08-01 11:00:00",
  });

  await acceptanceHelper.login(author.email);
  await page.goto(
    `/region?bid=${region.id}&sub=forum&tid=${thread.id}&pid=${linked.id}`,
  );

  await expect(page.locator(`#post-${linked.id}`)).toBeVisible();

  // The warning is pulsed after the thread has scrolled to the post, so give it
  // the time to appear before expecting it to be absent.
  await page.waitForTimeout(3000);
  await expect(
    page.locator(".vue-notification-wrapper", {
      hasText: "verlinkte Beitrag",
    }),
  ).toHaveCount(0);
});
