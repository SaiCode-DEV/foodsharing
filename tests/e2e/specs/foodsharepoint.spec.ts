import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";
import { FoodsharingUI } from "../helpers/foodsharingUI";
import { Database } from "../helpers/database";
import { faker } from "@faker-js/faker";

test.describe("FoodSharePoint", () => {
  let region: Awaited<ReturnType<typeof foodsharing.createRegion>>;
  let user: Awaited<ReturnType<typeof foodsharing.createFoodsharer>>;
  let responsible: Awaited<ReturnType<typeof foodsharing.createAmbassador>>;
  let otherBot: Awaited<ReturnType<typeof foodsharing.createAmbassador>>;
  let foodSharePoint: Awaited<
    ReturnType<typeof foodsharing.createFoodSharePoint>
  >;

  test.beforeEach(async () => {
    region = await foodsharing.createRegion("MyFunnyBezirk", {}, false);
    user = await foodsharing.createFoodsharer(null, { bezirk_id: region.id });
    responsible = await foodsharing.createAmbassador(null, {
      bezirk_id: region.id,
    });
    await foodsharing.addRegionAdmin(region.id, responsible.id);
    otherBot = await foodsharing.createAmbassador(null, {
      bezirk_id: region.id,
    });
    await foodsharing.addRegionAdmin(region.id, otherBot.id);
    foodSharePoint = await foodsharing.createFoodSharePoint(
      responsible.id,
      region.id,
    );
  });

  test("create food share point via UI", async ({ page, acceptanceHelper }) => {
    const streetName = faker.location.street();
    const name = faker.company.name();

    await acceptanceHelper.login(responsible.email);
    await page.goto(`/region?bid=${region.id}&sub=fairteiler`);
    await acceptanceHelper.waitForActiveAPICalls();
    await page.waitForSelector("text=Fairteiler eintragen", { timeout: 10000 });
    await page
      .getByRole("link", { name: "Fairteiler eintragen" })
      .first()
      .click();
    await page.waitForSelector("text=In welchem Bezirk", { timeout: 10000 });
    await page.waitForSelector("#name-input", { timeout: 5000 });
    await page.fill("#name-input", name);
    await page
      .getByRole("textbox", { name: "Beschreibung" })
      .fill("Blablabla if you come here be hungry!");

    await page.locator("#searchField").first().fill(streetName);
    await page.waitForSelector(".location-options");
    const addressText = await page
      .locator(".location-options .list-group-item")
      .first()
      .textContent();
    const addressArray = addressText?.split(",").map((s) => s.trim()) || [];
    const street = addressArray[1] || "";
    const postalCity = addressArray[2]?.trim() || "";
    await page
      .locator(".location-options .list-group-item", { hasText: streetName })
      .first()
      .click();
    await page.getByRole("button", { name: "Speichern" }).first().click();
    await acceptanceHelper.waitForActiveAPICalls();

    const id = await Database.grabFromDatabase("fs_fairteiler", "id", {
      name,
      bezirk_id: region.id,
    });

    await page.goto(`/fairteiler/${id}`);

    await expect(page.locator(`text=${street}`)).toBeVisible({
      timeout: 10000,
    });
    await expect(page.locator(`text=${postalCity}`)).toBeVisible({
      timeout: 10000,
    });
  });

  test("can see food share point in list", async ({
    page,
    acceptanceHelper,
  }) => {
    await acceptanceHelper.login(responsible.email);
    await page.goto(`/region?sub=fairteiler&bid=${region.id}`);
    await page.waitForSelector(`text=${foodSharePoint.name}`);
    await page.click(`text=${foodSharePoint.name}`);
    const firstLine = (foodSharePoint.anschrift || "").split("\n")[0];
    await page.waitForSelector(`text=${firstLine}`);
  });

  test("redirect for get page", async ({ page }) => {
    await page.goto(`/fairteiler/${foodSharePoint.id}`);
    const firstLine = (foodSharePoint.anschrift || "").split("\n")[0];
    await page.waitForSelector(`text=${firstLine}`);
    await expect(page).toHaveURL(`/fairteiler/${foodSharePoint.id}`);
  });

  test("edit food share point", async ({ page, acceptanceHelper }) => {
    const newManager = await foodsharing.createFoodsaver(null, {
      bezirk_id: region.id,
    });

    await acceptanceHelper.login(responsible.email);
    await page.goto(`/fairteiler/${foodSharePoint.id}/edit`);
    await page.waitForSelector(
      "text=Schreibe hier ein paar grundsätzliche Infos über den Fairteiler",
    );

    // Wait for the form to be populated with the fetched foodsharepoint data before making changes
    await expect(page.locator('#description-md')).not.toBeEmpty();

    await page.fill("#description-md", "The BEST fairshare point!");

    const ui = new FoodsharingUI(page);
    await ui.addInTagSelect(newManager.name, "#fspmanagers-input");

    await page.click("text=Speichern");
    await acceptanceHelper.waitForActiveAPICalls();
    await expect(page).toHaveURL(`/fairteiler/${foodSharePoint.id}`);
    await page.waitForSelector("text=The BEST fairshare point");
  });

  test("user may not edit food share point", async ({
    page,
    acceptanceHelper,
  }) => {
    await acceptanceHelper.login(user.email);
    await page.goto(`/fairteiler/${foodSharePoint.id}`);
    await acceptanceHelper.waitForActiveAPICalls();
    await expect(page.locator("body")).toContainText(foodSharePoint.name);
    await expect(page.getByText("Fairteiler bearbeiten")).toBeHidden();
  });

  test("responsible may edit food share point", async ({
    page,
    acceptanceHelper,
  }) => {
    await acceptanceHelper.login(responsible.email);
    await page.goto(`/fairteiler/${foodSharePoint.id}`);
    await acceptanceHelper.waitForActiveAPICalls();
    await expect(page.getByText("Fairteiler bearbeiten")).toBeVisible();
    await page.click("text=Fairteiler bearbeiten");
    await page.waitForSelector("text=Schreibe hier ein paar");
  });

  test("other bot may edit food share point", async ({
    page,
    acceptanceHelper,
  }) => {
    await acceptanceHelper.login(otherBot.email);
    await page.goto(`/fairteiler/${foodSharePoint.id}`);
    await acceptanceHelper.waitForActiveAPICalls();
    await expect(page.getByText("Fairteiler bearbeiten")).toBeVisible();
    await page.click("text=Fairteiler bearbeiten");
    await page.waitForSelector("text=Schreibe hier ein paar");
  });

  test("may not edit food share point wrong bid", async ({
    page,
    acceptanceHelper,
  }) => {
    const otherRegion = await foodsharing.createRegion("another funny region");
    const bot = await foodsharing.createAmbassador(null, {
      bezirk_id: otherRegion.id,
    });
    await foodsharing.addRegionAdmin(otherRegion.id, bot.id);

    await acceptanceHelper.login(bot.email);
    await page.goto(`/fairteiler/${foodSharePoint.id}`);
    await page.waitForSelector(`text=${foodSharePoint.name}`);
    await expect(page.getByText("Fairteiler bearbeiten")).toBeHidden();
  });
});
