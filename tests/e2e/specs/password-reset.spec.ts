import { test, expect } from '../helpers/acceptance';
import { foodsharing } from '../helpers/foodsharing';
import { maildev } from '../helpers/maildev';
import { Database } from '../helpers/database';
import argon2 from 'argon2';
import { url } from '@/helper/urls'

test.describe('Password Reset', () => {

  test('user can reset password', async ({ page }) => {
    test.setTimeout(30000); // Extend timeout for email operations

    const newPass = 'TeST1234!';
    const user = await foodsharing.createFoodsaver();

    // Navigate to home page and start password reset flow
    await page.goto('/');
    
    const mobileMenuButton = page.locator('button.navbar-toggler');
    // eslint-disable-next-line playwright/no-conditional-in-test
    if (await mobileMenuButton.isVisible()) {
      await mobileMenuButton.click();
      // eslint-disable-next-line playwright/no-wait-for-timeout
      await page.waitForTimeout(500);
    } else {
      // Desktop navigation - button is visible
      await page.getByRole('button', { name: 'Einloggen Einloggen' }).click({ timeout: 2000 });
    }
    await page.getByRole('menuitem', { name: 'Passwort vergessen?' }).click();

    // Wait for page navigation and Vue component initialization
    await page.waitForURL(`**${url('passwordReset')}`, { timeout: 10000 });
    
    await expect(page.getByText('Gib deine E-Mail-Adresse ein, um dein Passwort zurückzusetzen.')).toBeVisible({ timeout: 10000 });

    await page.fill('#email', user.email);
    await page.getByRole('button', { name: 'Senden' }).click();

    await expect(page.locator('text=Alles klar, dir wurde ein Link zum Ändern des Passworts per E-Mail zugeschickt')).toBeVisible();

    // Verify we received an email with retry mechanism
    let link = '';
    let retries = 5;
    
    // eslint-disable-next-line playwright/no-conditional-in-test
    while (retries > 0 && link === '') {
      // eslint-disable-next-line playwright/no-wait-for-timeout
      await page.waitForTimeout(1000); // Wait longer for email delivery
      
      const mails = await maildev.getMails();
      
      for (const mail of mails) {
        // eslint-disable-next-line playwright/no-conditional-in-test
        if (mail.to[0].address !== user.email.toLowerCase()) {
          continue;
        }
        // eslint-disable-next-line playwright/no-conditional-in-test
        if (mail.subject !== 'Neues Passwort auf foodsharing.de') {
          continue;
        }

        const pattern = /http:\/\/[^\s<>'"]+\/password-reset\/[a-f0-9]+/g;
        const matches = mail.text.match(pattern);
        
        // eslint-disable-next-line playwright/no-conditional-in-test
        if (matches && matches[0]) {
          link = matches[0].replace(/(?<!:)\/\/+/g, '/');
          link = maildev.replaceUrl(link);

          // eslint-disable-next-line playwright/no-conditional-in-test
          if (link) {
            await maildev.deleteMail(mail.id);
            break;
          }
        }
      }
      
      retries--;
    }

    expect(link).not.toBe('');

    // Navigate to the password reset link
    await page.goto(link);

    // First attempt with mismatched passwords
    await page.getByPlaceholder('Passwort (mindestens 8').click();
    await page.getByPlaceholder('Passwort (mindestens 8').fill(newPass);
    await page.getByPlaceholder('Passwortwiederholung').click();
    await page.getByPlaceholder('Passwortwiederholung').fill('INVALID');
    await expect(page.getByText('Beide eingegebenen Passwörter')).toBeVisible();

    // Second attempt with matching passwords
    await page.getByPlaceholder('Passwort (mindestens 8').click();
    await page.getByPlaceholder('Passwort (mindestens 8').fill(newPass);
    await page.getByPlaceholder('Passwortwiederholung').click();
    await page.getByPlaceholder('Passwortwiederholung').fill(newPass);
    await page.getByRole('button', { name: 'Speichern' }).click();

    // Wait for success message to appear first
    await expect(page.locator('.alert-success')).toBeVisible({ timeout: 10000 });
    
    // Then wait for redirect to login page (happens after 2 second delay)
    await page.waitForURL('/login', { timeout: 15000 });

    // Verify password was updated in database
    const userExists = await Database.seeInDatabase('fs_foodsaver', {
      email: user.email
    });
    expect(userExists).toBe(true);

    // Verify new password hash is valid
    const newHash = await Database.grabFromDatabase('fs_foodsaver', 'password', { email: user.email });
    
    // Try argon2 verification as used in the foodsharing helper
    const isValidPassword = await argon2.verify(newHash, newPass);
    expect(isValidPassword).toBe(true);
  });

});