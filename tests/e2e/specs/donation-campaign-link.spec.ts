import { test, expect } from "../helpers/acceptance";

// #2877: the campaign link may only appear while a campaign is switched on in the
// donation admin page. The flag reaches the client as showCampaignCard through
// /api/donation-data, so both states are served from there.
//
// The header entry sits in a dropdown that is collapsed until it is opened, so it is
// in the DOM but hidden. getByRole would not see it, because a hidden element is not
// in the accessibility tree. These locators count DOM nodes instead.
test.describe("Campaign link", () => {
  const serveCampaignFlag = (page, showCampaignCard: boolean) =>
    page.route("**/api/donation-data*", async (route) => {
      const response = await route.fetch();
      const data = await response.json();
      await route.fulfill({ json: { ...data, showCampaignCard } });
    });

  // Dynamic selector: Accesses .metanav in the mobile job, otherwise .mainnav
  const headerSelector = (isMobile: boolean) =>
    isMobile ? ".metanav" : ".mainnav";

  const linkIn = (page, area: string, label: string) =>
    page.locator(`${area} a`).filter({ hasText: label });

  test("appears in header and footer while a campaign runs", async ({
    page,
    isMobile,
  }) => {
    await serveCampaignFlag(page, true);
    await page.goto("/");

    const navArea = headerSelector(isMobile);

    await expect(linkIn(page, navArea, "Zur Kampagne")).toHaveAttribute(
      "href",
      "/donation/campaign",
    );
    await expect(linkIn(page, "footer", "Zur Kampagne")).toHaveAttribute(
      "href",
      "/donation/campaign",
    );
    await expect(linkIn(page, "footer", "Zur Spendenseite")).toHaveCount(1);

    await page.unrouteAll({ behavior: "ignoreErrors" });
  });

  test("stays hidden while no campaign runs", async ({ page, isMobile }) => {
    await serveCampaignFlag(page, false);
    await page.goto("/");

    const navArea = headerSelector(isMobile);

    await expect(linkIn(page, navArea, "Zur Spendenseite")).toHaveCount(1);
    await expect(linkIn(page, "footer", "Zur Spendenseite")).toHaveCount(1);

    await expect(linkIn(page, navArea, "Zur Kampagne")).toHaveCount(0);
    await expect(linkIn(page, "footer", "Zur Kampagne")).toHaveCount(0);

    await page.unrouteAll({ behavior: "ignoreErrors" });
  });
});
