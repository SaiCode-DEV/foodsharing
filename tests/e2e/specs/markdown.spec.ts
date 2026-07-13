import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";
import { faker } from "@faker-js/faker";

const testData = {} as {
  foodsaver: Awaited<ReturnType<typeof foodsharing.createFoodsaver>>;
  testBezirk: Awaited<ReturnType<typeof foodsharing.createRegion>>;
};

test.beforeAll(async () => {
  testData.testBezirk = await foodsharing.createRegion(
    "markdownRegion",
    {},
    false,
  );
  testData.foodsaver = await foodsharing.createFoodsaver(null, {
    bezirk_id: testData.testBezirk.id,
  });
});

test.describe("markdown tables", () => {
  test("a pipe table renders as a styled table", async ({
    page,
    acceptanceHelper,
  }) => {
    await acceptanceHelper.login(testData.foodsaver.email);
    const title = faker.word.words(5);
    const body =
      "| Fruit | Amount |\n| --- | --- |\n| Apples | 3 |\n| Pears | 5 |";
    await foodsharing.createForumThread({
      forumId: testData.testBezirk.id,
      title,
      body,
      page,
    });
    await page.goto(`/region?bid=${testData.testBezirk.id}&sub=forum`);
    await acceptanceHelper.waitForPageBody();
    await page.getByText(title).first().click();
    await acceptanceHelper.waitForActiveAPICalls();

    const table = page.locator(".markdown table").first();
    await expect(table).toBeVisible({ timeout: 15000 });
    await expect(table.locator("th").first()).toHaveText("Fruit");
    await expect(table.locator("td").first()).toHaveText("Apples");
    await expect(table).toHaveCSS("border-collapse", "collapse");
  });

  test("a pipe without the separator line stays plain text", async ({
    page,
    acceptanceHelper,
  }) => {
    await acceptanceHelper.login(testData.foodsaver.email);
    const title = faker.word.words(5);
    const body = "Apples | 3 kg | fresh";
    await foodsharing.createForumThread({
      forumId: testData.testBezirk.id,
      title,
      body,
      page,
    });
    await page.goto(`/region?bid=${testData.testBezirk.id}&sub=forum`);
    await acceptanceHelper.waitForPageBody();
    await page.getByText(title).first().click();
    await acceptanceHelper.waitForActiveAPICalls();

    await expect(page.locator("body")).toContainText("Apples | 3 kg | fresh");
    await expect(page.locator(".markdown table")).toHaveCount(0);
  });
});
