import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";

// #2843: a conversation without messages is only in the client's store because it
// was opened directly. It has no place in the list, but the open one has to stay.
test("lists conversations with messages, plus the open one", async ({
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
    {
      body: "Hallo Team",
    },
  );

  await acceptanceHelper.login(coordinator.email);

  // full list: the conversation with a message is there
  await page.goto("/msg");
  await acceptanceHelper.waitForPageBody();
  await acceptanceHelper.waitForActiveAPICalls();
  await expect(page.locator(".vac-room-item")).toHaveCount(1);
  await expect(page.locator(".vac-room-item").first()).toContainText(
    "Hallo Team",
  );

  // the empty standby chat can still be opened directly and stays visible
  await page.goto(`/msg?cid=${store.springer_conversation_id}`);
  await acceptanceHelper.waitForPageBody();
  await acceptanceHelper.waitForActiveAPICalls();
  await expect(page.locator(".vac-room-item")).toHaveCount(2);
  await expect(page.locator(".vac-room-footer")).toBeVisible();
});
