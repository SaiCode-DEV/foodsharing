import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";

// #2819: navigating between stores happens client side, so the store page state of the
// previously opened store is still around while the new one loads. A store you manage
// followed by one you do not must not make the client ask for the manager-only data.
test.describe("Switching between stores", () => {
  test("does not request manager data for a store you do not manage", async ({
    page,
    acceptanceHelper,
  }) => {
    const region = await foodsharing.createRegion();
    // mayEditStore needs the global store manager role on top of the team entry
    const user = await foodsharing.createStoreCoordinator(null, {
      bezirk_id: region.id,
    });

    const managed = await foodsharing.createStore(region.id, null, null, {
      bezirk_id: region.id,
      name: "ManagedStore",
      betrieb_status_id: 5,
    });
    const notManaged = await foodsharing.createStore(region.id, null, null, {
      bezirk_id: region.id,
      name: "PlainMemberStore",
      betrieb_status_id: 5,
    });
    await foodsharing.addStoreTeam(managed.id, user.id, true);
    await foodsharing.addStoreTeam(notManaged.id, user.id, false);

    const managerRequests = /\/stores\/(\d+)\/(requests|invitations)$/;
    const asked: string[] = [];
    page.on("request", (req) => {
      const match = req.url().match(managerRequests);
      if (match) asked.push(`${match[1]}/${match[2]}`);
    });

    await acceptanceHelper.login(user.email);
    await page.goto(`/store/${managed.id}`);
    await acceptanceHelper.waitForActiveAPICalls();
    // the store we manage loads the team panel, which is what fills the state
    await expect.poll(() => asked.length).toBeGreaterThan(0);

    asked.length = 0;

    // the store menu uses FsLink :to, so this stays on the same document
    await page
      .getByRole("button", { name: /Betriebe/ })
      .first()
      .click();
    // stores you are only a member of sit in a collapsed group
    await page
      .getByRole("menuitem", { name: "Deine Betriebe" })
      .first()
      .click();
    await page
      .getByRole("menuitem", { name: "PlainMemberStore" })
      .first()
      .click();

    await expect(page).toHaveURL(new RegExp(`/store/${notManaged.id}`));
    await acceptanceHelper.waitForActiveAPICalls();
    await expect(page.locator(".store-team")).toBeVisible();

    expect(asked).toEqual([]);
  });
});
