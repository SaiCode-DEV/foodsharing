import { Page } from "@playwright/test";

export class FoodsharingUI {
  private page: Page;

  constructor(page: Page) {
    this.page = page;
  }

  /**
   * Adds a value to the Bootstrap-Vue TagSelect component.
   * @param value The value to be searched and selected
   * @param tagSelectSelector The CSS selector for the TagSelect component
   */
  async addInTagSelect(
    value: string,
    tagSelectSelector: string = "#tags-with-dropdown",
  ): Promise<void> {
    const dropdownToggleSelector = tagSelectSelector + " .dropdown-toggle";
    await this.page.click(dropdownToggleSelector);

    const inputSelector = tagSelectSelector + " #tag-search-input";
    await this.page.waitForSelector(inputSelector);
    await this.page.fill(inputSelector, value);

    const suggestionSelector = tagSelectSelector + " .dropdown-item";
    await this.page.waitForSelector(suggestionSelector);

    // Find all dropdown items and click the one with matching text
    await this.page.click(
      `//button[contains(@class, 'dropdown-item') and contains(text(), '${value}')]`,
    );
    await this.page.waitForTimeout(1000); // Wait for 1 second

    const tagSelector = tagSelectSelector + " .list-inline-item";
    await this.page.waitForSelector(tagSelector);
    await this.page.locator(tagSelector).filter({ hasText: value }).waitFor();
  }

  /**
   * Removes a value from the Bootstrap-Vue TagSelect component.
   * @param value The value to be removed
   * @param tagSelectSelector The CSS selector for the TagSelect component
   */
  async removeFromTagSelect(
    value: string,
    tagSelectSelector: string | null = null,
  ): Promise<void> {
    const tagXPath =
      tagSelectSelector +
      `/descendant::li[contains(@class, "list-inline-item")]/*[contains(@title, "${value}")]`;

    const removeButtonXPath =
      tagXPath +
      '/following-sibling::button[contains(@class, "b-form-tag-remove")]';

    await this.page.click(removeButtonXPath);

    await this.page
      .locator(tagSelectSelector + " .list-inline-item")
      .filter({ hasText: value })
      .waitFor({ state: "detached" });
  }
}

export const foodsharingUI = new FoodsharingUI(null as any); // Will be initialized with proper page context
