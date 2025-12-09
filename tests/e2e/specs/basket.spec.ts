import { test, expect, AcceptanceHelper } from '../helpers/acceptance';
import { foodsharing } from '../helpers/foodsharing';
import { Database } from '../helpers/database';

test.describe('Food Basket', () => {
  // This test is complex with two browser contexts, so it needs more time
  test('can create, update, request and close a food basket', async ({ page, acceptanceHelper, browser }) => {
    test.setTimeout(60000);
    const description = 'my basket';

    const foodsaver = await foodsharing.createFoodsaver();

    // Login and create a food basket
    await acceptanceHelper.login(foodsaver.email);
    await page.goto('/');
    await expect(page.locator('.testing-basket-dropdown')).toContainText('Essenskörbe');
    await page.click('.testing-basket-dropdown > .nav-link');
    await page.waitForSelector('text=Essenskorb anlegen');
    await page.click('.testing-basket-create');
    await page.waitForSelector('text=Beschreibung, Bild, Übergabeort und Zeitraum sind öffentlich sichtbar.');

    await page.fill('#basket-description-input', description);
    await expect(page.locator('#chat-checkbox')).toBeChecked();
    await expect(page.locator('#phone-checkbox')).not.toBeChecked();
    await expect(page.locator('body')).not.toContainText('Telefonnummer');

    // Uncheck chat, should show error
    await page.click('#chat-checkbox + .custom-control-label');
    await expect(page.locator('body')).toContainText('Bitte wähle eine Möglichkeit aus, wie der Essenskorb angefragt werden soll.');

    // Check phone and fill number
    await page.click('#phone-checkbox + .custom-control-label');
    await expect(page.locator('body')).toContainText('Telefonnummer');
    await page.fill('#phone-number-input', '12345');

    await page.selectOption('#duration-select', 'Eine Woche');

    await page.click('text=Speichern');
    await acceptanceHelper.waitForActiveAPICalls();

    // Verify database entry
    await expect(Database.seeInDatabase('fs_basket', {
      description: description,
      foodsaver_id: foodsaver.id,
      handy: '12345'
    })).resolves.toBeTruthy();

    const id = await Database.grabFromDatabase('fs_basket', 'id', {
      description: description,
      foodsaver_id: foodsaver.id
    });

    // Check update of the food basket
    await page.goto(`/essenskoerbe/${id}`);
    await acceptanceHelper.waitForActiveAPICalls();
    await page.click('text=Essenskorb bearbeiten');
    await page.waitForSelector('text=Beschreibung, Bild, Übergabeort und Zeitraum sind öffentlich sichtbar.');

    await page.fill('#basket-description-input', description + ' edited');
    await page.click('#chat-checkbox + .custom-control-label');
    await page.click('text=Speichern');
    await acceptanceHelper.waitForActiveAPICalls();
    await acceptanceHelper.waitForPageBody();
    await expect(page.locator('body')).toContainText('Aktualisiert');
    await expect(page.locator('body')).toContainText(description + ' edited');

    await expect(Database.seeInDatabase('fs_basket', {
      description: description + ' edited',
      foodsaver_id: foodsaver.id,
      handy: '12345'
    })).resolves.toBeTruthy();

    // Create another foodsaver to request the basket
    const picker = await foodsharing.createFoodsaver();

    // Use a new browser context for the second user
    const pickerContext = await browser.newContext();
    const pickerPage = await pickerContext.newPage();
    const pickerHelper = new AcceptanceHelper(pickerPage);

    // Login as picker
    await pickerHelper.login(picker.email);

    // Request the basket
    await pickerPage.goto(`/essenskoerbe/${id}`);
    await pickerHelper.waitForPageBody();

    await pickerPage.waitForSelector('text=Essenskorb anfragen');
    await pickerPage.click('text=Essenskorb anfragen');
    await pickerPage.waitForSelector('text=Anfrage absenden');
    await pickerPage.fill('#contactmessage', 'Hi friend, can I have the basket please?');
    await pickerPage.click('text=Anfrage absenden');
    await pickerHelper.waitForActiveAPICalls();
    await pickerPage.waitForSelector('text=Anfrage wurde versendet');

    await pickerContext.close();

    // Back to original user - check the request
    await page.goto(`/essenskoerbe/${id}`);
    await acceptanceHelper.waitForActiveAPICalls();
    await page.waitForSelector('.loader.active', { state: 'hidden' });
    await page.waitForSelector('text=Anfragen (1)');

    // Open the dropdown menu
    await expect(page.locator('.testing-basket-dropdown')).toContainText('Essenskörbe');
    await page.click('.testing-basket-dropdown > .nav-link');
    await expect(page.locator('.testing-basket-create')).toContainText('Essenskorb anlegen');

    // Set localStorage item to avoid push notification prompt
    await page.evaluate(() => localStorage.setItem('askForPushNotifications', 'false'));

    // Open chat
    await page.click('.testing-basket-requests');
    await expect(page.locator('vue-advanced-chat')).toContainText('Hi friend, can I have');

    // Close request - wait for dropdown to close, then reopen and click close button
    await page.waitForSelector('.testing-basket-create', { state: 'hidden' });
        
    await page.click('.testing-basket-dropdown > .nav-link');
    await page.waitForSelector('.testing-basket-requests-close', { state: 'visible' });
    await page.click('.testing-basket-requests-close');
    await page.waitForSelector(`text=Essenskorbanfrage von ${picker.name} abschließen`);
    await expect(page.locator('body')).toContainText('Hat alles gut geklappt?');

    // Select "Nicht abgeholt" option (value="2")
    await page.click('//label[@for=(//input[@name="basket-request-status"][@value="2"]/@id)]');
    await expect(page.locator('input[name=basket-request-status][value="2"]')).toBeChecked();
    await page.click('text=Speichern');
    await page.waitForSelector('text=Erfolgreich abgeschlossen');
  });
});
