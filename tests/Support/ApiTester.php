<?php

declare(strict_types=1);

namespace Tests\Support;

use Codeception\Actor;
use Tests\Support\_generated\ApiTesterActions;

/**
 * Inherited Methods.
 * ... docblock unchanged ...
 */
class ApiTester extends Actor
{
    use ApiTesterActions;

    /**
     * Checks the content type is html, and the content contains html.
     */
    public function seeHtml(): void
    {
        $I = $this;
        $I->seeHttpHeader('Content-Type', 'text/html; charset=UTF-8');
        $I->seeResponseIsHtml();
    }

    /**
     * Checks if the status code of the last response is in the array of expected codes.
     *
     * @param int[] $code
     */
    public function seeStatusCodeIs(array $code): void
    {
        $response = json_decode($this->grabResponse(), true);
        $status = $response['code'] ?? null;
        $this->assertTrue(in_array($status, $code), "Response code $status is not in the expected values: " . implode(', ', $code));
    }
}
