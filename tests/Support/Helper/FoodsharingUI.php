<?php

declare(strict_types=1);

namespace Tests\Support\Helper;

use Codeception\Exception\ModuleException;
use Codeception\Module;

class FoodsharingUI extends Module
{
    /**
     * @throws ModuleException
     */
    private function getBrowser(): Module
    {
        return $this->getModule(WebDriver::class);
    }

    /**
     * Adds a value to the Bootstrap-Vue TagSelect component.
     *
     * @param string $value the value to be searched and selected
     * @param string $tagSelectSelector the CSS selector for the TagSelect component
     */
    public function addInTagSelect(string $value, string $tagSelectSelector = '#tags-with-dropdown'): void
    {
        $browser = $this->getBrowser();

        $dropdownToggleSelector = $tagSelectSelector . ' .dropdown-toggle';
        $browser->click($dropdownToggleSelector);

        $inputSelector = $tagSelectSelector . ' #tag-search-input';
        $browser->waitForElementVisible($inputSelector);
        $browser->fillField($inputSelector, $value);

        $suggestionSelector = $tagSelectSelector . ' .dropdown-item';
        $browser->waitForElementVisible($suggestionSelector);

        // Find all dropdown items and click the one with matching text
        $browser->click("//button[contains(@class, 'dropdown-item') and contains(text(), '$value')]");
        $browser->wait(1); // Wait for 1 second

        $tagSelector = $tagSelectSelector . ' .list-inline-item';
        $browser->waitForElementVisible($tagSelector);
        $browser->see($value, $tagSelector);
    }

    /**
     * Removes a value from the Bootstrap-Vue TagSelect component.
     *
     * @param string $value the value to be removed
     * @param string|null $tagSelectSelector the CSS selector for the TagSelect component
     * @throws ModuleException
     */
    public function removeFromTagSelect(string $value, string $tagSelectSelector = null): void
    {
        $browser = $this->getBrowser();

        $tagXPath = $tagSelectSelector . sprintf(
            '/descendant::li[contains(@class, "list-inline-item")]/*[contains(@title, "%s")]',
            $value
        );

        $removeButtonXPath = $tagXPath . '/following-sibling::button[contains(@class, "b-form-tag-remove")]';

        $browser->click($removeButtonXPath);

        $browser->dontSee($value, $tagSelectSelector . ' .list-inline-item');
    }
}
