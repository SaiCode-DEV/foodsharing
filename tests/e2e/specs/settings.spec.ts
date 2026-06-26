import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";
import { Database } from "../helpers/database";
import fs from "fs/promises";
import { maildev } from "../helpers/maildev";
import Role from "../helpers/constants/Foodsaver/Role";
import { faker } from "@faker-js/faker/locale/de";

test.describe("Settings", () => {
  test.fixme("can edit internal self description. Deactivated, see https://gitlab.com/foodsharing-dev/foodsharing/-/work_items/2738", async ({
    page,
    acceptanceHelper,
  }) => {
    const user = await foodsharing.createFoodsaver();

    await acceptanceHelper.login(user.email);

    const newSelfDesc = "This is a new self description!";

    await page.goto("/user/current/settings");
    await page.getByRole("button", { name: "Profileinstellungen" }).click();
    const aboutMeIntern = page.locator("#about_me_intern");
    await aboutMeIntern.waitFor({ state: "visible" });
    await aboutMeIntern.clear();
    await aboutMeIntern.fill(newSelfDesc);
    await page.click("text=Speichern");

    await acceptanceHelper.waitForActiveAPICalls();

    // Verify the description is visible on the profile page
    await page.goto(`/user/${user.id}/profile`);
    await expect(page.locator("body")).toContainText(newSelfDesc);
  });

  test("can edit location", async ({ page, acceptanceHelper }) => {
    const orga = await foodsharing.createOrga(null, true);
    const foodsharer = await foodsharing.createFoodsharer();

    await acceptanceHelper.login(orga.email);

    const address = "Teststra";

    await page.goto(`/user/${foodsharer.id}/settings`);
    if (await acceptanceHelper.isMobile()) {
      await page.getByRole("button", { name: "Profileinstellungen" }).click();
    }

    // Find an address in the search field
    await page.click("#change-address-button");
    await page.waitForSelector("text=Adresse auswählen");
    await page.locator("#searchField").fill(address);
    await page.waitForSelector(".location-options");
    const addressText = await page
      .locator(".location-options .list-group-item")
      .textContent();
    const addressArray = addressText?.split(",").map((s) => s.trim()) || [];
    const street = addressArray[1] || "";
    const postal = addressArray[2]?.split(" ")[0] || "";
    const city = addressArray[2]?.split(" ").slice(1).join(" ") || "";
    await page.click(`text=${address}`);
    await page.click("text=Adresse übernehmen");
    await page.click("text=Speichern");
    await acceptanceHelper.waitForActiveAPICalls();

    // Verify the address was saved by checking the fields
    await page.click("#change-address-button");
    await page.waitForSelector("text=Adresse auswählen");

    await expect(page.locator("#input-street")).toHaveValue(street);
    await expect(page.locator("#input-postal")).toHaveValue(postal);
    await expect(page.locator("#input-city")).toHaveValue(city);

    await page.click("text=Abbrechen");
  });

  test("shows return to profile button and redirects correctly when editing another user", async ({
    page,
    acceptanceHelper,
  }) => {
    // Setup region, member, and ambassador
    const region = await foodsharing.createRegion();
    const member = await foodsharing.createFoodsaver();
    const ambassador = await foodsharing.createAmbassador(null, {
      bezirk_id: region.id,
    });

    await foodsharing.addRegionAdmin(region.id, ambassador.id);
    await foodsharing.addRegionMember(region.id, member.id);

    await acceptanceHelper.login(ambassador.email);

    // Go to member"s settings page
    await page.goto(`/user/${member.id}/settings`);
    await acceptanceHelper.waitForActiveAPICalls();

    // A single tab (ambassador on another user's profile) is shown directly, with no
    // tab-list step to click through on mobile (#2710).

    // Check last name field
    await expect(page.locator("#input-lastname")).toHaveValue(member.nachname);

    // Click "Zurück zum Profil" and verify redirect
    await page.click("text=Zurück zum Profil");
    await expect(page).toHaveURL(`/user/${member.id}/profile`);
  });

  test("can downgrade foodsharer permanently", async ({
    page,
    acceptanceHelper,
  }) => {
    const region = await foodsharing.createRegion(null, {}, false);
    const foodsharer = await foodsharing.createFoodsharer();
    await foodsharing.addRegionMember(region.id, foodsharer.id);
    const orga = await foodsharing.createOrga();

    await acceptanceHelper.login(orga.email);
    await page.goto(`/user/${foodsharer.id}/settings`);
    await page.getByRole("button", { name: "Profileinstellungen" }).click();
    await page.selectOption("#input-role", "Foodsaver:in");
    await page.click("text=Speichern");
    await page.waitForSelector("text=Erfolgreich abgeschlossen", {
      timeout: 5000,
    });

    expect(
      await Database.seeInDatabase("fs_foodsaver", {
        id: foodsharer.id,
        rolle: Role.FOODSAVER,
      }),
    ).toBeTruthy();

    await page.goto(`/user/${foodsharer.id}/settings`);
    await page.getByRole("button", { name: "Profileinstellungen" }).click();
    await page.selectOption("#input-role", "Foodsharer:in");
    await page.click("text=Speichern");
    await page.waitForSelector("text=Erfolgreich abgeschlossen", {
      timeout: 5000,
    });

    expect(
      await Database.seeInDatabase("fs_foodsaver_has_bell", {
        foodsaver_id: foodsharer.id,
      }),
    ).toBeFalsy();
    expect(
      await Database.seeInDatabase("fs_foodsaver_has_bezirk", {
        foodsaver_id: foodsharer.id,
      }),
    ).toBeFalsy();
    expect(
      await Database.seeInDatabase("fs_botschafter", {
        foodsaver_id: foodsharer.id,
      }),
    ).toBeFalsy();
    expect(
      await Database.seeInDatabase("fs_betrieb_team", {
        foodsaver_id: foodsharer.id,
      }),
    ).toBeFalsy();
    expect(
      await Database.seeInDatabase("fs_abholer", {
        foodsaver_id: foodsharer.id,
      }),
    ).toBeFalsy();
    expect(
      await Database.seeInDatabase("fs_foodsaver_has_conversation", {
        foodsaver_id: foodsharer.id,
      }),
    ).toBeFalsy();

    // Check that there are 5 failed quiz sessions
    const conn = await Database.connect();
    const [rows] = await conn.execute(
      "SELECT COUNT(*) as count FROM `fs_quiz_session` WHERE `foodsaver_id` = ? AND `quiz_id` = ? AND `status` = ?",
      [foodsharer.id, Role.FOODSAVER, 2], // 2 = FAILED
    );
    const count = (rows as Array<{ count: number }>)[0].count;
    expect(count).toBe(5);

    expect(
      await Database.seeInDatabase("fs_foodsaver", {
        id: foodsharer.id,
        rolle: Role.FOODSHARER,
        quiz_rolle: Role.FOODSHARER,
      }),
    ).toBeTruthy();
  });

  test("can view another user settings and verify data from DB", async ({
    page,
    acceptanceHelper,
  }) => {
    const region = await foodsharing.createRegion();
    const foodSaver = await foodsharing.createFoodsaver(null, {
      bezirk_id: region.id,
    });
    const ambassador = await foodsharing.createAmbassador(null, {
      bezirk_id: region.id,
    });
    await foodsharing.addRegionAdmin(region.id, ambassador.id);

    await acceptanceHelper.login(ambassador.email);

    // Visit own settings as ambassador
    await page.goto(`/user/current/settings`);
    await acceptanceHelper.waitForActiveAPICalls();

    // Then visit foodSaver"s settings
    await page.goto(`/user/${foodSaver.id}/settings`);
    await acceptanceHelper.waitForActiveAPICalls();

    // Grab expected data from database for foodSaver
    const expectedName = await Database.grabFromDatabase(
      "fs_foodsaver",
      "name",
      { id: foodSaver.id },
    );
    const expectedLastName = await Database.grabFromDatabase(
      "fs_foodsaver",
      "nachname",
      { id: foodSaver.id },
    );

    // The single profile tab is shown directly on mobile too — no tab-list step (#2710).

    // Assert that the settings form displays the expected data in specific fields
    await expect(page.locator("#input-firstname")).toHaveValue(expectedName);
    await expect(page.locator("#input-lastname")).toHaveValue(expectedLastName);
  });

  test("can edit profile fields as foodsaver", async ({
    page,
    acceptanceHelper,
  }) => {
    const user = await foodsharing.createFoodsaver();

    await acceptanceHelper.login(user.email);

    await page.goto("/user/current/settings");

    await page.getByRole("button", { name: "Profileinstellungen" }).click();

    const mobilenumber = "+49 151 8417482";
    const phonenumber = "+49 4687 0307670";
    const aboutMeIntern = "Ich mag foodsharing.";

    // vue-tel-input normalizes phone numbers:
    // - mobile numbers: spaces removed
    const mobilenumberNormalized = "+49 1518417482";

    await page.locator("#mobile").clear();
    await page.locator("#mobile").fill(mobilenumber);
    await page.locator("#phone").clear();
    await page.locator("#phone").fill(phonenumber);
    await page.locator("#about_me_intern").fill(aboutMeIntern);

    await page.click("text=Speichern");

    await acceptanceHelper.waitForActiveAPICalls();

    // Assert fields contain the new values (normalized format without spaces)
    await expect(page.locator("#phone")).toHaveValue(phonenumber);
    await expect(page.locator("#mobile")).toHaveValue(mobilenumberNormalized);
    await expect(page.locator("#about_me_intern")).toHaveValue(aboutMeIntern);
  });

  test("foodsharer with empty address can visit settings page", async ({
    page,
    acceptanceHelper,
  }) => {
    const foodsharer = await foodsharing.createFoodsharer(null, {
      plz: "",
      stadt: "",
      anschrift: "",
    });

    await acceptanceHelper.login(foodsharer.email);

    await page.goto("/user/current/settings");
    await expect(page.locator("body")).toContainText("Konto löschen");
  });

  test("foodsaver can select business card role and region", async ({
    page,
    acceptanceHelper,
  }) => {
    const region = await foodsharing.createRegion();
    const foodsaver = await foodsharing.createFoodsaver(null, {
      name: "fs1",
      nachname: "saver1",
      photo: "does-not-exist.jpg",
      handy: "+4966669999",
      bezirk_id: region.id,
    });

    await acceptanceHelper.login(foodsaver.email);

    await page.goto("/user/current/settings?sub=bcard");
    await expect(
      page.getByText("Hier einfach generieren, ausdrucken und ausschneiden"),
    ).toBeVisible();

    // select role 'fs' (Foodsaver) and the created region
    await page.locator("select").first().selectOption("fs");
    await page.locator("select").nth(1).selectOption(String(region.id));

    // verify selections applied
    await expect(page.locator("select").first()).toHaveValue("fs");
    await expect(page.locator("select").nth(1)).toHaveValue(String(region.id));

    // Click the generate link and wait for file download (PDF)
    const generateLocator = page.getByRole("link", {
      name: "Visitenkarten erstellen",
    });
    const [download] = await Promise.all([
      page.waitForEvent("download"),
      generateLocator.click(),
    ]);

    const suggested = download.suggestedFilename();
    expect(suggested).toMatch(/bcard-.*\.pdf/);

    const tmpPath = await download.path();
    if (!tmpPath) throw new Error("Download path not available");
    const stat = await fs.stat(tmpPath);
    expect(stat.size).toBeGreaterThan(1000);
  });

  test.fixme("can register and authenticate with passkey/webauthn", async ({
    page,
    acceptanceHelper,
    browserName,
  }) => {
    // WebAuthn CDP is only supported in Chromium-based browsers

    if (browserName !== "chromium") {
      test.skip();
    }

    const user = await foodsharing.createFoodsaver();

    // Initialize CDP session for WebAuthn virtual authenticator
    const client = await page.context().newCDPSession(page);

    // Enable WebAuthn environment
    await client.send("WebAuthn.enable");

    // Add virtual authenticator with specific options
    const { authenticatorId } = await client.send(
      "WebAuthn.addVirtualAuthenticator",
      {
        options: {
          protocol: "ctap2",
          transport: "usb",
          hasResidentKey: true,
          hasUserVerification: true,
          isUserVerified: true,
          automaticPresenceSimulation: true,
        },
      },
    );

    // Login and navigate to account security settings
    await acceptanceHelper.login(user.email);
    await page.goto("/user/current/settings?sub=accountSecurity");
    await acceptanceHelper.waitForActiveAPICalls();

    // Verify no credentials are registered yet
    const credentialsBefore = await client.send("WebAuthn.getCredentials", {
      authenticatorId,
    });
    expect(credentialsBefore.credentials).toHaveLength(0);

    // Check that "no security keys registered" message is visible
    await expect(
      page.getByText("Noch keine Sicherheitsschlüssel registriert"),
    ).toBeVisible();

    // Add a listener for credential registration
    const credentialAddedPromise = new Promise<void>((resolve) => {
      client.on("WebAuthn.credentialAdded", () => {
        resolve();
      });
    });

    // Click button to register a new passkey
    await page
      .getByRole("button", { name: /Neuen Sicherheitsschlüssel registrieren/i })
      .click();

    // Wait for modal to appear
    await expect(
      page.getByRole("heading", {
        name: "Neuen Sicherheitsschlüssel registrieren",
      }),
    ).toBeVisible();

    // Fill in device name
    const deviceName = "Test Authenticator";
    await page.getByRole("textbox", { name: /Gerätename/i }).fill(deviceName);

    // Click register button
    await page.getByRole("button", { name: "Registrieren" }).click();

    // Wait for credential to be added
    await credentialAddedPromise;
    await acceptanceHelper.waitForActiveAPICalls();

    // Verify credential was registered
    const credentialsAfter = await client.send("WebAuthn.getCredentials", {
      authenticatorId,
    });
    expect(credentialsAfter.credentials).toHaveLength(1);

    // Verify the UI updates to show the registered key
    await expect(
      page.getByText("Noch keine Sicherheitsschlüssel registriert"),
    ).toBeHidden();
    await expect(page.getByText(deviceName)).toBeVisible();
  });

  test("can change email address in profile", async ({
    page,
    acceptanceHelper,
  }) => {
    const pass = "testpass123!";
    const newMail = faker.internet.email({
      lastName: `change${faker.string.alphanumeric(16)}`,
    });

    const user = await foodsharing.createFoodsaver(pass);

    await acceptanceHelper.login(user.email, true, pass);

    await page.goto("/user/current/settings");
    await page.getByRole("button", { name: "Kontosicherheit" }).click();
    await page.getByRole("button", { name: /E-Mail-Adresse ändern/i }).click();
    await page.waitForSelector("#new-email");
    await page.fill("#new-email", newMail);
    await page.fill("#new-email-confirm", newMail);
    await page.getByRole("textbox", { name: "Dein Passwort" }).fill(pass);
    await page.getByRole("button", { name: "E-Mail ändern" }).click();
    await expect(
      page.getByRole("heading", { name: "Bist du sicher?" }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Übernehmen" }).click();
    await acceptanceHelper.waitForActiveAPICalls();

    // Wait for the confirmation mail sent to the new address and extract link
    const subject = "Bestätige deine neue E-Mail-Adresse für foodsharing";
    const mail = await maildev.waitForMail(subject, newMail);
    const link = mail.findLink("user/current/settings/email/verify");
    await page.goto(link);
    await expect(page.locator("body")).toContainText(
      "Deine E-Mail-Adresse wurde geändert",
    );

    const found = await Database.seeInDatabase("fs_foodsaver", {
      id: user.id,
      email: newMail,
    });
    expect(found).toBeTruthy();
  });
});
