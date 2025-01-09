<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Example;
use Codeception\Util\HttpCode;
use Faker\Factory;
use Faker\Generator;
use Foodsharing\Modules\Core\DBConstants\Mailbox\MailboxFolder;
use Tests\Support\ApiTester;

class MailboxApiCest
{
    private const array MAILBOX_FOLDERS = [
        MailboxFolder::FOLDER_INBOX, MailboxFolder::FOLDER_SENT, MailboxFolder::FOLDER_TRASH
    ];

    private Generator $faker;
    private array $ambassador;
    private int $ambassadorMailboxId;

    public function _before(ApiTester $I): void
    {
        $this->faker = Factory::create('de_DE');

        $this->ambassador = $I->createAmbassador();
        $this->ambassadorMailboxId = $I->grabFromDatabase('fs_foodsaver', 'mailbox_id', [
            'id' => $this->ambassador['id']
        ]);
    }

    /**
     * @example [0]
     * @example [1]
     * @example [2]
     */
    public function canReadPersonalMailbox(ApiTester $I, Example $example): void
    {
        $folder = self::MAILBOX_FOLDERS[$example[0]];
        $I->sendGet("api/mailbox/all/$this->ambassadorMailboxId/$folder");
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->login($this->ambassador['email']);
        $I->sendGet("api/mailbox/all/$this->ambassadorMailboxId/$folder");
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function canSendValidEmail(ApiTester $I): void
    {
        $email = $this->createRandomEmail();

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost("api/mailbox/$this->ambassadorMailboxId", $email);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->login($this->ambassador['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost("api/mailbox/$this->ambassadorMailboxId", $email);
        $I->seeResponseCodeIs(HttpCode::CREATED);
    }

    public function canNotSendEmailWithNonExistentAttachment(ApiTester $I): void
    {
        // use one random UUID that was not uploaded before
        $email = $this->createRandomEmail(1);

        $I->login($this->ambassador['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost("api/mailbox/$this->ambassadorMailboxId", $email);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function canNotSendEmailWithTooManyAttachments(ApiTester $I): void
    {
        $email = $this->createRandomEmail(11);

        $I->login($this->ambassador['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost("api/mailbox/$this->ambassadorMailboxId", $email);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    private function createRandomEmail(int $numAttachments = 0): array
    {
        if ($numAttachments < 1) {
            $attachments = null;
        } else {
            $attachments = [];
            for ($i = 0; $i < $numAttachments; ++$i) {
                $attachments[] = $this->createRandomAttachment();
            }
        }

        return [
            'to' => $this->createRandomEmailAddresses(random_int(1, 5)),
            'cc' => $this->createRandomEmailAddresses(random_int(0, 2)),
            'bcc' => $this->createRandomEmailAddresses(random_int(0, 2)),
            'subject' => $this->faker->text(),
            'body' => $this->faker->realTextBetween(100, 200),
            'attachments' => $attachments,
        ];
    }

    private function createRandomEmailAddresses(int $numAddresses): array
    {
        $addresses = [];
        for ($i = 0; $i < $numAddresses; ++$i) {
            $addresses[] = $this->faker->email();
        }

        return $addresses;
    }

    private function createRandomAttachment(): array
    {
        return [
            'uuid' => $this->faker->uuid(),
            'filename' => $this->faker->word() . '.' . $this->faker->fileExtension(),
        ];
    }
}
