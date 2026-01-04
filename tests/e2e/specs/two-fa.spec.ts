import { test, expect, AcceptanceHelper } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";
import { Database } from "../helpers/database";
import { maildev } from "../helpers/maildev";
import argon2 from "argon2";
import { authenticator } from "otplib";
import { Page } from "@playwright/test";

test.describe("Two-Factor Authentication", () => {
  test.describe.configure({ timeout: 60000 });

  let password: string;
  let foodsaver: Record<string, unknown>;
  let secret: string;
  let backupCodes: string[];

  test.beforeEach(async () => {
    password = "password";
    foodsaver = await foodsharing.createFoodsharer(password);
    secret = "";
    backupCodes = [];
  });

  /**
   * Helper function to enable TOTP for the current user
   */
  async function enableTOTP(
    page: Page,
    acceptanceHelper: AcceptanceHelper,
    logout = true,
  ) {
    // Assert in database that TOTP is not enabled
    const hasTotpBefore = await Database.seeInDatabase("fs_foodsaver", {
      email: foodsaver.email as string,
      totp_secret: null,
      backup_codes: null,
    });
    expect(hasTotpBefore).toBe(true);

    // Login without TOTP
    await acceptanceHelper.login(foodsaver.email as string, true, password);

    // Go to settings
    await page.goto("/user/current/settings");
    await acceptanceHelper.waitForPageBody();
    await acceptanceHelper.waitForActiveAPICalls();
    await page.click("text=Kontosicherheit");

    // Click on enable TOTP
    await page.getByRole("button", { name: "2FA aktivieren" }).click();
    await acceptanceHelper.waitForActiveAPICalls();

    // Wait for modal to load with QR code (Tab 2)
    await acceptanceHelper.waitForActiveAPICalls();
    await page.getByRole("button", { name: "Weiter" }).click();
    await page.waitForSelector(".testing-qr-code", { timeout: 5000 });

    // Expand secret code section
    await page
      .getByRole("button", {
        name: "Code anzeigen (falls QR-Scan nicht funktioniert)",
      })
      .click();
    await page.waitForSelector(".testing-totp-secret", { timeout: 5000 });

    // Go to backup codes tab
    await page.getByRole("button", { name: "Weiter" }).click();
    await page.waitForSelector(".testing-backup-codes", { timeout: 5000 });

    // Get the secret (still visible from expanded section)
    secret =
      (await page.locator(".testing-totp-secret").textContent())?.trim() || "";

    // Get backup codes (currently visible on this tab)
    const backupCodeElements = await page
      .locator(".testing-backup-codes")
      .all();
    backupCodes = [];
    for (const element of backupCodeElements) {
      const code = (await element.textContent())?.trim() || "";
      backupCodes.push(code);
    }

    // Assert the secret and backup codes are not empty
    expect(secret).toBeTruthy();
    expect(secret.length).toBeGreaterThan(0);
    for (const code of backupCodes) {
      expect(code).toBeTruthy();
      expect(code.length).toBeGreaterThan(0);
    }

    // Check the secret has the expected format (Base32, 32 characters)
    expect(secret).toMatch(/^[A-Z2-7]{32}$/);

    // Assert there are twelve backup codes
    expect(backupCodes.length).toBe(12);

    // Confirm backup codes saved
    await page.getByText("Ich habe die Backup-Codes").check();

    // Go to confirmation tab
    await page.getByRole("button", { name: "Weiter" }).click();
    await expect(
      page.getByRole("heading", { name: "Aktivierung bestätigen" }),
    ).toBeVisible();

    // Compute valid code
    const validCode = authenticator.generate(secret);

    // Fill the form with the valid code
    await page.getByRole("textbox", { name: "z.B." }).fill(validCode);
    await page.getByRole("textbox", { name: "Passwort" }).fill(password);

    // Click on submit
    await page.getByRole("button", { name: "Aktivieren", exact: true }).click();
    await acceptanceHelper.waitForActiveAPICalls();

    // Assert in database that TOTP is enabled
    const totpSecret = await Database.grabFromDatabase(
      "fs_foodsaver",
      "totp_secret",
      { email: foodsaver.email as string },
    );
    const backupCodesDb = await Database.grabFromDatabase(
      "fs_foodsaver",
      "backup_codes",
      { email: foodsaver.email as string },
    );

    expect(totpSecret).toBe(secret);
    expect(backupCodesDb).toBe(JSON.stringify(backupCodes));

    if (logout) {
      await acceptanceHelper.logMeOut();
    }
  }

  /**
   * Helper function to attempt login and check if it should fail
   */
  async function attemptLogin(
    acceptanceHelper: AcceptanceHelper,
    email: string,
    pass: string,
    totpSecretOrCode?: string,
  ) {
    try {
      await acceptanceHelper.login(email, true, pass, totpSecretOrCode);
      return true;
    } catch {
      return false;
    }
  }

  test("login without code fails when TOTP is enabled", async ({
    page,
    acceptanceHelper,
  }) => {
    await enableTOTP(page, acceptanceHelper);

    // Assert login without TOTP fails
    const loginSuccess = await attemptLogin(
      acceptanceHelper,
      foodsaver.email as string,
      password,
    );
    expect(loginSuccess).toBe(false);
  });

  test("reveals TOTP input and focuses it after 403 login response", async ({
    page,
    acceptanceHelper,
  }) => {
    await enableTOTP(page, acceptanceHelper);

    // Open app and the login dropdown.
    await page.goto("/");
    await acceptanceHelper.openMobileMenuIfNeeded();
    await page.click(".testing-login-dropdown");

    // Fill credentials and submit
    await page.fill(".testing-login-input-email", foodsaver.email as string);
    await page.fill("#testing-login-input-password > input", password);
    await page.waitForTimeout(250);
    await page.click(".testing-login-click-submit");

    // Wait until the TOTP component wrapper is visible
    await page.waitForSelector("#testing-login-input-totp", {
      state: "visible",
      timeout: 5000,
    });

    // Wait until its inner input becomes the activeElement (focused)
    await page.waitForFunction(
      () => {
        const totpInput = document.querySelector(
          "#testing-login-input-totp input",
        ) as HTMLElement | null;
        return !!totpInput && document.activeElement === totpInput;
      },
      null,
      { timeout: 3000 },
    );

    const focused = await page.evaluate(() => {
      const totpInput = document.querySelector(
        "#testing-login-input-totp input",
      ) as HTMLElement | null;
      return !!totpInput && document.activeElement === totpInput;
    });

    expect(focused).toBe(true);
  });

  test("login with code succeeds when TOTP is enabled", async ({
    page,
    acceptanceHelper,
  }) => {
    await enableTOTP(page, acceptanceHelper);

    // Login via UI with TOTP
    await page.goto("/");
    await page.evaluate("window.localStorage.clear();");
    await acceptanceHelper.openMobileMenuIfNeeded();
    await page.waitForSelector(".testing-login-dropdown");
    await page.click(".testing-login-dropdown");
    await page.fill(".testing-login-input-email", foodsaver.email as string);
    await page.fill("#testing-login-input-password > input", password);
    await page.click(".testing-login-click-submit");
    await acceptanceHelper.waitForActiveAPICalls();

    // Wait for TOTP input to appear
    await page.waitForSelector("#testing-login-input-totp > input", {
      timeout: 5000,
    });

    // Generate and fill TOTP code
    const totpCode = authenticator.generate(secret);
    await page.fill("#testing-login-input-totp > input", totpCode);
    await page.click(".testing-login-click-submit");
    await acceptanceHelper.waitForActiveAPICalls();

    await page.waitForSelector("#pulse-success", {
      state: "hidden",
      timeout: 10000,
    });
    await acceptanceHelper.waitForPageBody();
    await page.waitForSelector(".testing-intro-field", { timeout: 5000 });
    await expect(page.locator(".testing-intro-field")).toContainText("Hallo");
  });

  test("login with backup code succeeds when TOTP is enabled", async ({
    page,
    acceptanceHelper,
  }) => {
    await enableTOTP(page, acceptanceHelper);

    // Login via UI with backup code
    await page.goto("/");
    await page.evaluate("window.localStorage.clear();");
    await acceptanceHelper.openMobileMenuIfNeeded();
    await page.waitForSelector(".testing-login-dropdown");
    await page.click(".testing-login-dropdown");
    await page.fill(".testing-login-input-email", foodsaver.email as string);
    await page.fill("#testing-login-input-password > input", password);
    await page.click(".testing-login-click-submit");
    await acceptanceHelper.waitForActiveAPICalls();

    // Wait for TOTP input to appear
    await page.waitForSelector("#testing-login-input-totp > input", {
      timeout: 5000,
    });

    // Fill backup code
    await page.fill("#testing-login-input-totp > input", backupCodes[0]);
    await page.click(".testing-login-click-submit");
    await acceptanceHelper.waitForActiveAPICalls();

    await page.waitForSelector("#pulse-success", {
      state: "hidden",
      timeout: 10000,
    });
    await acceptanceHelper.waitForPageBody();
    await page.waitForSelector(".testing-intro-field", { timeout: 5000 });

    // Go to settings (test direct navigation via GET parameter)
    await page.goto("/user/current/settings?sub=accountSecurity");
    await acceptanceHelper.waitForPageBody();
    await acceptanceHelper.waitForActiveAPICalls();

    // Assert 11 backup codes remain
    await expect(page.locator("#testing-num-backup-codes")).toContainText(
      "11",
      { timeout: 10000 },
    );
  });

  test("can enable and disable TOTP", async ({ page, acceptanceHelper }) => {
    await enableTOTP(page, acceptanceHelper, false);

    // Verify TOTP is actually enabled in the database
    const totpSecretEnabled = await Database.grabFromDatabase(
      "fs_foodsaver",
      "totp_secret",
      { email: foodsaver.email as string },
    );
    const backupCodesEnabled = await Database.grabFromDatabase(
      "fs_foodsaver",
      "backup_codes",
      { email: foodsaver.email as string },
    );
    expect(totpSecretEnabled).not.toBeNull();
    expect(backupCodesEnabled).not.toBeNull();
    expect(totpSecretEnabled).toBe(secret);
    expect(backupCodesEnabled).toBe(JSON.stringify(backupCodes));

    // Go to settings
    await page.goto("/user/current/settings");
    await acceptanceHelper.waitForPageBody();
    await acceptanceHelper.waitForActiveAPICalls();
    await page.click("text=Kontosicherheit");
    await acceptanceHelper.waitForPageBody();
    await page.getByRole("button", { name: "2FA deaktivieren" }).click();

    // Click on disable TOTP
    await acceptanceHelper.waitForActiveAPICalls();

    // Fill the form with a valid code (generate fresh code to avoid expiration)
    const validCode = authenticator.generate(secret);
    await page.fill("#testing-totp-input-totp > input", validCode);
    await page.fill("#testing-totp-input-password > input", password);

    // Click on submit
    await page
      .getByRole("button", { name: "Deaktivieren", exact: true })
      .click();
    await acceptanceHelper.waitForActiveAPICalls();

    // Assert in database that TOTP is not enabled
    const hasTotpDisabled = await Database.seeInDatabase("fs_foodsaver", {
      email: foodsaver.email as string,
      totp_secret: null,
      backup_codes: null,
    });
    expect(hasTotpDisabled).toBe(true);

    // Assert login without TOTP now succeeds
    await acceptanceHelper.logMeOut();
    const loginSuccess = await attemptLogin(
      acceptanceHelper,
      foodsaver.email as string,
      password,
    );
    expect(loginSuccess).toBe(true);
  });

  test("password reset enforces TOTP", async ({ page, acceptanceHelper }) => {
    test.setTimeout(60000); // Extended timeout for email operations

    const newPass = "yourNewPassword1234!";

    // Enable TOTP
    await enableTOTP(page, acceptanceHelper);

    await page.goto("/");
    const mobileMenuButton = page.locator("button.navbar-toggler");

    if (await mobileMenuButton.isVisible()) {
      await mobileMenuButton.click();

      await page.waitForTimeout(500);
    } else {
      await page
        .getByRole("button", { name: "Einloggen" })
        .click({ timeout: 2000 });
    }
    await page.getByRole("menuitem", { name: "Passwort vergessen?" }).click();

    await expect(page.getByText("Gib deine E-Mail-Adresse ein")).toBeVisible({
      timeout: 10000,
    });

    await page.fill("#email", foodsaver.email as string);
    await page.getByRole("button", { name: "Senden" }).click();

    await expect(
      page.locator(
        "text=Alles klar, dir wurde ein Link zum Ändern des Passworts per E-Mail zugeschickt",
      ),
    ).toBeVisible();

    // Wait for email and extract link
    let link = "";
    let retries = 10;

    while (retries > 0 && link === "") {
      await page.waitForTimeout(1000);

      const mails = await maildev.getMails();

      for (const mail of mails) {
        if (mail.to[0].address !== (foodsaver.email as string).toLowerCase()) {
          continue;
        }

        // Use text content instead of HTML and update regex to match TOTP parameter
        const pattern =
          /http:\/\/[^\s<>'"]+\/password-reset\/[a-f0-9]+\?totp=true/g;
        const matches = mail.text.match(pattern);

        if (matches && matches[0]) {
          link = matches[0].replace(/(?<!:)\/\/+/g, "/");
          link = maildev.replaceUrl(link);

          if (link) {
            await maildev.deleteMail(mail.id);
            break;
          }
        }
      }

      retries--;
    }

    expect(link).not.toBe("");
    expect(link).toContain("totp=true");

    // Navigate to the password reset link
    await page.goto(link);
    await acceptanceHelper.waitForPageBody();
    await acceptanceHelper.waitForActiveAPICalls();

    // Compute a valid code
    const validCode = authenticator.generate(secret);

    // Fill the form
    await page.fill("#testing-reset-input-password > input", newPass);
    await page.fill("#testing-reset-input-confirm-password > input", newPass);
    await page.fill("#testing-reset-input-totp > input", validCode);
    await page.click("#password-change-button");
    await page.waitForSelector(
      "text=Prima, dein Passwort wurde erfolgreich geändert.",
      { timeout: 10000 },
    );

    // Ensure new password hash is set in database -> change password worked
    const newHash = await Database.grabFromDatabase(
      "fs_foodsaver",
      "password",
      { email: foodsaver.email as string },
    );
    const isValidPassword = await argon2.verify(newHash, newPass);
    expect(isValidPassword).toBe(true);
  });
});
