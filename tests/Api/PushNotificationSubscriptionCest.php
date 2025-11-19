<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode;
use Tests\Support\ApiTester;

/**
 * @group api-group-2
 */
class PushNotificationSubscriptionCest
{
    /**
     * @var string
     */
    private $testSubscription;

    public function _before(ApiTester $I): void
    {
        $this->tester = $I;
        $this->user = $I->createFoodsaver();

        $this->testSubscription = '
		{
			"endpoint": "https://some.pushservice.com/something-unique",
			"keys": {
				"p256dh": "BIPUL12DLfytvTajnryr2PRdAgXS3HGKiLqndGcJGabyhHheJYlNGCeXl1dn18gSJ1WAkAPIxr4gK0_dQds4yiI=",
				"auth":"FPssNDTKnInHVndSTdbKFw=="
			}
		}';
    }

    public function subscriptionSucceedsIfLoggedIn(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendPOST('api/pushnotification/webpush/subscription', $this->testSubscription);

        $I->seeResponseCodeIs(HttpCode::OK);
        $subscriptionId = $I->grabDataFromResponseByJsonPath('id')[0];
        $I->canSeeInDatabase('fs_push_notification_subscription', [
            'id' => $subscriptionId,
            'foodsaver_id' => $this->user['id'],
            'data' => $this->testSubscription,
            'type' => 'webpush'
        ]);
    }

    public function unsubscriptionSucceedsIfLoggedIn(ApiTester $I): void
    {
        $subscriptionId = 123;
        $I->haveInDatabase('fs_push_notification_subscription', [
            'id' => $subscriptionId,
            'foodsaver_id' => $this->user['id'],
            'data' => $this->testSubscription,
            'type' => 'webpush'
        ]);

        $I->login($this->user['email']);
        $I->sendDELETE('api/pushnotification/webpush/subscription/' . $subscriptionId);

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeInDatabase('fs_push_notification_subscription', ['id' => $subscriptionId]);
    }

    public function subscriptionFailsIfNotLoggedIn(ApiTester $I): void
    {
        $I->sendPOST('api/pushnotification/webpush/subscription', $this->testSubscription);

        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
    }

    public function unsubscriptionFailsIfNotLoggedIn(ApiTester $I): void
    {
        $I->sendDELETE('api/pushnotification/webpush/subscription/123');

        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
    }
}
