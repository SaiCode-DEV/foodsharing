import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";
import { Database } from "../helpers/database";

// #2811: quill clamps its link popup against `bounds`, which defaults to
// document.body, so a selection near the left edge pushes it out of the editor.
test("keeps the link popup inside the editor", async ({
  page,
  acceptanceHelper,
}) => {
  const orga = await foodsharing.createOrga();
  const contentId = await Database.addToDatabase("fs_content", {
    name: "e2e-link-popup",
    title: "Link popup",
    body: "<p>Ein Satz, an dessen Anfang das Popup aus dem Editor rutscht.</p>",
    last_mod: "2026-01-01 00:00:00",
  });

  await acceptanceHelper.login(orga.email);
  await page.goto(`/content?a=edit&id=${contentId}`);
  await acceptanceHelper.waitForPageBody();
  await page.waitForSelector(".ql-editor", { timeout: 20000 });
  await expect(page.locator(".ql-editor")).not.toBeEmpty();

  // select text at the very start, that is where the popup escapes to the left
  await page.locator(".ql-editor").click();
  await page.keyboard.press("Home");
  for (let i = 0; i < 5; i++) await page.keyboard.press("Shift+ArrowRight");
  await page.locator(".ql-toolbar .ql-link").click();

  const tooltip = page.locator(".ql-tooltip");
  await expect(tooltip).toBeVisible();

  const box = await page.evaluate(() => {
    const t = document.querySelector(".ql-tooltip") as HTMLElement;
    const c = document.querySelector("#content-body") as HTMLElement;
    return {
      tooltipLeft: t.getBoundingClientRect().left,
      containerLeft: c.getBoundingClientRect().left,
    };
  });
  expect(box.tooltipLeft).toBeGreaterThanOrEqual(box.containerLeft - 1);
});
