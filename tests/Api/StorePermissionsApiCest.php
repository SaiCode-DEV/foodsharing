<?php

declare(strict_types=1);

namespace Tests\Api;

use Tests\Support\ApiTester;

/**
 * Tests visibility rules for:
 *   GET /api/stores/{storeId}/permissions
 *
 * CI-safe: the logged-out check (401) always runs.
 * Optional: 403 (non-member) and 200 (member) run when env vars are set.
 */
final class StorePermissionsApiCest
{
    /**
     * @group store-permissions
     */
    public function loggedOutGets401(ApiTester $I): void
    {
        // No env var needed for CI: 401 is returned before store existence is checked
        $storeId = (int)(getenv('TEST_STORE_ID_MEMBER') ?: 1);

        $I->sendGet(sprintf('/api/stores/%d/permissions', $storeId));
        $I->seeResponseCodeIs(401);
    }

    /**
     * @group store-permissions
     * Requires: TEST_STORE_ID_NONMEMBER, API_COOKIE_NONMEMBER
     */
    public function nonMemberGets403(ApiTester $I): void
    {
        $storeId = $this->requireEnvIntOrSkip($I, 'TEST_STORE_ID_NONMEMBER');
        $cookie = $this->requireEnvOrSkip($I, 'API_COOKIE_NONMEMBER');

        $I->haveHttpHeader('Cookie', $cookie);
        $I->sendGet(sprintf('/api/stores/%d/permissions', $storeId));
        $I->seeResponseCodeIs(403);
    }

    /**
     * @group store-permissions
     * Requires: TEST_STORE_ID_MEMBER, API_COOKIE_MEMBER
     */
    public function memberGets200(ApiTester $I): void
    {
        $storeId = $this->requireEnvIntOrSkip($I, 'TEST_STORE_ID_MEMBER');
        $cookie = $this->requireEnvOrSkip($I, 'API_COOKIE_MEMBER');

        $I->haveHttpHeader('Cookie', $cookie);
        $I->sendGet(sprintf('/api/stores/%d/permissions', $storeId));
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();

        // Optional structure check:
        $I->seeResponseContainsJson([
            'storeId' => $storeId,
        ]);
    }

    // -----------------------------
    // Helpers
    // -----------------------------

    private function requireEnvOrSkip(ApiTester $I, string $name): string
    {
        $val = getenv($name);
        if (!$val) {
            $I->markTestSkipped(sprintf('Set %s to run this test locally.', $name));
        }

        return (string)$val;
    }

    private function requireEnvIntOrSkip(ApiTester $I, string $name): int
    {
        $val = getenv($name);
        $valStr = (string)$val;
        if (!$val || !ctype_digit($valStr)) {
            $I->markTestSkipped(sprintf('Set %s (integer) to run this test locally.', $name));
        }

        return (int)$valStr;
    }
}
