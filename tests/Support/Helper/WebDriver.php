<?php

declare(strict_types=1);

namespace Tests\Support\Helper;

// here you can define custom WebDriver actions
// all public methods declared in helper class will be available in $I

use DateTime;
use DateTimeZone;
use Facebook\WebDriver\WebDriverExpectedCondition;

class WebDriver extends \Codeception\Module\WebDriver
{
    /**
     * Checks if the element is REALLY visible in the current viewport.
     * The available standard WebDriver actions only check if an element is
     * somewhere in th DOM, but not necessarily if it's visible.
     */
    public function elementVisibleInViewport($selector)
    {
        $visible = $this->executeJS('
                    var elem = document.querySelector(arguments[0]);
                    box = elem.getBoundingClientRect();
                    cx = box.left + box.width / 2;
                    cy = box.top + box.height / 2;
                    return document.elementFromPoint(cx, cy) === elem;', [$selector]
        );

        return $this->assertTrue((bool)$visible, 'Element \'' . $selector . '\' not visible in viewport');
    }

    public function seeFormattedDateInRange($min, $max, $format, $actual): void
    {
        $date = DateTime::createFromFormat($format, $actual, new DateTimeZone('Europe/Berlin'));
        $this->assertGreaterOrEquals($min, $date, 'Date is in past');
        $this->assertLessThanOrEqual($max, $date, 'Date is in future');
    }

    public function waitForFileExists($filename, $timeout = 4): void
    {
        $condition = fn () => file_exists($filename);
        $this->waitFor($condition, $timeout);
    }

    /**
     * Wait until the open URL equals given one.
     *
     * @param int $timeout
     */
    public function waitUrlEquals($url, $timeout = 4): void
    {
        $condition = WebDriverExpectedCondition::urlIs($url);
        $this->waitFor($condition, $timeout);
    }

    public function unlockAllInputFields(): void
    {
        $this->executeJs(
            'document.querySelectorAll(\'*[readOnly]\').forEach(el => el.readOnly = false)'
        );
    }

    public function fillFieldJs($selector, $value): void
    {
        $this->executeJS(
            'document.querySelectorAll(\'' . $selector . '\').forEach(el => el.value = \'' . $value . '\')'
        );
    }

    private function waitFor($condition, $timeout): void
    {
        $this->webDriver->wait($timeout)->until($condition);
    }

    public function seeCookieHasSessionExpiry($cookieName): void
    {
        $cookie = $this->webDriver->manage()->getCookieNamed($cookieName);
        codecept_debug($cookie);
        $this->assertNotNull($cookie['expiry'], 'Cookie should have an expiry set');

        // Check that expiry is 24h ± 1h safety margin
        $now = time();
        $expiryTime = $cookie['expiry'];
        $minExpiry = $now + (23 * 3600); // 23 hours from now
        $maxExpiry = $now + (25 * 3600); // 25 hours from now

        $this->assertGreaterThanOrEqual($minExpiry, $expiryTime,
            'Cookie expiry should be at least 23 hours from now (expires in: ' . round(($expiryTime - $now) / 3600, 2) . ' hours)');
        $this->assertLessThanOrEqual($maxExpiry, $expiryTime,
            'Cookie expiry should be at most 25 hours from now (expires in: ' . round(($expiryTime - $now) / 3600, 2) . ' hours)');
    }

    public function seeCookieHasNoSessionExpiry($cookieName): void
    {
        $cookie = $this->webDriver->manage()->getCookieNamed($cookieName);
        codecept_debug($cookie);
        $this->assertNotNull($cookie['expiry'], 'Cookie should have an expiry set');

        // Check that expiry is 30 days ± 1 day safety margin
        $now = time();
        $expiryTime = $cookie['expiry'];
        $minExpiry = $now + (29 * 24 * 3600); // 29 days from now
        $maxExpiry = $now + (31 * 24 * 3600); // 31 days from now

        $this->assertGreaterThanOrEqual($minExpiry, $expiryTime,
            'Cookie expiry should be at least 29 days from now (expires in: ' . round(($expiryTime - $now) / (24 * 3600), 2) . ' days)');
        $this->assertLessThanOrEqual($maxExpiry, $expiryTime,
            'Cookie expiry should be at most 31 days from now (expires in: ' . round(($expiryTime - $now) / (24 * 3600), 2) . ' days)');
    }
}
