import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";

test.describe("Event", () => {
	let region: Awaited<ReturnType<typeof foodsharing.createRegion>>;
	let foodsaver: Awaited<ReturnType<typeof foodsharing.createFoodsaver>>;

	test.beforeEach(async () => {
		region = await foodsharing.createRegion();
		foodsaver = await foodsharing.createFoodsaver(null, { bezirk_id: region.id });
	});

	test("can open event add page", async ({ page, acceptanceHelper }) => {
		await acceptanceHelper.login(foodsaver.email);
		await page.goto("/event/add");
		await acceptanceHelper.waitForPageBody();
		await expect(page.locator("body")).toContainText("Was ist das für ein Event?");
	});
});

