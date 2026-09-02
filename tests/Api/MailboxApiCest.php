<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Example;
use Codeception\Util\HttpCode;
use Faker\Factory;
use Faker\Generator;
use Foodsharing\Modules\Core\DBConstants\Mailbox\MailboxFolder;
use Tests\Support\ApiTester;

/**
 * @group api-group-1
 */
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
        $I->sendGet("api/mailboxes/{$this->ambassadorMailboxId}/folders/{$folder}/mails");
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->login($this->ambassador['email']);
        $I->sendGet("api/mailboxes/{$this->ambassadorMailboxId}/folders/{$folder}/mails");
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    /**
     * @example { "withCc": true, "withBcc": true }
     * @example { "withCc": true, "withBcc": false }
     * @example { "withCc": false, "withBcc": true }
     * @example { "withCc": false, "withBcc": false }
     */
    public function canSendValidEmail(ApiTester $I, Example $example): void
    {
        $I->deleteAllMails();
        $email = $this->createRandomEmail(0, $example['withCc'], $example['withBcc']);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost("api/mailboxes/{$this->ambassadorMailboxId}/mails", $email);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->login($this->ambassador['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost("api/mailboxes/{$this->ambassadorMailboxId}/mails", $email);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->expectNumMails(1, 10);
    }

    /**
     * Regression test for #2469: the body is plain text, so angle brackets have to
     * survive both the mail that is sent out and the copy in the sent folder.
     */
    public function keepsAngleBracketsInTheBody(ApiTester $I): void
    {
        $I->deleteAllMails();
        $body = 'Schreib an <foo@bar.de> und dann geht es hier weiter';
        $email = $this->createRandomEmail(0, false, false);
        $email['body'] = $body;

        $I->login($this->ambassador['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost("api/mailboxes/{$this->ambassadorMailboxId}/mails", $email);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->expectNumMails(1, 10);

        $sentMail = $I->getMails()[0];
        $I->assertStringContainsString('&lt;foo@bar.de&gt;', $sentMail->html);
        $I->assertStringContainsString('<foo@bar.de>', $sentMail->text);

        $I->seeInDatabase('fs_mailbox_message', [
            'mailbox_id' => $this->ambassadorMailboxId,
            'folder' => MailboxFolder::FOLDER_SENT,
            'body' => $body,
        ]);
    }

    public function canNotSendEmailWithNonExistentAttachment(ApiTester $I): void
    {
        // use one random UUID that was not uploaded before
        $email = $this->createRandomEmail(1);

        $I->login($this->ambassador['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost("api/mailboxes/{$this->ambassadorMailboxId}/mails", $email);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function canNotSendEmailWithTooManyAttachments(ApiTester $I): void
    {
        $email = $this->createRandomEmail(11);

        $I->login($this->ambassador['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost("api/mailboxes/{$this->ambassadorMailboxId}/mails", $email);
        $I->seeResponseCodeIs(HttpCode::UNPROCESSABLE_ENTITY);
    }

    public function canGetUnreadMailCount(ApiTester $I): void
    {
        $I->sendGet('api/mailboxes/unread-count');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->login($this->ambassador['email']);
        $I->sendGet('api/mailboxes/unread-count');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseMatchesJsonType([
            'unreadCount' => 'integer'
        ]);
    }

    public function canGetOwnMailboxesWithUnreadCounts(ApiTester $I): void
    {
        $I->sendGet('api/mailboxes');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->login($this->ambassador['email']);
        $I->sendGet('api/mailboxes');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $mailboxes = json_decode($I->grabResponse(), true, flags: JSON_THROW_ON_ERROR);
        $ownMailboxes = array_values(array_filter(
            $mailboxes,
            fn (array $mailbox): bool => $mailbox['id'] === $this->ambassadorMailboxId,
        ));

        $I->assertCount(1, $ownMailboxes);
        $I->assertIsString($ownMailboxes[0]['name']);
        $I->assertIsInt($ownMailboxes[0]['count']);
    }

    private function createRandomEmail(int $numAttachments = 0, bool $withCc = true, bool $withBcc = true): array
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
            'cc' => $withCc ? $this->createRandomEmailAddresses(random_int(1, 2)) : null,
            'bcc' => $withBcc ? $this->createRandomEmailAddresses(random_int(1, 2)) : null,
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
