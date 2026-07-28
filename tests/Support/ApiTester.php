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
     * Formats a datetime the way the API serializes all datetimes in responses:
     * UTC with a Z suffix (#2760). Use this to build expected response values.
     */
    public function utcDateTime(\DateTimeInterface $date): string
    {
        return \DateTimeImmutable::createFromInterface($date)
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * Asserts that a datetime string from a response uses the canonical API format
     * (UTC, second precision, Z suffix).
     */
    public function assertUtcDateTimeFormat(string $value): void
    {
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $value,
            "'$value' is not in the canonical API datetime format (UTC with Z suffix)"
        );
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
