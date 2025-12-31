import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";
import RegionIDs from "../helpers/constants/Region/RegionIDs";

test.describe("Team Page", () => {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  let boardMember: any;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  let administrationMember: any;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  let alumniMember: any;

  test.beforeEach(async () => {
    boardMember = await foodsharing.createFoodsaver();
    await foodsharing.addRegionMember(
      RegionIDs.TEAM_BOARD_MEMBER,
      boardMember.id,
    );

    administrationMember = await foodsharing.createFoodsaver();
    await foodsharing.addRegionMember(
      RegionIDs.TEAM_ADMINISTRATION_MEMBER,
      administrationMember.id,
    );

    alumniMember = await foodsharing.createFoodsaver();
    await foodsharing.addRegionMember(
      RegionIDs.TEAM_ALUMNI_MEMBER,
      alumniMember.id,
    );
  });

  test("lists board, administration, and alumni members", async ({ page }) => {
    await page.goto("/team");

    await expect(page.locator("body")).toContainText(boardMember.name);
    await expect(page.locator("body")).toContainText(administrationMember.name);
    await expect(page.locator("body")).toContainText(alumniMember.name);
  });
});
