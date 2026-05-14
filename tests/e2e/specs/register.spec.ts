import { test, expect } from "../helpers/acceptance";
import { fakerDE as faker } from "@faker-js/faker";
import { foodsharing } from "../helpers/foodsharing";
import { Database } from "../helpers/database";
import { maildev } from "helpers/maildev";
import type { Locator, Page } from "@playwright/test";

class RegistrationPage {
  public readonly formArea: Locator;
  public readonly nextStepButton: Locator;
  public readonly email: Locator;
  public readonly password: Locator;
  public readonly firstName: Locator;
  public readonly lastName: Locator;
  public readonly confirmPassword: Locator;
  public readonly birthdate: Locator;
  public readonly mobileNumber: Locator;

  constructor(private page: Page) {
    this.formArea = page.locator("main .card");
    this.nextStepButton = this.formArea.getByRole("button", { name: "weiter" });
    this.email = this.formArea.getByRole("textbox", { name: "E-Mail-Adresse" });
    this.password = this.formArea
      .getByRole("textbox", { name: "Passwort" })
      .nth(0);
    this.firstName = this.formArea.getByRole("textbox", { name: "Vorname" });
    this.lastName = this.formArea.getByRole("textbox", { name: "Nachname" });
    this.confirmPassword = this.formArea.getByRole("textbox", {
      name: "Passwort bestätigen",
    });
    this.birthdate = this.formArea.getByLabel("Geburtsdatum");
    this.mobileNumber = this.formArea.getByLabel("Handynummer");
  }

  async openForm() {
    await this.page.goto("/");
    // Check if mobile menu button is visible and click it if it is
    const mobileMenuButton = this.page.locator("button.navbar-toggler");
    if (await mobileMenuButton.isVisible()) {
      await mobileMenuButton.click();
    }
    await this.page
      .getByRole("navigation")
      .getByRole("link", { name: "Mitmachen" })
      .click();
    await this.page.getByText("Jetzt registrieren").click();
  }

  async nextStep() {
    await this.nextStepButton.click();
  }

  async expectStep(step: number) {
    await expect(
      this.formArea.getByText(`Registrierung (${step} / 6)`),
    ).toBeVisible();
  }

  async getMailedTokenLink(emailAddress: string): Promise<string> {
    const tokenMail = await maildev.waitForMail(
      "Registrierung bei foodsharing",
      emailAddress,
      10000,
    );
    const tokenLink = tokenMail.findLink("register-continue");
    expect(tokenLink).toBeTruthy();

    return tokenLink;
  }

  async validateTokenLink(tokenLink: string): Promise<string> {
    const url = new URL(
      tokenLink,
      await this.page.evaluate(() => window.location.origin),
    );
    expect(url.pathname).toBe("/register-continue");
    const token = url.searchParams.get("token");
    expect(token).toMatch(/^[a-zA-Z0-9-_]{30,}$/);
    return token;
  }

  // "männlich" | "weiblich" | "divers"
  async selectGender(gender: string) {
    await this.formArea.getByText(gender).click();
  }

  async togglePrivacyPolicy() {
    await this.formArea
      .locator("label span")
      .filter({ hasText: "Datenschutzerklärung" })
      .click({ position: { x: 5, y: 5 } });
  }

  async toggleLegalAgreement() {
    await this.formArea
      .locator("label span")
      .filter({ hasText: "Rechtsvereinbarung" })
      .click({ position: { x: 5, y: 5 } });
  }

  async toggleNewsletterSubscription() {
    await this.formArea
      .locator("label")
      .filter({ hasText: "Newsletter" })
      .click();
  }
}

test.describe("Registration", () => {
  let registrationPage: RegistrationPage;

  test.describe.configure({ timeout: 60000 });

  const blacklistedDomain = "bad.com";

  test.beforeAll(async () => {
    await foodsharing.createBlacklistedEmailAddress(blacklistedDomain);
  });

  test.beforeEach(async ({ page }) => {
    registrationPage = new RegistrationPage(page);
  });

  // Generate fresh test data for each test
  const getTestData = () => ({
    email: `${faker.internet.username()}_${faker.string.alphanumeric(16)}@test.com`,
    password: "testPassword123",
    firstName: faker.person.firstName(),
    lastName: faker.person.lastName(),
    gender: "divers",
    genderCode: 3,
    birthdate: "1983-08-27",
    mobileNumber: "177 3231323",
    countryCode: "+49",
    newsletter: true,
  });

  const testExamples = [
    getTestData(),
    {
      ...getTestData(),
      gender: "weiblich",
      genderCode: 2,
      mobileNumber: null,
      newsletter: false,
    },
  ];
  for (const [index, testData] of testExamples.entries()) {
    test(`can register new user with dataset ${index + 1}`, async ({
      page,
    }) => {
      await registrationPage.openForm();
      const formArea = registrationPage.formArea;

      // Step 0: Request registration token for email address
      await expect(formArea.getByText("Registrierung")).toBeVisible();
      await registrationPage.email.fill(testData.email);
      await registrationPage.nextStep();
      await expect(
        formArea.getByText(/Bestätigungslink.*gesendet/),
      ).toBeVisible();
      const tokenLink = await registrationPage.getMailedTokenLink(
        testData.email,
      );
      await registrationPage.validateTokenLink(tokenLink);

      // Step 1: Password
      await page.goto(tokenLink);
      await registrationPage.expectStep(1);
      await registrationPage.password.fill(testData.password);
      await expect(registrationPage.nextStepButton).toBeDisabled();
      await registrationPage.confirmPassword.fill(testData.password);
      await registrationPage.nextStep();

      // Step 2: Personal Details
      await registrationPage.expectStep(2);
      await registrationPage.selectGender(testData.gender);
      await registrationPage.firstName.fill(testData.firstName);
      await registrationPage.lastName.fill(testData.lastName);
      await registrationPage.nextStep();

      // Step 3: Birthdate
      await registrationPage.expectStep(3);
      await registrationPage.birthdate.fill(testData.birthdate);
      await registrationPage.nextStep();

      // Step 4: Mobile number
      await registrationPage.expectStep(4);
      if (testData.mobileNumber) {
        await registrationPage.mobileNumber.fill(testData.mobileNumber);
      }
      await registrationPage.nextStep();

      // Step 5: Legal agreements
      await registrationPage.expectStep(5);
      await registrationPage.togglePrivacyPolicy();
      await registrationPage.toggleLegalAgreement();
      if (testData.newsletter) {
        await registrationPage.toggleNewsletterSubscription();
      }
      await formArea
        .getByRole("button", { name: "Anmeldung absenden" })
        .click();

      // Verify registration succeeded
      await registrationPage.expectStep(6);
      await expect(
        formArea.getByText(
          "Du hast die Anmeldung bei foodsharing erfolgreich abgeschlossen",
        ),
      ).toBeVisible();

      // Check newsletter subscription failure message is shown, because listmonk is not running in the test environment
      expect(
        await formArea
          .getByText(
            "Anmeldung zum Newsletter ist leider ein Fehler aufgetreten",
          )
          .isVisible(),
      ).toBe(testData.newsletter);

      // Verify database entry
      const fsParams = {
        email: testData.email,
        name: testData.firstName,
        nachname: testData.lastName,
        geschlecht: testData.genderCode,
        geb_datum: testData.birthdate,
        handy: testData.mobileNumber
          ? `${testData.countryCode} ${testData.mobileNumber}`
          : "",
        active: 1,
        quiz_rolle: 0,
      };
      await expect(
        Database.seeInDatabase("fs_foodsaver", fsParams),
      ).resolves.toBeTruthy();

      expect(
        await maildev.waitForMail(
          "Schön, dass Du jetzt dabei bist!",
          testData.email,
        ),
      ).not.toBeNull();

      // Try to log in with the new user
      await formArea.getByRole("button", { name: "Einloggen" }).click();
      await page.waitForURL("/login");
      await page
        .getByRole("textbox", { name: "E-Mail-Adresse" })
        .fill(testData.email);
      await page
        .getByRole("textbox", { name: "Passwort" })
        .fill(testData.password);
      await page
        .getByRole("main")
        .getByRole("button", { name: "Einloggen", exact: true })
        .click();
      await expect(
        page.getByRole("main").getByText(`Hallo ${testData.firstName}`).first(),
      ).toBeVisible();
    });
  }

  test("cannot register with invalid or blacklisted email", async () => {
    await registrationPage.openForm();

    // Invalid email address
    await registrationPage.email.fill(`test@test`);
    await expect(registrationPage.nextStepButton).toBeDisabled();

    // Internal email address
    await registrationPage.email.fill(`test@foodsharing.de`);
    await registrationPage.nextStep();
    await expect(
      registrationPage.formArea.getByText(
        "Adresse kann nicht verwendet werden",
      ),
    ).toBeVisible();

    // Blacklisted email address
    await registrationPage.email.fill(`test@${blacklistedDomain}`);
    await registrationPage.nextStep();
    await expect(
      registrationPage.formArea.getByText(
        "Adresse kann nicht verwendet werden",
      ),
    ).toBeVisible();
  });

  test("cannot register with too simple password", async ({ page }) => {
    const testData = getTestData();
    await registrationPage.openForm();

    // Step 0: Request registration token for email address
    await registrationPage.email.fill(testData.email);
    await registrationPage.nextStep();
    const tokenLink = await registrationPage.getMailedTokenLink(testData.email);

    // Step 1: Password
    await page.goto(tokenLink);
    await registrationPage.expectStep(1);

    for (const passwordTestCase of [
      {
        password: "password",
        lengthOk: true,
        complexityOk: false,
        trimOk: true,
      },
      {
        password: "12345678",
        lengthOk: true,
        complexityOk: false,
        trimOk: true,
      },
      { password: "abc", lengthOk: false, complexityOk: false, trimOk: true },
      { password: "Aa1", lengthOk: false, complexityOk: true, trimOk: true },
      {
        password: "   Aa1   ",
        lengthOk: true,
        complexityOk: true,
        trimOk: false,
      },
      {
        password: "FoOdShArIn9",
        lengthOk: true,
        complexityOk: true,
        trimOk: true,
      },
    ]) {
      await registrationPage.password.fill(passwordTestCase.password);
      await registrationPage.confirmPassword.fill(passwordTestCase.password);

      const errors = {
        "muss mindestens aus acht Zeichen bestehen": !passwordTestCase.lengthOk,
        "muss mindestens jeweils einen Groß- und Kleinbuchstaben sowie Zahlen beinhalten":
          !passwordTestCase.complexityOk,
        "darf keine Leerzeichen am Anfang oder Ende enthalten":
          !passwordTestCase.trimOk,
      };
      for (const [errorText, shouldBeVisible] of Object.entries(errors)) {
        const errorLocator = registrationPage.formArea.locator(
          `text=Das Passwort ${errorText}`,
        );
        if (shouldBeVisible) {
          await expect(errorLocator).toBeVisible();
        } else {
          await expect(errorLocator).toBeHidden();
        }
      }

      if (Object.values(errors).some((v) => v)) {
        await expect(registrationPage.nextStepButton).toBeDisabled();
      } else {
        await expect(registrationPage.nextStepButton).toBeEnabled();
      }
    }
  });
});
