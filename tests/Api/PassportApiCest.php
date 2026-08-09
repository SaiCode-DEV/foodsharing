<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode;
use Tests\Support\ApiTester;

/**
 * @group api-group-2
 */
class PassportApiCest
{
    private array $region;
    private array $ambassador;
    private array $user;

    private const ORIGINAL_LAST_PASS = '2024-05-01 10:00:00';

    public function _before(ApiTester $I): void
    {
        $this->region = $I->createRegion();
        $this->ambassador = $I->createAmbassador(null, ['bezirk_id' => $this->region['id']]);
        $I->addRegionAdmin($this->region['id'], $this->ambassador['id']);
        $this->user = $I->createFoodsaver(null, [
            'bezirk_id' => $this->region['id'],
            'last_pass' => self::ORIGINAL_LAST_PASS,
        ]);
        $I->addRegionMember($this->region['id'], $this->user['id']);
    }

    public function reprintWithoutRenewKeepsTheValidity(ApiTester $I): void
    {
        // #2746: a reprint must not shift the validity
        $I->login($this->ambassador['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('api/regions/' . $this->region['id'] . '/passports', [
            'userIds' => [$this->user['id']],
            'createPdf' => true,
            'renew' => false,
            'informUser' => false,
            'usePaperSizeDinA4' => true,
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeInDatabase('fs_foodsaver', ['id' => $this->user['id'], 'last_pass' => self::ORIGINAL_LAST_PASS]);
    }

    public function renewingUpdatesTheValidityStart(ApiTester $I): void
    {
        $I->login($this->ambassador['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('api/regions/' . $this->region['id'] . '/passports', [
            'userIds' => [$this->user['id']],
            'createPdf' => true,
            'renew' => true,
            'informUser' => false,
            'usePaperSizeDinA4' => true,
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->dontSeeInDatabase('fs_foodsaver', ['id' => $this->user['id'], 'last_pass' => self::ORIGINAL_LAST_PASS]);
        $I->seeInDatabase('fs_pass_gen', ['foodsaver_id' => $this->user['id'], 'bot_id' => $this->ambassador['id']]);
    }
}
