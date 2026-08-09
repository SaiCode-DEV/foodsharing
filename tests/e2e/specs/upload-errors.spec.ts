import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";

// #2804: a rejected upload used to end in console.error only, so the user could
// not tell a too large file from a rejected type or a network problem.

// 1x1 PNG, small enough to pass the client side compression untouched
const TINY_PNG = Buffer.from(
  "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==",
  "base64",
);

test.describe("Image upload errors", () => {
  test("a rejected upload says why", async ({ page, acceptanceHelper }) => {
    const region = await foodsharing.createRegion();
    const user = await foodsharing.createFoodsaver(null, {
      bezirk_id: region.id,
    });
    await foodsharing.addRegionMember(region.id, user.id);

    await acceptanceHelper.login(user.email);

    // the server rejects the upload as too large
    await page.route("**/api/uploads", async (route) => {
      await route.fulfill({
        status: 413,
        contentType: "application/json",
        body: JSON.stringify({ message: "file is bigger than 1.5 MB" }),
      });
    });

    await page.goto(`/profile/${user.id}`);
    await acceptanceHelper.waitForActiveAPICalls();

    const fileInput = page.locator('input[type="file"]').first();
    await fileInput.waitFor({ state: "attached", timeout: 20000 });
    await fileInput.setInputFiles({
      name: "photo.png",
      mimeType: "image/png",
      buffer: TINY_PNG,
    });

    // trigger the upload by submitting the post
    const submit = page.getByRole("button", {
      name: /senden|abschicken|post/i,
    });
    if (await submit.count()) {
      await submit.first().click();
    }

    await expect(page.locator(".vue-notification-group")).toContainText(
      /zu groß|too large/i,
      { timeout: 15000 },
    );
  });

  test("a 400 from the endpoint does not claim the file is too large", async ({
    page,
    acceptanceHelper,
  }) => {
    const region = await foodsharing.createRegion();
    const user = await foodsharing.createFoodsaver(null, {
      bezirk_id: region.id,
    });
    await foodsharing.addRegionMember(region.id, user.id);

    await acceptanceHelper.login(user.email);

    // what the endpoint really answers for a rejected upload
    await page.route("**/api/uploads", async (route) => {
      await route.fulfill({
        status: 400,
        contentType: "application/json",
        body: JSON.stringify({ message: "invalid image provided" }),
      });
    });

    await page.goto(`/profile/${user.id}`);
    await acceptanceHelper.waitForActiveAPICalls();

    const fileInput = page.locator('input[type="file"]').first();
    await fileInput.waitFor({ state: "attached", timeout: 20000 });
    await fileInput.setInputFiles({
      name: "photo.png",
      mimeType: "image/png",
      buffer: TINY_PNG,
    });

    const submit = page.getByRole("button", {
      name: /senden|abschicken|post/i,
    });
    if (await submit.count()) {
      await submit.first().click();
    }

    const notifications = page.locator(".vue-notification-group");
    await expect(notifications).toContainText(
      /hochgeladen werden|could not be uploaded/i,
      { timeout: 15000 },
    );
    await expect(notifications).not.toContainText(/zu groß|too large/i);
  });
});
