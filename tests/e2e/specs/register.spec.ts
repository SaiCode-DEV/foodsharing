import { test, expect } from '@playwright/test';
import { expect_database } from '../helpers/database';
import { faker } from '@faker-js/faker';

test.describe('Registration', () => {
  test.beforeAll(async () => {
    // Add blacklisted email domain
    await expect_database.addToDatabase('fs_email_blacklist', {
      email: 'bad.com',
      since: '2010-10-14 12:00:00',
      reason: 'Disposable email addresses should not be used for registration.'
    });
  });

  // Generate fresh test data for each test
  const getTestData = () => ({
    email: `${faker.internet.username()}@test.com`,
    password: 'testPassword123',
    firstName: faker.person.firstName(),
    lastName: faker.person.lastName(),
    birthdate: '1983-08-27',
    mobileNumber: '177 3231323',
    countryCode: '+49'
  });

  async function openRegistrationForm(page) {
    await page.goto('/');
    
    // Check if mobile menu button is visible and click it if it is
    const mobileMenuButton = page.locator('button.navbar-toggler');
    if (await mobileMenuButton.isVisible()) {
      await mobileMenuButton.click();
      // Wait for mobile menu animation
      await page.waitForTimeout(300);
    }
    
    await page.click('.testing-register-link');
    await page.click('text=Jetzt registrieren');
  }

  test('can register new user with newsletter', async ({ page }) => {
    const testData = getTestData();
    await openRegistrationForm(page);
    
    // Step 1: Email & Password
    await page.waitForSelector('#step1');
    await page.fill('#email', testData.email);
    await page.fill('#password', testData.password);
    await page.fill('#confirmPassword', testData.password);
    // Wait for email validation to complete
    await page.waitForTimeout(350);
    await page.waitForSelector('button:text("weiter"):not([disabled])');
    await page.click('text=weiter');

    // Step 2: Personal Details  
    await page.waitForSelector('#step2');
    await page.click('label[for="genderWoman"]');
    await page.fill('#firstname', testData.firstName);
    await page.fill('#lastname', testData.lastName);
    await page.click('text=weiter');

    // Step 3: Birthdate
    await page.waitForSelector('#step3');
    await page.fill('#register-birthdate-input', testData.birthdate);
    await page.click('text=weiter');

    // Step 4: Mobile number
    await page.waitForSelector('#step4');
    await page.fill('input[class=vti__input]', testData.mobileNumber);
    await page.click('text=weiter');

    // Step 5: Legal agreements
    await page.waitForSelector('#step5');
    await page.evaluate(() => {
      document.querySelector<HTMLElement>('#acceptGdpr')?.click();
      document.querySelector<HTMLElement>('#acceptLegal')?.click();
      document.querySelector<HTMLElement>('#subscribeNewsletter')?.click();
    });
    await page.click('text=Anmeldung absenden');

    // Verify registration succeeded
    await page.waitForSelector('#step6');
    await expect(page.locator('text=Du hast die Anmeldung bei foodsharing erfolgreich abgeschlossen')).toBeVisible();

    // Verify database entry
    await expect_database.toHaveEntry('fs_foodsaver', {
      email: testData.email,
      name: testData.firstName,
      nachname: testData.lastName,
      newsletter: 1,
      handy: '+49 ' + testData.mobileNumber
    });
  });

  test('can register new user without newsletter', async ({ page }) => {
    const testData = getTestData();
    await openRegistrationForm(page);
    
    // Step 1: Email & Password
    await page.waitForSelector('#step1');
    await page.fill('#email', '      ' + testData.email);
    await page.fill('#password', testData.password);
    await page.fill('#confirmPassword', testData.password);
    // Wait for email validation to complete
    await page.waitForTimeout(350);
    await page.waitForSelector('button:text("weiter"):not([disabled])');
    await page.click('text=weiter');

    // Step 2: Personal Details
    await page.waitForSelector('#step2');
    await page.click('label[for="genderWoman"]');
    await page.fill('#firstname', testData.firstName);
    await page.fill('#lastname', testData.lastName);
    await page.click('text=weiter');

    // Step 3: Birthdate
    await page.waitForSelector('#step3');
    await page.fill('#register-birthdate-input', testData.birthdate);
    await page.click('text=weiter');

    // Step 4: Mobile number
    await page.waitForSelector('#step4');
    await page.fill('input[class=vti__input]', testData.mobileNumber);
    await page.click('text=weiter');

    // Step 5: Legal agreements (without newsletter)
    await page.waitForSelector('#step5');
    await page.evaluate(() => {
      document.querySelector<HTMLElement>('#acceptGdpr')?.click();
      document.querySelector<HTMLElement>('#acceptLegal')?.click();
    });
    await page.click('text=Anmeldung absenden');

    // Verify registration succeeded
    await page.waitForSelector('#step6');
    await expect(page.locator('text=Du hast die Anmeldung bei foodsharing erfolgreich abgeschlossen')).toBeVisible();

    // Verify database entry
    await expect_database.toHaveEntry('fs_foodsaver', {
      email: testData.email,
      name: testData.firstName,
      nachname: testData.lastName,
      newsletter: 0,
      handy: testData.countryCode + ' ' + testData.mobileNumber
    });
  });

  test('cannot register with blacklisted email', async ({ page }) => {
    const testData = getTestData();
    // Grab blacklisted domain from database
    const blacklistedEmailDomain = await expect_database.grabFromDatabase(
      'fs_email_blacklist',
      'email',
      { 'email': '%' }
    );
    
    await openRegistrationForm(page);
    
    await page.waitForSelector('#step1');
    await page.fill('#email', `test@${blacklistedEmailDomain}`);
    await page.fill('#password', testData.password);
    await page.fill('#confirmPassword', testData.password);
    // Wait for email validation to complete
    await page.waitForTimeout(350);
    await page.waitForSelector('button:text("weiter"):not([disabled])');
    await page.click('text=weiter');

    await expect(page.locator('text=Die E-Mail-Adresse ist ungültig')).toBeVisible();
  });
});
