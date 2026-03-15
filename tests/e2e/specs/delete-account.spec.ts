import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";
import { Database } from "../helpers/database";

test.describe("Delete Account", () => {
  test("can delete my account (being a foodsaver)", async ({
    page,
    acceptanceHelper,
  }) => {
    test.setTimeout(120_000);

    const foodsaver = await foodsharing.createFoodsaver();

    await acceptanceHelper.login(foodsaver.email);

    await page.goto("/user/current/settings?sub=deleteaccount");

    // Click delete account button
    await page.click("#delete-account");

    // Handle confirmation dialog
    const dialog = page.locator("#confirm-password-modal");
    await dialog.waitFor({ state: "visible" });

    // Enter password to enable delete button
    await page.fill("input[id='current-password']", "password");

    // Wait for screen fading animation and verify text is visible
    await expect(dialog).toContainText("unwiderruflich.", { timeout: 5000 });

    // Wait for countdown to complete (button should no longer be disabled)
    const confirmButton = dialog.locator("button.btn-danger");
    await confirmButton.waitFor({ state: "visible" });

    // Wait until button is clickable (not disabled)
    await expect(async () => {
      const isDisabled = await confirmButton.getAttribute("disabled");
      expect(isDisabled).toBeNull();
    }).toPass({ timeout: 65000 });

    await confirmButton.click();

    await acceptanceHelper.waitForActiveAPICalls();

    // Verify data in database
    expect(
      await Database.seeInDatabase("fs_foodsaver", {
        id: foodsaver.id,
        name: null,
        email: null,
        nachname: null,
        deleted_by: foodsaver.id,
      }),
    ).toBeTruthy();

    expect(
      await Database.seeInDatabase("fs_foodsaver_archive", {
        id: foodsaver.id,
        name: foodsaver.name,
        email: foodsaver.email,
        nachname: foodsaver.nachname,
      }),
    ).toBeTruthy();
  });

  test("cannot delete my account with wrong password", async ({
    page,
    acceptanceHelper,
  }) => {
    test.setTimeout(120_000);

    const foodsaver = await foodsharing.createFoodsaver();

    await acceptanceHelper.login(foodsaver.email);

    await page.goto("/user/current/settings?sub=deleteaccount");

    // Click delete account button
    await page.click("#delete-account");

    // Handle confirmation dialog
    const dialog = page.locator("#confirm-password-modal");
    await dialog.waitFor({ state: "visible" });

    // Enter wrong password to enable delete button
    await page.fill("input[id='current-password']", "wrongpassword");

    // Wait for screen fading animation and verify text is visible
    await expect(dialog).toContainText("unwiderruflich.", { timeout: 5000 });

    // Wait for countdown to complete (button should no longer be disabled)
    const confirmButton = dialog.locator("button.btn-danger");
    await confirmButton.waitFor({ state: "visible" });

    // Wait until button is clickable (not disabled)
    await expect(async () => {
      const isDisabled = await confirmButton.getAttribute("disabled");
      expect(isDisabled).toBeNull();
    }).toPass({ timeout: 65000 });

    await confirmButton.click();

    await acceptanceHelper.waitForActiveAPICalls();

    // Verify data in database - user should not be deleted
    expect(
      await Database.seeInDatabase("fs_foodsaver", {
        id: foodsaver.id,
        email: foodsaver.email,
      }),
    ).toBeTruthy();
  });
});
