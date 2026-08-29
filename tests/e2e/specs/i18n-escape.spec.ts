import { test, expect } from "../helpers/acceptance";

// #2718: a literal '@' in a translation must render instead of throwing in the
// vue-i18n compiler. Pure logic tested directly, the compiler path in the browser.
import "../helpers/client-module-shim";
import {
  escapeLinkedTokens,
  unescapeLinkedTokens,
} from "../../../client/src/helper/i18n-escape";
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

  // #2887: the escape is v9 syntax, the v8 formatter of the legacy bridge leaves it
  // in the output, so postTranslation has to undo it after rendering.
  test("unescapeLinkedTokens turns the literal back into an @", () => {
    expect(unescapeLinkedTokens("info{'@'}foodsharing.de")).toBe(
      "info@foodsharing.de",
    );
    expect(unescapeLinkedTokens("a{'@'}b and c{'@'}d")).toBe("a@b and c@d");
    expect(unescapeLinkedTokens("nothing to undo")).toBe("nothing to undo");
    expect(unescapeLinkedTokens(42 as any)).toBe(42);
  });

  test("escaping and unescaping a message round trips", () => {
    for (const original of [
      "¿Estas segur@?",
      "[fundraising@foodsharing.network](mailto:fundraising@foodsharing.network)",
      "no at sign here",
    ]) {
      expect(unescapeLinkedTokens(escapeLinkedTokens(original))).toBe(original);
    }
  });
});

test.describe("i18n literal @ in the app (#2718)", () => {
  test("renders a translation containing a literal @ instead of throwing", async ({
    page,
    acceptanceHelper,
  }) => {
    const user = await foodsharing.createFoodsaver();
    await acceptanceHelper.login(user.email);

    // the modal placeholder "deine.neue@email.de" only renders if the escape works.
    // /@/ alone would also match "{'@'}", so the escape must be absent explicitly.
    await page.goto("/user/current/settings?sub=accountSecurity");
    await page.getByRole("button", { name: "E-Mail-Adresse ändern" }).click();
    const input = page.getByPlaceholder(/@/).first();
    await expect(input).toBeVisible();
    expect(await input.getAttribute("placeholder")).not.toContain("{'@'}");
  });
});

// #2887: the FAQ answers on the donation page are the visible case, they carry the
// contact addresses. A broken escape hits the link text and the mailto: target.
test.describe("Mail addresses in translations (#2887)", () => {
  test("renders the donation FAQ addresses as real mail addresses", async ({
    page,
  }) => {
    await page.goto("/donation");

    const mailLinks = page.locator('a[href^="mailto:"]');
    await expect(mailLinks.first()).toBeAttached();

    const links = await mailLinks.evaluateAll((els) =>
      els.map((e) => ({
        text: (e.textContent || "").trim(),
        href: e.getAttribute("href") || "",
      })),
    );
    expect(links.length).toBeGreaterThan(0);
    for (const link of links) {
      expect(link.text).not.toContain("{'@'}");
      expect(link.href).not.toContain("{'@'}");
      // the escape survives into the href url-encoded as %7B'@'%7D
      expect(link.href.toUpperCase()).not.toContain("%7B");
      expect(link.href).toContain("@");
    }
  });
});
