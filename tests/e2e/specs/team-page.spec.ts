import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";
import RegionIDs from "../helpers/constants/Region/RegionIDs";

test.describe("Team Page", () => {
  let boardMember: any;

  let administrationMember: any;

  let alumniMember: any;

  test.beforeEach(async () => {
    await foodsharing.createWorkingGroup('Vereinsvorstand', {
      id: RegionIDs.TEAM_BOARD_MEMBER
    })
    boardMember = await foodsharing.createFoodsaver();
    await foodsharing.addRegionMember(
      RegionIDs.TEAM_BOARD_MEMBER,
      boardMember.id,
    );

    await foodsharing.createWorkingGroup('Aktive (Überregional)', {
      id: RegionIDs.TEAM_ADMINISTRATION_MEMBER
    })
    administrationMember = await foodsharing.createFoodsaver();
    await foodsharing.addRegionMember(
      RegionIDs.TEAM_ADMINISTRATION_MEMBER,
      administrationMember.id,
    );

    await foodsharing.createWorkingGroup('Ehemalige (Vorstand und Orgateam)', {
      id: RegionIDs.TEAM_ALUMNI_MEMBER
    })
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
