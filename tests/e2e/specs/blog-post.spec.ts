import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";
import { Tags } from "../helpers/tags";

test.describe("Blog post", () => {
  test(
    "renders inside a container with a background (#2812)",
    { tag: [Tags.DESKTOP_ONLY] },
    async ({ page, acceptanceHelper }) => {
      const region = await foodsharing.createRegion();
      const author = await foodsharing.createFoodsaver(null, {
        bezirk_id: region.id,
      });
      const post = await foodsharing.addBlogPost(author.id, region.id, {
        name: "Testbeitrag im Blog",
      });

      await acceptanceHelper.login(author.email);
      await page.goto(`/blog/${post.id}`);
      // the title alone is not enough to wait for, it is in the breadcrumb before
      // the component has rendered
      await page.locator(".blogpost").waitFor({ state: "visible" });

      // The post used to sit in a leftover jQuery UI element without any
      // background, so the page background showed through.
      const background = await page.evaluate(() => {
        const post = document.querySelector(".blogpost");
        const container = post?.closest(".list-group");
        return container ? getComputedStyle(container).backgroundColor : null;
      });
      expect(background).not.toBeNull();
      expect(background).not.toBe("rgba(0, 0, 0, 0)");
    },
  );
});
