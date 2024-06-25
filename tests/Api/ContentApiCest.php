<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Example;
use Codeception\Util\HttpCode;
use Faker\Factory;
use Tests\Support\ApiTester;

class ContentApiCest
{
    private $faker;
    private $user;
    private $userOrga;
    private $existingContent;

    public function _before(ApiTester $I): void
    {
        $this->faker = Factory::create('de_DE');

        $this->user = $I->createFoodsharer();
        $this->userOrga = $I->createOrga();
        $this->existingContent = $I->createContent();
    }

    public function canNotAddContentAsFoodsaver(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/content', $this->createRandomContentForPost());
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function canAddContentAsOrga(ApiTester $I): void
    {
        $content = $this->createRandomContentForPost();

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/content', $content);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->login($this->userOrga['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/content', $content);
        $I->seeResponseCodeIs(HttpCode::OK);

        $id = $I->grabDataFromResponseByJsonPath('id')[0];
        $I->seeInDatabase('fs_content', [
            'id' => $id,
            'title' => $content['title'],
            'name' => $content['name'],
            'body' => $content['body']
        ]);
    }

    /**
     * @example ["title"]
     * @example ["name"]
     */
    public function canNotAddInvalidContent(ApiTester $I, Example $example): void
    {
        $I->login($this->userOrga['email']);

        $content = $this->createRandomContentForPost();
        $content[$example[0]] = '';
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/content', $content);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function canNotEditContentAsFoodsaver(ApiTester $I): void
    {
        $I->login($this->user['email']);

        // create a copy that is different
        $content = $this->existingContent;
        $content['title'] = $this->faker->title;

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/content/' . $this->existingContent['id'], $this->existingContent);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);

        // make sure that it was not modified in the database
        $I->seeInDatabase('fs_content', [
            'id' => $this->existingContent['id'],
            'title' => $this->existingContent['title'],
            'name' => $this->existingContent['name'],
            'last_mod' => $this->existingContent['last_mod'],
            'body' => $this->existingContent['body'],
        ]);
    }

    public function canEditContentAsOrga(ApiTester $I): void
    {
        // create a copy that is different
        $content = $this->existingContent;
        $content['title'] = $this->faker->title;

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/content/' . $this->existingContent['id'], $content);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
        $I->seeInDatabase('fs_content', [
            'id' => $this->existingContent['id'],
            'title' => $this->existingContent['title']
        ]);

        $I->login($this->userOrga['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/content/' . $this->existingContent['id'], $content);
        $I->seeResponseCodeIs(HttpCode::OK);

        // make sure that it was modified in the database
        $I->seeInDatabase('fs_content', [
            'id' => $this->existingContent['id'],
            'title' => $content['title'],
        ]);
    }

    public function canNotEditNonExistentContent(ApiTester $I): void
    {
        $I->login($this->userOrga['email']);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/content/999999', $this->existingContent);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);

        // make sure that it was not modified in the database
        $I->seeInDatabase('fs_content', [
            'id' => $this->existingContent['id'],
            'title' => $this->existingContent['title'],
            'name' => $this->existingContent['name'],
            'last_mod' => $this->existingContent['last_mod'],
            'body' => $this->existingContent['body'],
        ]);
    }

    /**
     * @example ["title"]
     * @example ["name"]
     */
    public function canNotEditInvalidContent(ApiTester $I, Example $example): void
    {
        $I->login($this->userOrga['email']);

        $content = $this->existingContent;
        $content[$example[0]] = '';
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/content/' . $this->existingContent['id'], $content);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);

        // make sure that it was not modified in the database
        $I->seeInDatabase('fs_content', [
            'id' => $this->existingContent['id'],
            'title' => $this->existingContent['title'],
            'name' => $this->existingContent['name'],
            'last_mod' => $this->existingContent['last_mod'],
            'body' => $this->existingContent['body'],
        ]);
    }

    private function createRandomContentForPost(): array
    {
        $title = $this->faker->title;

        return [
            'name' => strtolower(str_replace(' ', '_', $title)),
            'title' => $title,
            'body' => $this->faker->text,
        ];
    }
}
