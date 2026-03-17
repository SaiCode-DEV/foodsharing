<?php

declare(strict_types=1);

namespace Api;

use Codeception\Example;
use Codeception\Util\HttpCode;
use Faker\Factory;
use Foodsharing\Modules\Core\DBConstants\Uploads\UploadUsage;
use Tests\Support\ApiTester;

/**
 * @group api-group-2
 */
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
     * If a file with the same content is uploaded a second time, it should still be assigned a new UUID. The old file
     * should not be overwritten. This must be independent of filename and user.
     *
     * @example {"user1": 0, "user2": 1, "filename1": "text.txt", "filename2": "other.txt"}
     * @example {"user1": 0, "user2": 1, "filename1": "text.txt", "filename2": "text.txt"}
     * @example {"user1": 0, "user2": 0, "filename1": "text.txt", "filename2": "other.txt"}
     * @example {"user1": 0, "user2": 0, "filename1": "text.txt", "filename2": "text.txt"}
     */
    public function canUploadTheSameFileMultipleTImes(ApiTester $I, Example $example): void
    {
        $loginUsers = [$this->user, $this->ambassador];
        $file = base64_encode((string)$this->faker->paragraph());

        // Log in with the first user and upload the file
        $user1 = $loginUsers[$example['user1']];
        $I->login($user1['email']);
        $I->sendPost('api/uploads', ['filename' => $example['filename1'], 'body' => $file]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $uuid1 = $I->grabDataFromResponseByJsonPath('uuid')[0];

        // Log in with the second user (if it is not the same) and upload the file again
        $user2 = $loginUsers[$example['user2']];
        if ($user2['id'] != $user1['id']) {
            $I->login($user2['email']);
        }
        $I->sendPost('api/uploads', ['filename' => $example['filename2'], 'body' => $file]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $uuid2 = $I->grabDataFromResponseByJsonPath('uuid')[0];

        // Check that both files were stored independently
        $I->assertNotEquals($uuid1, $uuid2);
        $I->seeInDatabase('uploads', ['uuid' => $uuid1, 'user_id' => $user1['id']]);
        $I->seeInDatabase('uploads', ['uuid' => $uuid2, 'user_id' => $user2['id']]);
    }

    /**
     * Creates a random file, uploads it to the API, and returns the UUID.
     */
    private function uploadFile(ApiTester $I): void
    {
        $file = $this->faker->paragraph();

        $I->sendPost('api/uploads', [
            'filename' => 'text.txt',
            'body' => base64_encode((string)$file)
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
        $emailId = $emails[random_int(0, count($emails) - 1)]['id'];

        // and mark it file as an the attachment of this email
        $I->updateInDatabase('uploads', ['used_in' => UploadUsage::EMAIL_ATTACHMENT->value, 'usage_id' => $emailId], ['uuid' => $uuid]);
    }
}
