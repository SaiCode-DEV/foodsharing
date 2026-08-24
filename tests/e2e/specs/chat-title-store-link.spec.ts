import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";

// #2834: the title rendered a plain <a> with the router's :to attribute, which
// leaves it without an href and therefore not clickable.
test("opens the store from the title of a team chat", async ({
  page,
  acceptanceHelper,
}) => {
  const region = await foodsharing.createRegion();
  const coordinator = await foodsharing.createStoreCoordinator(null, {
    bezirk_id: region.id,
  });
  await foodsharing.addRegionMember(region.id, coordinator.id);
  const store = await foodsharing.createStore(region.id);
  await foodsharing.addStoreTeam(store.id, coordinator.id, true);
  await foodsharing.addConversationMessage(
    coordinator.id,
    store.team_conversation_id,
    { body: "Hallo Team" },
  );

  await acceptanceHelper.login(coordinator.email);
  await page.goto(`/msg?cid=${store.team_conversation_id}`);
  await acceptanceHelper.waitForPageBody();

  await acceptanceHelper.waitForActiveAPICalls();

  const title = page.locator("#header a").first();
  await expect(title).toHaveAttribute("href", `/store/${store.id}`);

  // the message list keeps growing while messages load, so the header never
  // settles long enough for a positional click
  await title.dispatchEvent("click");
  await expect(page).toHaveURL(new RegExp(`/store/${store.id}`), {
    timeout: 15000,
  });
});
