import { test, expect } from '../helpers/acceptance';
import { foodsharing } from '../helpers/foodsharing';
import { Database } from '../helpers/database';
import Role from '../helpers/constants/Foodsaver/Role';

test.describe('Settings', () => {

  test('can edit internal self description', async ({ page, acceptanceHelper }) => {
    const password = 'password';
    const user = await foodsharing.createFoodsaver(password);

    await acceptanceHelper.login(user.email, password);

    const newSelfDesc = 'This is a new self description!';

    await page.goto('/user/current/settings');

    await page.locator('#about_me_intern').fill(newSelfDesc);
    await page.click('text=Speichern');

    await page.waitForSelector('text=Erfolgreich abgeschlossen', { timeout: 5000 });

    // Verify the description is visible on the profile page
    await page.goto(`/user/${user.id}/profile`);
    await expect(page.locator('body')).toContainText(newSelfDesc);
  });

  test('shows return to profile button and redirects correctly when editing another user', async ({ page, acceptanceHelper }) => {
      // Setup region, member, and ambassador
      const region = await foodsharing.createRegion();
      const member = await foodsharing.createFoodsaver();
      const ambassador = await foodsharing.createAmbassador(null, { bezirk_id: region.id });

      await foodsharing.addRegionAdmin(region.id, ambassador.id);
      await foodsharing.addRegionMember(region.id, member.id);

      await acceptanceHelper.login(ambassador.email);

      // Go to member's settings page
      await page.goto(`/user/${member.id}/settings`);
      await acceptanceHelper.waitForActiveAPICalls();

      // Check last name field
      await expect(page.locator('#input-lastname')).toHaveValue(member.nachname);

      // Click 'Zurück zum Profil' and verify redirect
      await page.click('text=Zurück zum Profil');
      await expect(page).toHaveURL(`/user/${member.id}/profile`);
    });

  test('can downgrade foodsharer permanently', async ({ page, acceptanceHelper }) => {
    const password = 'password';
    const region = await foodsharing.createRegion(null, {}, false);
    const foodsharer = await foodsharing.createFoodsharer(password);
    await foodsharing.addRegionMember(region.id, foodsharer.id);
    const orga = await foodsharing.createOrga(password);

    await acceptanceHelper.login(orga.email, password);
    await page.goto(`/user/${foodsharer.id}/settings`);
    await page.selectOption('#input-role', 'Foodsaver:in');
    await page.click('text=Speichern');
    await page.waitForSelector('text=Erfolgreich abgeschlossen', { timeout: 5000 });

    expect(await Database.seeInDatabase('fs_foodsaver', {
      id: foodsharer.id,
      rolle: Role.FOODSAVER,
    })).toBeTruthy();

    await page.goto(`/user/${foodsharer.id}/settings`);
    await page.selectOption('#input-role', 'Foodsharer:in');
    await page.click('text=Speichern');
    await page.waitForSelector('text=Erfolgreich abgeschlossen', { timeout: 5000 });

    expect(await Database.seeInDatabase('fs_foodsaver_has_bell', { foodsaver_id: foodsharer.id })).toBeFalsy();
    expect(await Database.seeInDatabase('fs_foodsaver_has_bezirk', { foodsaver_id: foodsharer.id })).toBeFalsy();
    expect(await Database.seeInDatabase('fs_botschafter', { foodsaver_id: foodsharer.id })).toBeFalsy();
    expect(await Database.seeInDatabase('fs_betrieb_team', { foodsaver_id: foodsharer.id })).toBeFalsy();
    expect(await Database.seeInDatabase('fs_abholer', { foodsaver_id: foodsharer.id })).toBeFalsy();
    expect(await Database.seeInDatabase('fs_foodsaver_has_conversation', { foodsaver_id: foodsharer.id })).toBeFalsy();

    // Check that there are 5 failed quiz sessions
    const conn = await Database.connect();
    const [rows] = await conn.execute(
      'SELECT COUNT(*) as count FROM `fs_quiz_session` WHERE `foodsaver_id` = ? AND `quiz_id` = ? AND `status` = ?',
      [foodsharer.id, Role.FOODSAVER, 2] // 2 = FAILED
    );
    const count = (rows as Array<{ count: number }>)[0].count;
    expect(count).toBe(5);

    expect(await Database.seeInDatabase('fs_foodsaver', {
      id: foodsharer.id,
      rolle: Role.FOODSHARER,
      quiz_rolle: Role.FOODSHARER
    })).toBeTruthy();

    test('can view another user settings and verify data from DB', async ({ page, acceptanceHelper }) => {
      const password = 'password';
      const region = await foodsharing.createRegion();
      const foodSaver = await foodsharing.createFoodsaver(password, { bezirk_id: region.id });
      const ambassador = await foodsharing.createAmbassador(password, { bezirk_id: region.id });
      await foodsharing.addRegionAdmin(region.id, ambassador.id);

      await acceptanceHelper.login(ambassador.email, password);

      // Visit own settings as ambassador
      await page.goto(`/user/current/settings`);
      await acceptanceHelper.waitForActiveAPICalls();

      // Then visit foodSaver's settings
      await page.goto(`/user/${foodSaver.id}/settings`);
      await acceptanceHelper.waitForActiveAPICalls();

      // Grab expected data from database for foodSaver
      const expectedName = await Database.grabFromDatabase('fs_foodsaver', 'name', { id: foodSaver.id });
      const expectedLastName = await Database.grabFromDatabase('fs_foodsaver', 'nachname', { id: foodSaver.id });

      // Assert that the settings form displays the expected data in specific fields
      await expect(page.locator('#input-firstname')).toHaveValue(expectedName);
      await expect(page.locator('#input-lastname')).toHaveValue(expectedLastName);
    });
  });
