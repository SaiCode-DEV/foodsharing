<?php

declare(strict_types=1);

namespace Api;

use Codeception\Util\HttpCode;
use Faker\Factory;
use Foodsharing\Modules\Core\DBConstants\Uploads\UploadUsage;
use Tests\Support\ApiTester;

class UploadsApiCest
{
    private $faker;
    private $user;
    private $ambassador;

    public function _before(ApiTester $I): void
    {
        $this->faker = Factory::create('de_DE');

        $this->user = $I->createFoodsharer();
        $this->ambassador = $I->createAmbassador();
    }

    public function canUploadFiles(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $this->uploadFile($I);
        $I->seeResponseCodeIs(HttpCode::OK);
        $uuid = $I->grabDataFromResponseByJsonPath('uuid')[0];
        $I->seeInDatabase('uploads', ['uuid' => $uuid]);
    }

    public function canNotUploadFilesWithoutLogin(ApiTester $I): void
    {
        $this->uploadFile($I);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
    }

    public function canAccessEmailAttachmentsAsAuthor(ApiTester $I): void
    {
        $I->login($this->ambassador['email']);
        $this->uploadFile($I);
        $I->seeResponseCodeIs(HttpCode::OK);
        $uuid = $I->grabDataFromResponseByJsonPath('uuid')[0];

        $this->markUploadAsMailboxAttachment($I, $uuid, $this->ambassador['id']);

        // download the file
        $I->sendGet('api/uploads/' . $uuid);
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function canNotAccessEmailAttachmentsAsRandomUser(ApiTester $I): void
    {
        $I->login($this->ambassador['email']);
        $this->uploadFile($I);
        $I->seeResponseCodeIs(HttpCode::OK);
        $uuid = $I->grabDataFromResponseByJsonPath('uuid')[0];

        $this->markUploadAsMailboxAttachment($I, $uuid, $this->ambassador['id']);

        // download the file
        $I->login($this->user['email']);
        $I->sendGet('api/uploads/' . $uuid);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    /**
     * Creates a random file, uploads it to the API, and returns the UUID.
     */
    private function uploadFile(ApiTester $I): void
    {
        $file = $this->faker->paragraph();

        $I->sendPost('api/uploads', [
            'filename' => 'text.txt',
            'body' => base64_encode($file)
        ]);
    }

    /**
     * Marks the uploaded file with the specified UUID as being used as email attachment. This uses a random email from
     * the user's personal mailbox, specified by the user id.
     */
    private function markUploadAsMailboxAttachment(ApiTester $I, string $uuid, int $userId): void
    {
        // get a random email from the user's mailbox
        $mailboxId = $I->grabFromDatabase('fs_foodsaver', 'mailbox_id', ['id' => $userId]);
        $emails = $I->grabEntriesFromDatabase('fs_mailbox_message', ['mailbox_id' => $mailboxId]);
        $emailId = $emails[random_int(0, sizeof($emails))]['id'];

        // and mark it file as an the attachment of this email
        $I->updateInDatabase('uploads', ['used_in' => UploadUsage::EMAIL_ATTACHMENT->value, 'usage_id' => $emailId], ['uuid' => $uuid]);
    }
}
