import { test, expect } from "../helpers/acceptance";

// #2718: a literal '@' in a translation must render instead of throwing in the
// vue-i18n compiler. Pure logic tested directly, the compiler path in the browser.
import "../helpers/client-module-shim";
import { escapeLinkedTokens } from "../../../client/src/helper/i18n-escape";
import { foodsharing } from "../helpers/foodsharing";

test.describe("i18n literal @ handling (#2718)", () => {
  test("escapeLinkedTokens escapes a literal @ to the vue-i18n literal", () => {
    expect(escapeLinkedTokens("¿Estas segur@?")).toBe("¿Estas segur{'@'}?");
    expect(escapeLinkedTokens("info@foodsharing.de")).toBe(
      "info{'@'}foodsharing.de",
    );
  });

  test("leaves strings without @ and non-string values untouched", () => {
    expect(escapeLinkedTokens("Bist du sicher?")).toBe("Bist du sicher?");
    expect(escapeLinkedTokens(42)).toBe(42);
    expect(escapeLinkedTokens(null)).toBe(null);
    expect(escapeLinkedTokens({ a: "x@y", b: "z" })).toEqual({
      a: "x{'@'}y",
      b: "z",
    });
    expect(escapeLinkedTokens(["x@y", "z"])).toEqual(["x{'@'}y", "z"]);
  });
});

test.describe("i18n literal @ in the app (#2718)", () => {
  test("renders a translation containing a literal @ instead of throwing", async ({
    page,
    acceptanceHelper,
  }) => {
    const user = await foodsharing.createFoodsaver();
    await acceptanceHelper.login(user.email);

    // the modal placeholder "deine.neue@email.de" only renders if the escape works
    await page.goto("/user/current/settings?sub=accountSecurity");
    await page.getByRole("button", { name: "E-Mail-Adresse ändern" }).click();
    const input = page.getByPlaceholder(/@/).first();
    await expect(input).toBeVisible();
  });
});
