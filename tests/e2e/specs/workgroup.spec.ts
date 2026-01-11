import { test, expect, AcceptanceHelper } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";
import { Database } from "../helpers/database";
import RegionIDs from "../helpers/constants/Region/RegionIDs";
import ApplyType from "../helpers/constants/Region/ApplyType";
import { Urls } from "../helpers/urls";
import { faker } from "@faker-js/faker";

test.describe("WorkGroup", () => {
  /* roles that refer to testGroup */
  let parentRegion: Awaited<ReturnType<typeof foodsharing.createRegion>>;
  let testGroup: Awaited<ReturnType<typeof foodsharing.createWorkingGroup>>;
  let globalTestGroup: Awaited<ReturnType<typeof foodsharing.createWorkingGroup>>;

  let regionMember: Awaited<ReturnType<typeof foodsharing.createFoodsaver>>;
  let groupAdmin: Awaited<ReturnType<typeof foodsharing.createFoodsaver>>;

  /* group that can be applied for */
  let testGroupApply: Awaited<ReturnType<typeof foodsharing.createWorkingGroup>>;
  /* admin of testGroupApply */
  let groupApplyAdmin: Awaited<ReturnType<typeof foodsharing.createFoodsaver>>;
  let foodsharer: Awaited<ReturnType<typeof foodsharing.createFoodsharer>>;
  let userOrga: Awaited<ReturnType<typeof foodsharing.createOrga>>;

  test.beforeEach(async () => {
    /* Minimal global setup used by most tests: global groups and simple users */
    await foodsharing.createWorkingGroup(
      `${faker.lorem.word()}-global-test-group`,
      {},
    );
    globalTestGroup = await foodsharing.createWorkingGroup(
      `global-test-group-${faker.lorem.word()}`,
      {
        apply_type: ApplyType.OPEN,
        parent_id: RegionIDs.GLOBAL_WORKING_GROUPS,
      },
    );

    // Create users that are used across many tests
    foodsharer = await foodsharing.createFoodsharer();
    regionMember = await foodsharing.createFoodsaver();
    // ensure region membership in global groups
    await foodsharing.addRegionMember(
      RegionIDs.GLOBAL_WORKING_GROUPS,
      regionMember.id,
    );
  });

  test.describe("with testGroup", () => {
    test.beforeEach(async () => {
      parentRegion = await foodsharing.createRegion(null, {}, false);
      testGroup = await foodsharing.createWorkingGroup(`test-group-${faker.lorem.word()}`, {
        apply_type: ApplyType.OPEN,
        parent_id: parentRegion.id,
      });

      // Create users tied to testGroup
      regionMember = await foodsharing.createFoodsaver(null, {
        bezirk_id: testGroup.id,
      });
      await foodsharing.addRegionMember(testGroup.id, regionMember.id);

      groupAdmin = await foodsharing.createFoodsaver(null, {
        bezirk_id: testGroup.id,
      });
      await foodsharing.addRegionMember(testGroup.id, groupAdmin.id);
      await foodsharing.addRegionAdmin(testGroup.id, groupAdmin.id);

      userOrga = await foodsharing.createOrga();
    });

    test("regionMember cannot access work group", async ({
      page,
      acceptanceHelper,
    }) => {
      await acceptanceHelper.login(regionMember.email);
      await page.goto(Urls.groupEditUrl(testGroup.id));
      await expect(page).toHaveURL(/dashboard/);
      await expect(page.locator("body")).not.toContainText("Bewerbungen");
    });

    test("regionMember cannot edit work group", async ({
      page,
      acceptanceHelper,
    }) => {
      await acceptanceHelper.login(regionMember.email);
      await page.goto(Urls.groupEditUrl(testGroup.id));
      await expect(page).toHaveURL(/dashboard/);
      await expect(page.locator("body")).not.toContainText("Bewerbungen");
    });

    test.describe("can edit work group", () => {
      test("groupAdmin can edit work group", async ({
        page,
        acceptanceHelper,
      }) => {
        await acceptanceHelper.login(groupAdmin.email);
        await page.goto(Urls.groupEditUrl(testGroup.id));
        await expect(page.locator("body")).toContainText(
          `${testGroup.name} bearbeiten`,
        );
      });

      test("userOrga can edit work group", async ({ page, acceptanceHelper }) => {
        await acceptanceHelper.login(userOrga.email);
        await page.goto(Urls.groupEditUrl(testGroup.id));
        await expect(page.locator("body")).toContainText(
          `${testGroup.name} bearbeiten`,
        );
      });
    });
  });

  /**
   * It is actually not really defined if foodsharer should be able to participate in groups or not.
   * They don't get the menu item but they can use groups.
   */
  test.describe("can see global groups", () => {
    test("regionMember can see global groups", async ({
      page,
      acceptanceHelper,
    }) => {
      await acceptanceHelper.login(regionMember.email);
      await page.goto(Urls.groupListUrl());
      await expect(page.locator("body")).toContainText(globalTestGroup.name);
    });

    test("foodsharer can see global groups", async ({
      page,
      acceptanceHelper,
    }) => {
      await acceptanceHelper.login(foodsharer.email);
      await page.goto(Urls.groupListUrl());
      await expect(page.locator("body")).toContainText(globalTestGroup.name);
    });
  });

  test("RegionMember can join global group", async ({
    page,
    acceptanceHelper,
  }) => {
    await acceptanceHelper.login(regionMember.email);
    await page.goto(Urls.groupListUrl());
    await page.click(`.list-group:has-text("${globalTestGroup.name}")`);
    await page.click("text=Dieser Arbeitsgruppe beitreten");
    await page.waitForSelector('h5:has-text("Pinnwand")');
    await page.goto(Urls.forumUrl(globalTestGroup.id));
    await expect(page.locator("body")).toContainText(globalTestGroup.name);
    await expect(page.locator("body")).toContainText(
      "Noch keine Themen gepostet",
    );
  });

  

  test.describe("with testGroupApply", () => {
    test.beforeEach(async () => {
      testGroupApply = await foodsharing.createWorkingGroup(
        `apply-group-${faker.lorem.word()}`,
        {
          apply_type: ApplyType.EVERYBODY,
        },
      );

      // Use the global `regionMember` created in the outer beforeEach as the applicant
      // (do NOT add them as member of `testGroupApply`, otherwise the apply button won't show)

      // Admin for the apply group
      groupApplyAdmin = await foodsharing.createFoodsaver(null, {
        bezirk_id: testGroupApply.id,
      });
      await foodsharing.addRegionMember(
        RegionIDs.GLOBAL_WORKING_GROUPS,
        groupApplyAdmin.id,
      );
      await foodsharing.addRegionMember(testGroupApply.id, groupApplyAdmin.id);
      await foodsharing.addRegionAdmin(testGroupApply.id, groupApplyAdmin.id);
    });

    test("can apply for work group", async ({
      page,
      acceptanceHelper,
      browser,
    }) => {

      await acceptanceHelper.login(regionMember.email);
      await page.goto(Urls.groupListUrl());
      await page.click(`.list-group:has-text("${testGroupApply.name}")`);
      await page.waitForSelector("text=Arbeitsgruppe bewerben");
      await page.click("text=Für diese Arbeitsgruppe bewerben");
      await page.waitForSelector("#input-motivation");
      await page.fill("#input-motivation", "My Motivation");
      await page.fill("#input-ability", "My Skillz");
      await page.fill("#input-experience", "My Experience");
      await page.selectOption("#input-time", "1–2 Stunden");
      await page.click("text=Senden");
      await page.waitForSelector("text=Erfolgreich abgeschlossen");

      // Verify database entry
      expect(
        await Database.seeInDatabase("fs_foodsaver_has_bezirk", {
          foodsaver_id: regionMember.id,
          bezirk_id: testGroupApply.id,
        }),
      ).toBeTruthy();

      // Admin checks and accepts application
      const adminContext = await browser.newContext();
      const adminPage = await adminContext.newPage();
      const adminHelper = new AcceptanceHelper(adminPage);

      await adminHelper.login(groupApplyAdmin.email);
      await adminPage.goto(Urls.forumUrl(testGroupApply.id));
      await adminPage.waitForSelector("text=Bewerbungen (1)");
      await adminPage.click("text=Bewerbungen");
      await adminPage.waitForSelector(`text=${regionMember.name}`);
      await adminPage.click(`text=${regionMember.name}`);
      await adminPage.waitForSelector("text=Bewerbung annehmen");
      await adminPage.locator('#vmenu a:has-text("Ja")').click();
      await adminHelper.waitForActiveAPICalls();
      await adminContext.close();

      // Original user checks access
      await acceptanceHelper.logMeOut();
      await acceptanceHelper.login(regionMember.email);
      await page.goto(Urls.forumUrl(testGroupApply.id));
      // Wait for forum page to load - check for the "no topics" message
      await expect(page.locator("text=Noch keine Themen gepostet")).toBeVisible();
    });
  });
});
