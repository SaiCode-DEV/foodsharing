<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode;
use Tests\Support\ApiTester;

/**
 * @group api-group-3
 */
class ForumSearchCest
{
    private array $region;
    private array $user;
    private array $threads;
    private array $posts;

    public function _before(ApiTester $I): void
    {
        $this->region = $I->createRegion();
        $this->user = $I->createFoodsaver(null, ['bezirk_id' => $this->region['id']]);
        $I->addRegionMember($this->region['id'], $this->user['id']);

        $I->login($this->user['email']);

        $threads = [
            'Mein erster Post als Foodie' => ['Ich liebe Foodsharing und möchte mehr darüber erfahren.'],
            'Lebensmittel retten leicht gemacht' => ['Lebensmittel retten leicht gemacht ist ein wichtiges Thema.'],
            'Foodsharing in deiner Stadt' => ['Foodsharing in deiner Stadt ist eine tolle Initiative.'],
            'Gemeinsam gegen Lebensmittelverschwendung' => ['Gemeinsam gegen Lebensmittelverschwendung können wir viel erreichen.'],
            'Tipps und Tricks zum Foodsharing' => ['Tipps und Tricks zum Foodsharing sind immer willkommen.']
        ];

        foreach ($threads as $title => $contents) {
            $thread = $I->addForumThread($this->region['id'], $this->user['id'], false, ['name' => $title]);
            foreach ($contents as $content) {
                $this->posts[] = $I->addForumThreadPost($thread['id'], $this->user['id'], ['body' => $content]);
            }
            $this->threads[] = $thread;
        }
    }

    public function canSearchByThreadTitleOneKeyword(ApiTester $I): void
    {
        $I->sendGET("api/search/forum/{$this->region['id']}/0?q=" . urlencode('Foodsharing'));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson([
            'id' => $this->threads[2]['id'],
        ], [
            'id' => $this->threads[4]['id'],
        ]);
        $I->cantSeeResponseContainsJson([
            'id' => $this->threads[0]['id'],
            'name' => $this->threads[0]['name'],
        ], [
            'id' => $this->threads[1]['id'],
            'name' => $this->threads[1]['name'],
        ], [
            'id' => $this->threads[3]['id'],
            'name' => $this->threads[3]['name'],
        ]);
    }

    public function canSearchByThreadTitleMultipleKeywords(ApiTester $I): void
    {
        $I->sendGET("api/search/forum/{$this->region['id']}/0?q=" . urlencode('Foodsharing Tricks'));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson([
            'id' => $this->threads[4]['id'],
            'name' => $this->threads[4]['name'],
        ]);
        $I->cantSeeResponseContainsJson([
            'id' => $this->threads[0]['id'],
            'name' => $this->threads[0]['name'],
        ], [
            'id' => $this->threads[1]['id'],
            'name' => $this->threads[1]['name'],
        ], [
            'id' => $this->threads[2]['id'],
            'name' => $this->threads[2]['name'],
        ], [
            'id' => $this->threads[3]['id'],
            'name' => $this->threads[3]['name'],
        ]);
    }

    public function noResultsForUnknownKeyword(ApiTester $I): void
    {
        $I->sendGET("api/search/forum/{$this->region['id']}/0?q=" . urlencode('Apfeltorte'));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseEquals('[]');
    }

    public function canSearchByThreadBodyOneKeyword(ApiTester $I): void
    {
        $I->sendGET("api/search/forum/{$this->region['id']}/0?searchBody=1&q=" . urlencode('Foodsharing'));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson([
            'id' => $this->threads[0]['id'],
            'name' => $this->threads[0]['name'],
            'body' => $this->posts[0]['body'],
        ], [
            'id' => $this->threads[2]['id'],
            'name' => $this->threads[2]['name'],
            'body' => $this->posts[2]['body'],
        ], [
            'id' => $this->threads[4]['id'],
            'name' => $this->threads[4]['name'],
            'body' => $this->posts[4]['body'],
        ]);
        $I->cantSeeResponseContainsJson([
            'id' => $this->threads[1]['id'],
            'name' => $this->threads[1]['name'],
        ], [
            'id' => $this->threads[3]['id'],
            'name' => $this->threads[3]['name'],
        ]);
    }

    public function canSearchByThreadBodyMultipleKeywords(ApiTester $I): void
    {
        $I->sendGET("api/search/forum/{$this->region['id']}/0?searchBody=1&q=" . urlencode('Foodsharing Stadt'));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson([
            'id' => $this->threads[2]['id'],
            'name' => $this->threads[2]['name'],
            'body' => $this->posts[2]['body'],
        ]);
        $I->cantSeeResponseContainsJson([
            'id' => $this->threads[0]['id'],
            'name' => $this->threads[0]['name'],
        ], [
            'id' => $this->threads[1]['id'],
            'name' => $this->threads[1]['name'],
        ], [
            'id' => $this->threads[4]['id'],
            'name' => $this->threads[4]['name'],
        ], [
            'id' => $this->threads[3]['id'],
            'name' => $this->threads[3]['name'],
        ]);
    }
}
