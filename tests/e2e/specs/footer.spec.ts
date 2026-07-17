import { test, expect } from "../helpers/acceptance";

// #1596: the social block must show the .at accounts on foodsharing.at. The footer
// checks window.location.hostname, so the local instance is served under that host.
test.describe("Footer", () => {
  test("shows the Austrian social media links on foodsharing.at (#1596)", async ({
    page,
    baseURL,
  }) => {
    await page.route("**/*", async (route) => {
      const url = new URL(route.request().url());
      if (url.hostname.endsWith("foodsharing.at")) {
        const response = await route.fetch({
          url: url.href.replace(url.origin, baseURL!),
        });
        await route.fulfill({ response });
        return;
      }
      await route.continue();
    });

    await page.goto("http://foodsharing.at/");
    const socials = page.locator("a.social_icons");
    await expect(socials.first()).toBeVisible();
    const hrefs = await socials.evaluateAll((els) =>
      els.map((e) => e.getAttribute("href")),
    );
    expect(hrefs.some((h) => h?.includes("instagram.com/foodsharing.at"))).toBe(
      true,
    );
    expect(hrefs.some((h) => h?.includes("oesterreichfoodsharing"))).toBe(true);
    await page.unrouteAll({ behavior: "ignoreErrors" });
  });
});
