import { test, expect } from "../helpers/acceptance";

// #2849: navigating to a donation subpage a second time changed the address
// but not the content.
test.describe("Donation page navigation", () => {
  test("opens the selfservice form after coming back from it", async ({
    page,
  }) => {
    // The embed container only holds the external form, which is not
    // configured in the test instance, so check for the element itself.
    const donationForm = page.locator(".twingle-container");

    await page.goto("/donation/selfservice");
    await expect(donationForm).toBeAttached();

    await page.locator('footer a[href="/donation"]').first().click();
    await page.waitForURL(/\/donation$/, { timeout: 15000 });
    await expect(donationForm).not.toBeAttached();

    await page
      .locator('footer a[href="/donation/selfservice"]')
      .first()
      .click();
    await page.waitForURL(/\/donation\/selfservice$/, { timeout: 15000 });
    await expect(donationForm).toBeAttached();
  });
});
