import { test, expect } from '../helpers/acceptance';
import { foodsharing } from '../helpers/foodsharing';

test.describe('Region', () => {

  test('can click through vue generated store list pages', async ({ page, acceptanceHelper }) => {
    const region = await foodsharing.createRegion('A region I test with');

    const storeCoordinator = await foodsharing.createStoreCoordinator(null, { bezirk_id: region.id });

    // Create 30 stores to ensure pagination
    for (let i = 0; i < 30; i++) {
      await foodsharing.createStore(region.id);
    }

    await acceptanceHelper.login(storeCoordinator.email);
    await page.goto(`/region/${region.id}/stores`);

    // Page 1 active and Page 2 available
    await expect(page.locator('.page-item.active .page-link')).toContainText('1');
    await expect(page.locator('.page-item .page-link').filter({ hasText: '2' })).toBeVisible();

    // Go to page 2
    await page.click('.page-link[aria-posinset="2"]');

    // Page 2 active and Page 1 available
    await expect(page.locator('.page-item .page-link').filter({ hasText: '1' })).toBeVisible();
    await expect(page.locator('.page-item.active .page-link')).toContainText('2');
  });

});
