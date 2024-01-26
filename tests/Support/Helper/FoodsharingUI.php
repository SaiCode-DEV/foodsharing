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
     * Searches for a value in a tagselect and marks it.
     *
     * @param string $value to search for and click on, first element will be used if multiple turn up
     * @param string $tagEditId the ID of the tagselect itself
     */
    public function addInTagSelect($value, $tagEditInSelector, $tagEditId = 'tagedit'): void
    {
        $inputId = $tagEditInSelector . ' #' . $tagEditId . '-input';
        $this->getBrowser()->clickWithLeftButton($tagEditInSelector, 3, 3);
        $this->getBrowser()->fillField($inputId, $value);
        $selector = '//a[contains(@id, \'ui-id\') and contains(text(), "' . $value . '")]';
        $this->getBrowser()->waitForElement($selector);
        $this->getBrowser()->click($selector);
    }

    public function removeFromTagSelect($value, $tagEditInId = null): void
    {
        if ($tagEditInId) {
            $selector = '//*[@id="' . $tagEditInId . '"]//*[@value="' . $value . '"]/following-sibling::*';
        } else {
            $selector = '//*[@value="' . $value . '"]/following-sibling::*';
        }

        $this->getBrowser()->click($selector);
        /* wait until it is gone, it might change the layout */
        $this->getBrowser()->dontSee($selector);
    }
}
