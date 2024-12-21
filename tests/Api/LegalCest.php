<?php

declare(strict_types=1);

namespace Tests\Api;

use Carbon\Carbon;
use Codeception\Util\HttpCode;
use Foodsharing\Modules\Core\DBConstants\Content\ContentId;
use Tests\Support\ApiTester;

/**
 * Makes sure that all but some required API endpoints do not work if the privacy policy was not accepted.
 */
class LegalCest
{
    private $user;

    public function _before(ApiTester $I): void
    {
        $policyDate = Carbon::now()->subWeek();
        $acceptedDate = $policyDate->subMonth();

        $this->user = $I->createFoodsaver(null, [
            'privacy_policy_accepted_date' => $acceptedDate->format('Y-m-d H:i:s'),
            'privacy_notice_accepted_date' => $acceptedDate->format('Y-m-d H:i:s'),
        ]);

        $I->updateInDatabase('fs_content', ['last_mod' => $policyDate->format('Y-m-d H:i:s')],
            ['id' => ContentId::PRIVACY_POLICY_CONTENT]);
        $I->updateInDatabase('fs_content', ['last_mod' => $policyDate->format('Y-m-d H:i:s')],
            ['id' => ContentId::PRIVACY_NOTICE_CONTENT]);
    }

    public function canUseApiWithoutLogin(ApiTester $I): void
    {
        $I->sendGET('/api/map/markers/baskets');
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function canNotUseApiWithLogin(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendGET('/api/map/markers/stores');
        $I->seeResponseCodeIs(HttpCode::UNAVAILABLE_FOR_LEGAL_REASONS);
    }
}
