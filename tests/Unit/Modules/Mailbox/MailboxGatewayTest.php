<?php

declare(strict_types=1);

namespace Tests\Unit;

use Carbon\Carbon;
use Codeception\Test\Unit;
use Ddeboer\Imap\Message\EmailAddress;
use Foodsharing\Modules\Core\DBConstants\Mailbox\MailboxFolder;
use Foodsharing\Modules\Mailbox\Email;
use Foodsharing\Modules\Mailbox\MailboxGateway;
use Foodsharing\Modules\Mails\MailsGateway;
use Tests\Support\UnitTester;

class MailboxGatewayTest extends Unit
{
    protected UnitTester $tester;
    private MailboxGateway $gateway;
    private MailsGateway $mailsGateway;

    private $testMailbox1;
    private $testMailbox2;
    private $testMailbox3;
    private $testMailbox4;

    public function _before()
    {
        $this->gateway = $this->tester->get(MailboxGateway::class);
        $this->mailsGateway = $this->tester->get(MailsGateway::class);

        $this->testMailbox1 = $this->tester->createMailbox();
        $this->testMailbox2 = $this->tester->createMailbox();
        $this->testMailbox3 = $this->tester->createMailbox();
        $this->testMailbox4 = $this->tester->createMailbox();
    }

    // Verify that getMailboxesWithUnreadCount behaves correctly for multiple, single, and empty mailbox ID inputs
    public function testMailboxesWithUnreadCountImplodeProblem(): void
    {
        $twoMailboxes = $this->gateway->getMailboxesWithUnreadCount([$this->testMailbox2['id'], $this->testMailbox3['id']]);
        $this->tester->assertEquals(2, count($twoMailboxes));

        $oneMailboxes = $this->gateway->getMailboxesWithUnreadCount([$this->testMailbox2['id']]);
        $this->tester->assertEquals(1, count($oneMailboxes));

        $noMailboxes = $this->gateway->getMailboxesWithUnreadCount([]);
        $this->tester->assertEquals(0, count($noMailboxes));
    }

    /**
     * Regression test for #2881: a sent mail keeps angle brackets in its subject.
     */
    public function testSavedMessageKeepsAngleBracketsInSubject(): void
    {
        $subject = 'Schreib an <foo@bar.de> und dann weiter';
        $body = 'Schreib an <foo@bar.de> und dann weiter';
        $email = Email::create(
            -1, $this->testMailbox1['id'], MailboxFolder::FOLDER_SENT,
            new EmailAddress('sender', 'example.com'), [new EmailAddress('receiver', 'example.com')],
            Carbon::now(), $subject, $body, null
        );

        $id = $this->gateway->saveMessage($email);
        $storedEmail = $this->gateway->getEmail($id);

        $this->tester->assertEquals($subject, $storedEmail->subject);
        $this->tester->assertEquals($body, $storedEmail->body);
    }

    /**
     * Regression test for #2881: an incoming mail keeps angle brackets in its subject and body.
     */
    public function testIncomingMessageKeepsAngleBracketsInSubjectAndBody(): void
    {
        $subject = 'Schreib an <foo@bar.de> und dann weiter';
        $body = 'Schreib an <foo@bar.de> und dann weiter';

        $id = $this->mailsGateway->saveMessage(
            $this->testMailbox1['id'],
            MailboxFolder::FOLDER_INBOX,
            json_encode(['mailbox' => 'sender', 'host' => 'example.com']),
            json_encode([['mailbox' => $this->testMailbox1['name'], 'host' => 'example.com']]),
            $subject,
            $body,
            '',
            Carbon::now()->format('Y-m-d H:i:s')
        );
        $storedEmail = $this->gateway->getEmail($id);

        $this->tester->assertEquals($subject, $storedEmail->subject);
        $this->tester->assertEquals($body, $storedEmail->body);
    }

    public function testCreateMailboxNameAvoidingCollisions(): void
    {
        $testedNames = [
            't.est',
            't.est',
            't.est',
            't.estcase',
            't.estcase',
            't.estcase',
            't.est',
            't.est1_',
            't.est1_',
            't.est',
        ];

        $createdNames = [];
        foreach ($testedNames as $name) {
            $id = $this->gateway->createMailbox($name);
            $mailboxName = $this->gateway->getMailboxname($id);
            $createdNames[] = $mailboxName;
        }

        // A unique name shall be created for each tested name
        $this->tester->assertEquals(count($testedNames), count($createdNames));
        $this->tester->assertEquals(count($createdNames), count(array_unique($createdNames)));
    }
}
