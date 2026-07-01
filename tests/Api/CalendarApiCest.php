<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode;
use Foodsharing\Modules\Event\InvitationStatus;
use Tests\Support\ApiTester;

/**
 * @group api-group-2
 */
class CalendarApiCest
{
    private const string TEST_TOKEN = '1234567890';
    private $user;
    private $user2;
    private $region;
    private array $acceptedEvent;
    private array $invitedEvent;

    public function _before(ApiTester $I): void
    {
        $this->region = $I->createRegion();

        $this->user = $I->createFoodsharer();
        $I->addRegionMember($this->region['id'], $this->user['id']);

        $this->user2 = $I->createFoodsharer();
        $I->addRegionMember($this->region['id'], $this->user2['id']);

        $this->acceptedEvent = $I->createEvents($this->region['id'], $this->user2['id']);
        $I->addEventInvitation($this->acceptedEvent['id'], $this->user['id'], [
            'status' => InvitationStatus::ACCEPTED->value
        ]);

        $this->invitedEvent = $I->createEvents($this->region['id'], $this->user2['id']);
        $I->addEventInvitation($this->invitedEvent['id'], $this->user['id'], [
            'status' => InvitationStatus::INVITED->value
        ]);
    }

    public function canNotAccessApiWithoutLogin(ApiTester $I): void
    {
        $I->sendGet('api/calendar/token');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->sendPut('api/calendar/token');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->sendDelete('api/calendar/token');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
    }

    public function cannotRequestNonExistingToken(ApiTester $I): void
    {
        $I->login($this->user['email']);

        $I->sendGet('api/calendar/token');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function canRequestExistingToken(ApiTester $I): void
    {
        $I->login($this->user['email']);

        $I->haveInDatabase('fs_apitoken', [
            'foodsaver_id' => $this->user['id'],
            'token' => self::TEST_TOKEN
        ]);
        $I->sendGet('api/calendar/token');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson(self::TEST_TOKEN);
    }

    public function canCreateToken(ApiTester $I): void
    {
        $I->login($this->user['email']);

        // create a token
        $I->sendPut('api/calendar/token');
        $I->seeResponseCodeIs(HttpCode::OK);
        $token1 = $I->grabDataFromResponseByJsonPath('$.token', true)[0];

        // check if the token was set
        $I->seeInDatabase('fs_apitoken', [
            'foodsaver_id' => $this->user['id'],
            'token' => $token1
        ]);

        // create a new token
        $I->sendPut('api/calendar/token');
        $I->seeResponseCodeIs(HttpCode::OK);
        $token2 = $I->grabDataFromResponseByJsonPath('$.token', true)[0];

        // check if the token was overwritten
        $I->seeInDatabase('fs_apitoken', [
            'foodsaver_id' => $this->user['id'],
            'token' => $token2
        ]);
        $I->dontSeeInDatabase('fs_apitoken', [
            'foodsaver_id' => $this->user['id'],
            'token' => $token1
        ]);
    }

    public function canDeleteToken(ApiTester $I): void
    {
        $I->haveInDatabase('fs_apitoken', [
            'foodsaver_id' => $this->user['id'],
            'token' => self::TEST_TOKEN
        ]);

        $I->login($this->user['email']);
        $I->sendDelete('api/calendar/token');
        $I->dontSeeInDatabase('fs_apitoken', [
            'foodsaver_id' => $this->user['id']
        ]);
    }

    public function canRequestCalendar(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendGet('api/calendar/' . self::TEST_TOKEN);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);

        $I->haveInDatabase('fs_apitoken', [
            'foodsaver_id' => $this->user['id'],
            'token' => self::TEST_TOKEN
        ]);
        $I->sendGet('api/calendar/' . self::TEST_TOKEN);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContains('BEGIN:VCALENDAR');
        $I->seeResponseContains(substr((string)$this->invitedEvent['name'], 0, 10));
        $I->seeResponseContains(substr((string)$this->acceptedEvent['name'], 0, 10));

        // Calendars without explicitly set reminders should not contain
        // reminders
        $I->cantSeeResponseContains('BEGIN:VALARM');
    }

    public function pickupEventContainsStoreSpecialInformation(ApiTester $I): void
    {
        // #1798: the store's "Besonderheiten" should be included in the
        // synchronized pickup event so users see the special instructions.
        $special = 'StoreBesonderheitMarkerABC';
        $store = $I->createStore($this->region['id'], null, null, ['besonderheiten' => $special]);
        // Sign the user up for a future pickup at this store.
        $I->addCollector($this->user['id'], $store['id'], ['date' => new \DateTime('+2 days')]);

        $I->haveInDatabase('fs_apitoken', [
            'foodsaver_id' => $this->user['id'],
            'token' => self::TEST_TOKEN
        ]);
        $I->login($this->user['email']);
        $I->sendGet('api/calendar/' . self::TEST_TOKEN);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Unfold ICS continuation lines (CRLF + space every 75 octets) before
        // asserting, so the marker is matched regardless of where it wraps.
        $unfolded = str_replace(["\r\n ", "\n "], '', $I->grabResponse());
        $I->assertStringContainsString($special, $unfolded);
    }

    public function pickupEventStoreInformationIsRenderedAsMarkdown(ApiTester $I): void
    {
        // #1798 review: the store's "Besonderheiten" is markdown (edited with the
        // markdown editor), so in the html formatting it must be converted to html
        // like the meeting description, not emitted raw.
        $store = $I->createStore($this->region['id'], null, null, ['besonderheiten' => '**StoreBoldMarkerXYZ**']);
        $I->addCollector($this->user['id'], $store['id'], ['date' => new \DateTime('+2 days')]);

        $I->haveInDatabase('fs_apitoken', [
            'foodsaver_id' => $this->user['id'],
            'token' => self::TEST_TOKEN
        ]);
        $I->login($this->user['email']);
        $I->sendGet('api/calendar/' . self::TEST_TOKEN . '?formatting=html');
        $I->seeResponseCodeIs(HttpCode::OK);

        $unfolded = str_replace(["\r\n ", "\n "], '', $I->grabResponse());
        $I->assertStringContainsString('<strong>StoreBoldMarkerXYZ</strong>', $unfolded);
        $I->assertStringNotContainsString('**StoreBoldMarkerXYZ**', $unfolded);
    }

    public function canSetReminders(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->haveInDatabase('fs_apitoken', [
            'foodsaver_id' => $this->user['id'],
            'token' => self::TEST_TOKEN
        ]);
        $I->sendGet('api/calendar/' . self::TEST_TOKEN . '?reminders=60');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContains('BEGIN:VALARM');
        $I->seeResponseContains('TRIGGER:-PT60M');
        $I->seeResponseContains('ACTION:DISPLAY');
    }

    public function canFilterOutInvitations(ApiTester $I)
    {
        $I->haveInDatabase('fs_apitoken', [
            'foodsaver_id' => $this->user['id'],
            'token' => self::TEST_TOKEN
        ]);

        $I->login($this->user['email']);
        $I->sendGet('api/calendar/' . self::TEST_TOKEN);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContains(substr((string)$this->acceptedEvent['name'], 0, 10));
        $I->seeResponseContains(substr((string)$this->invitedEvent['name'], 0, 10));

        $I->sendGet('api/calendar/' . self::TEST_TOKEN . '?events=maybe');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContains(substr((string)$this->acceptedEvent['name'], 0, 10));
        $I->cantSeeResponseContains(substr((string)$this->invitedEvent['name'], 0, 10));
    }
}
