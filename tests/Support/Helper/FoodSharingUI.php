<?php

declare(strict_types=1);

namespace Tests\Support\Helper;

use Codeception\Exception\ModuleException;
use Codeception\Module;
use Codeception\Module\WebDriver;

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
     * Fügt einen Wert in der Bootstrap-Vue TagSelect-Komponente hinzu.
     *
     * @param string $value Der Wert, der gesucht und ausgewählt werden soll.
     * @param string $tagSelectSelector Der CSS-Selektor für die TagSelect-Komponente.
     */
    public function addInTagSelect(string $value, string $tagSelectSelector = '#tags-with-dropdown'): void
    {
        $browser = $this->getBrowser();

        // Öffnet das Dropdown-Menü, falls es nicht bereits geöffnet ist
        $dropdownToggleSelector = $tagSelectSelector . ' .dropdown-toggle';
        $browser->click($dropdownToggleSelector);

        // Füllt das Suchfeld aus
        $inputSelector = $tagSelectSelector . ' #tag-search-input';
        $browser->waitForElementVisible($inputSelector);
        $browser->fillField($inputSelector, $value);

        // Wartet auf die angezeigten Vorschläge
        $suggestionSelector = $tagSelectSelector . ' .dropdown-menu .dropdown-item-button';
        $browser->waitForElementVisible($suggestionSelector);

        // Wählt den passenden Vorschlag aus
        $suggestionXPath = $tagSelectSelector . sprintf(
                '/descendant::button[contains(@class, "dropdown-item-button") and normalize-space(text())="%s"]',
                $value
            );
        $browser->click($suggestionXPath);

        // Überprüft, ob das Tag hinzugefügt wurde
        $tagSelector = $tagSelectSelector . ' .list-inline-item';
        $browser->waitForElementVisible($tagSelector);
        $browser->see($value, $tagSelector);
    }

    /**
     * Entfernt einen Wert aus der Bootstrap-Vue TagSelect-Komponente.
     *
     * @param string $value Der Wert, der entfernt werden soll.
     * @param string $tagSelectSelector Der CSS-Selektor für die TagSelect-Komponente.
     */
    public function removeFromTagSelect(string $value, string $tagSelectSelector = '#tags-with-dropdown'): void
    {
        $browser = $this->getBrowser();

        // Findet das Tag, das entfernt werden soll
        $tagXPath = $tagSelectSelector . sprintf(
                '/descendant::li[contains(@class, "list-inline-item")]/*[contains(@title, "%s")]',
                $value
            );

        // Findet die Entfernen-Schaltfläche innerhalb des Tags
        $removeButtonXPath = $tagXPath . '/following-sibling::button[contains(@class, "b-form-tag-remove")]';

        // Klickt auf die Entfernen-Schaltfläche
        $browser->click($removeButtonXPath);

        // Überprüft, ob das Tag entfernt wurde
        $browser->dontSee($value, $tagSelectSelector . ' .list-inline-item');
    }
}
