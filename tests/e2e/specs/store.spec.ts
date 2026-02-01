import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";
import { Database } from "../helpers/database";
import MembershipStatus from "../helpers/constants/Store/MembershipStatus";

test.describe("Store", () => {
  let region: Awaited<ReturnType<typeof foodsharing.createRegion>>;
  let store: Awaited<ReturnType<typeof foodsharing.createStore>>;
  let foodsharer: Awaited<ReturnType<typeof foodsharing.createFoodsharer>>;
  let foodsaver: Awaited<ReturnType<typeof foodsharing.createFoodsaver>>;
  let foodsaverNoRegion: Awaited<
    ReturnType<typeof foodsharing.createFoodsaver>
  >;
  let foodsaverOnJumperList: Awaited<
    ReturnType<typeof foodsharing.createFoodsaver>
  >;
  let foodsaverDifferentRegion: Awaited<
    ReturnType<typeof foodsharing.createFoodsaver>
  >;
  let storeManager: Awaited<
    ReturnType<typeof foodsharing.createStoreCoordinator>
  >;
  let foodsaverWithStoreManagerQuiz: Awaited<
    ReturnType<typeof foodsharing.createStoreCoordinator>
  >;

  async function openOverflow(page: any, targetId: number) {
    await page.click(`#user-${targetId} .overflow-menu`);
    await page.waitForSelector(".dropdown-menu.show");
  }

  test.beforeEach(async () => {
    region = await foodsharing.createRegion();
    const extraParams = { bezirk_id: region.id };

    foodsharer = await foodsharing.createFoodsharer();
    foodsaver = await foodsharing.createFoodsaver(null, extraParams);
    foodsaverNoRegion = await foodsharing.createFoodsaver();
    foodsaverOnJumperList = await foodsharing.createFoodsaver(
      null,
      extraParams,
    );
    storeManager = await foodsharing.createStoreCoordinator(null, extraParams);
    foodsaverWithStoreManagerQuiz = await foodsharing.createStoreCoordinator(
      null,
      extraParams,
    );

    store = await foodsharing.createStore(region.id, null, null, {
      betrieb_status_id: 1,
    });

    await foodsharing.addRegionMember(region.id, foodsaver.id);
    await foodsharing.addRegionMember(region.id, foodsaverOnJumperList.id);
    await foodsharing.addRegionMember(region.id, storeManager.id);
    await foodsharing.addRegionMember(
      region.id,
      foodsaverWithStoreManagerQuiz.id,
    );

    await foodsharing.addStoreTeam(store.id, storeManager.id, true);
    await foodsharing.addStoreTeam(store.id, foodsaver.id);
    await foodsharing.addStoreTeam(
      store.id,
      foodsaverOnJumperList.id,
      false,
      true,
      true,
    );

    // Edge case: user from other region
    const differentRegion = (await foodsharing.createRegion()).id;
    foodsaverDifferentRegion = await foodsharing.createFoodsaver(null, {
      bezirk_id: differentRegion,
    });
    await foodsharing.addRegionMember(
      differentRegion,
      foodsaverDifferentRegion.id,
    );
  });

  test("StoreManager and Foodsaver can see store on dashboard", async ({
    page,
    acceptanceHelper,
  }) => {
    // StoreManager
    await acceptanceHelper.login(storeManager.email);
    await expect(
      Database.seeInDatabase("fs_betrieb_team", {
        betrieb_id: store.id,
        foodsaver_id: storeManager.id,
        active: 1,
        verantwortlich: 1,
      }),
    ).resolves.toBeTruthy();
    await expect(
      page.getByRole("heading", { name: "Deine Betriebsverantwortungen" }),
    ).toBeVisible();
    await expect(page.getByRole("link", { name: store.name })).toBeVisible();

    await acceptanceHelper.logMeOut();

    // Foodsaver
    await acceptanceHelper.login(foodsaver.email);
    await expect(
      Database.seeInDatabase("fs_betrieb_team", {
        betrieb_id: store.id,
        foodsaver_id: foodsaver.id,
        active: 1,
        verantwortlich: 0,
      }),
    ).resolves.toBeTruthy();
    await expect(
      page.getByRole("heading", { name: "Deine Betriebe" }),
    ).toBeVisible();
    await expect(page.getByRole("link", { name: store.name })).toBeVisible();
  });

  test.describe("Store – Access Restriction", () => {
    const users = [
      { label: "Foodsharer", getUser: (object: any) => object.foodsharer },
      {
        label: "FoodsaverNoRegion",
        getUser: (object: any) => object.foodsaverNoRegion,
      },
      {
        label: "foodsaverDifferentRegion",
        getUser: (object: any) => object.foodsaverDifferentRegion,
      },
    ];

    for (const { label, getUser } of users) {
      test(`User ${label} cannot access store page`, async ({
        page,
        acceptanceHelper,
      }) => {
        const object = {
          foodsharer,
          foodsaverNoRegion,
          foodsaverDifferentRegion,
        };
        const user = getUser(object);
        await acceptanceHelper.login(user.email);
        await page.goto(`/store/${store.id}`);
        // Wait for possible redirect or error page
        await page.waitForTimeout(1000);
        await expect(page).not.toHaveURL(/fsbetrieb/);
      });
    }
  });

  test("StoreManager can see pickup history", async ({
    page,
    acceptanceHelper,
  }) => {
    // Add a pickup in the past for the foodsaver

    const now = new Date();
    const eightHoursAgo = new Date(now);
    eightHoursAgo.setHours(now.getHours() - 8);
    const eightHoursAgoString = foodsharing.toDateTime(eightHoursAgo);
    await Database.addToDatabase("fs_abholer", {
      betrieb_id: store.id,
      foodsaver_id: foodsaver.id,
      date: eightHoursAgoString,
    });

    await acceptanceHelper.login(storeManager.email);
    await page.goto(`/store/${store.id}`);
    // Wait for the pickup history section
    await expect(page.getByText("Slothistorie")).toBeVisible();

    // Expand UI (should be collapsed by default)
    await page.getByRole("heading", { name: "Slothistorie" }).click();
    await expect(page.getByText("Slots anzeigen")).toBeVisible();

    await page.click(".date-picker-from");
    // Click 'Previous Month' as often as possible
    while (
      await page.getByRole("button", { name: "Vorheriger Monat" }).isEnabled()
    ) {
      await page.getByRole("button", { name: "Vorheriger Monat" }).click();
    }

    // Find the first active day (button, not aria-disabled="true")
    const activeDay = page
      .locator(
        '.b-calendar-grid-body [role="button"]:not([aria-disabled="true"])',
      )
      .first();
    await expect(activeDay).toBeVisible();
    await activeDay.click();

    // Submit search
    await page.click(".pickup-search-button > button");
    // Wait for results
    await expect(page.locator(".pickup-date")).toBeVisible({ timeout: 5000 });
    await expect(
      page.getByText(`${foodsaver.name} ${foodsaver.nachname}`),
    ).toBeVisible();
  });

  test.describe("Store – can access store chat", () => {
    const roles = [
      { label: "StoreManager", userGetter: () => storeManager },
      { label: "Foodsaver", userGetter: () => foodsaver },
      {
        label: "FoodsaverOnJumperList",
        userGetter: () => foodsaverOnJumperList,
      },
    ];

    for (const { label, userGetter } of roles) {
      test(`User ${label} can access store chat`, async ({
        acceptanceHelper,
      }) => {
        const user = userGetter();
        await acceptanceHelper.login(user.email);

        if (label === "StoreManager") {
          await expect(
            Database.seeInDatabase("fs_foodsaver_has_conversation", {
              conversation_id: store.team_conversation_id,
              foodsaver_id: storeManager.id,
            }),
          ).resolves.toBeTruthy();

          await expect(
            Database.seeInDatabase("fs_foodsaver_has_conversation", {
              conversation_id: store.springer_conversation_id,
              foodsaver_id: storeManager.id,
            }),
          ).resolves.toBeTruthy();
        }

        if (label === "Foodsaver") {
          await expect(
            Database.seeInDatabase("fs_foodsaver_has_conversation", {
              conversation_id: store.team_conversation_id,
              foodsaver_id: foodsaver.id,
            }),
          ).resolves.toBeTruthy();

          await expect(
            Database.seeInDatabase("fs_foodsaver_has_conversation", {
              conversation_id: store.springer_conversation_id,
              foodsaver_id: foodsaver.id,
            }),
          ).resolves.toBeFalsy();
        }

        if (label === "FoodsaverOnJumperList") {
          await expect(
            Database.seeInDatabase("fs_foodsaver_has_conversation", {
              conversation_id: store.team_conversation_id,
              foodsaver_id: foodsaverOnJumperList.id,
            }),
          ).resolves.toBeFalsy();

          await expect(
            Database.seeInDatabase("fs_foodsaver_has_conversation", {
              conversation_id: store.springer_conversation_id,
              foodsaver_id: foodsaverOnJumperList.id,
            }),
          ).resolves.toBeTruthy();
        }
      });
    }
  });

  test.describe("Store – remove member from store", () => {
    test("StoreManager can remove member", async ({
      page,
      acceptanceHelper,
    }) => {
      await acceptanceHelper.login(storeManager.email);
      await page.goto(`/store/${store.id}`);
      await acceptanceHelper.waitForActiveAPICalls();

      await openOverflow(page, foodsaverOnJumperList.id);

      await page.click(".dropdown-menu.show >> text=Aus dem Team entfernen");
      await page.waitForSelector("text=Bist du dir sicher?");
      await page.click("text=Ja, ich bin mir sicher");
      await acceptanceHelper.waitForActiveAPICalls();
      await expect(page.locator(".store-team")).not.toContainText(
        `${foodsaverOnJumperList.name} ${foodsaverOnJumperList.nachname}`,
      );
      await expect(
        Database.seeInDatabase("fs_betrieb_team", {
          betrieb_id: store.id,
          foodsaver_id: foodsaverOnJumperList.id,
        }),
      ).resolves.toBeFalsy();

      await acceptanceHelper.logMeOut();
    });

    test("Foodsaver cannot remove member", async ({
      page,
      acceptanceHelper,
    }) => {
      await acceptanceHelper.login(foodsaver.email);
      await page.goto(`/store/${store.id}`);
      await acceptanceHelper.waitForActiveAPICalls();

      await openOverflow(page, foodsaverOnJumperList.id);

      await expect(
        page.locator(".dropdown-menu.show >> text=Aus dem Team entfernen"),
      ).toHaveCount(0);

      await acceptanceHelper.logMeOut();
    });
  });

  test("StoreManager can promote and demote a member", async ({
    page,
    acceptanceHelper,
  }) => {
    // ensure member exists in team
    await foodsharing.addStoreTeam(store.id, foodsaverWithStoreManagerQuiz.id);

    await acceptanceHelper.login(storeManager.email);
    await page.goto(`/store/${store.id}`);
    await acceptanceHelper.waitForActiveAPICalls();

    // promote
    await openOverflow(page, foodsaverWithStoreManagerQuiz.id);
    await page.click(".dropdown-menu.show >> text=Verantwortlich machen");
    await acceptanceHelper.waitForActiveAPICalls();

    await expect(
      Database.seeInDatabase("fs_betrieb_team", {
        betrieb_id: store.id,
        foodsaver_id: foodsaverWithStoreManagerQuiz.id,
        active: 1,
        verantwortlich: 1,
      }),
    ).resolves.toBeTruthy();
    await expect(
      page.locator(`#user-${foodsaverWithStoreManagerQuiz.id}.manager`),
    ).toBeVisible();

    // demote
    await openOverflow(page, foodsaverWithStoreManagerQuiz.id);
    await page.click(".dropdown-menu.show >> text=Verantwortung entziehen");
    await page.waitForSelector("text=Bist du dir sicher?");
    await page.click("text=Ja, ich bin mir sicher");
    await acceptanceHelper.waitForActiveAPICalls();

    await expect(
      Database.seeInDatabase("fs_betrieb_team", {
        betrieb_id: store.id,
        foodsaver_id: foodsaverWithStoreManagerQuiz.id,
        verantwortlich: 0,
      }),
    ).resolves.toBeTruthy();
    await expect(
      page.locator(`#user-${foodsaverWithStoreManagerQuiz.id}.manager`),
    ).toHaveCount(0);

    await acceptanceHelper.logMeOut();
  });

  test("can invite store member", async ({ page, acceptanceHelper }) => {
    // login as store manager
    await acceptanceHelper.login(storeManager.email);
    await page.goto(`/store/${store.id}`);
    await acceptanceHelper.waitForActiveAPICalls();

    // invite new foodsaver to the team
    await page.fill(
      "#new-member-search input",
      foodsaverWithStoreManagerQuiz.name,
    );
    await acceptanceHelper.waitForActiveAPICalls();
    await page.waitForSelector("#new-member-search li.suggest-item");
    await page.click("#new-member-search li.suggest-item");
    await page.click('#new-member-search button[type="submit"]');
    await acceptanceHelper.waitForActiveAPICalls();

    await page.waitForSelector("text=Offene Einladungen (1)");
    await page.click("text=Offene Einladungen (1)");
    await page.waitForSelector(`text=Einladungen für ${store.name}`);
    await page.waitForSelector(`text=${foodsaverWithStoreManagerQuiz.name}`);
    await page.waitForSelector(`text=Eingeladen von ${storeManager.name}`);

    await expect(
      Database.seeInDatabase("fs_betrieb_team", {
        betrieb_id: store.id,
        foodsaver_id: foodsaverWithStoreManagerQuiz.id,
        active: MembershipStatus.INVITED,
        verantwortlich: 0,
      }),
    ).resolves.toBeTruthy();

    await acceptanceHelper.logMeOut();
  });

  test("StoreManager can move member to jumper", async ({
    page,
    acceptanceHelper,
  }) => {
    // ensure member exists in team
    await foodsharing.addStoreTeam(store.id, foodsaver.id);

    await acceptanceHelper.login(storeManager.email);
    await page.goto(`/store/${store.id}`);
    await acceptanceHelper.waitForActiveAPICalls();

    // open overflow and move to jumper list
    await openOverflow(page, foodsaver.id);
    await page.click(".dropdown-menu.show >> text=Auf die Springerliste");
    await page.waitForSelector("text=Bist du dir sicher?");
    await page.click("text=Ja, ich bin mir sicher");
    await acceptanceHelper.waitForActiveAPICalls();

    await expect(page.locator(`#user-${foodsaver.id} .jumper`)).toBeVisible();

    await acceptanceHelper.logMeOut();
  });

  test("StoreManager can access store log", async ({
    page,
    acceptanceHelper,
  }) => {
    // Login and open store page
    await acceptanceHelper.login(storeManager.email);
    await page.goto(`/store/${store.id}`);
    await acceptanceHelper.waitForActiveAPICalls();

    // Open store log and filter to "Foodsaver zum Springer machen" only
    await page.click("#store-log");
    await page.click(".multiselect");
    await page.waitForSelector("#null-4");
    await page.click("#null-4");
    await page.click(".multiselect .multiselect__select");
    await page.click("#search-store-log");
    await acceptanceHelper.waitForActiveAPICalls();

    // No entries yet
    await expect(page.locator(".log-entry-content")).toHaveCount(0);

    // Perform action: move foodsaver to jumper list
    await openOverflow(page, foodsaver.id);
    await page.click(".dropdown-menu.show >> text=Auf die Springerliste");
    await page.waitForSelector("text=Bist du dir sicher?");
    await page.click("text=Ja, ich bin mir sicher");
    await acceptanceHelper.waitForActiveAPICalls();

    // Now the store log should contain the action
    await page.click("#search-store-log");
    await acceptanceHelper.waitForActiveAPICalls();
    await expect(page.locator('.list-group-item').getByText("auf die Springerliste gesetzt")).toBeVisible();

    // Toggle filters: enable other category and disable the previous one
    await page.click(".multiselect");
    await page.waitForSelector("#null-3");
    await page.click("#null-3");
    await page.waitForSelector("#null-4");
    await page.click("#null-4");
    await page.click(".multiselect .multiselect__select");
    await page.click("#search-store-log");
    await acceptanceHelper.waitForActiveAPICalls();
    await expect(page.locator(".log-entry-content")).toHaveCount(0);

    await acceptanceHelper.logMeOut();
  });
});
