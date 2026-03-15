<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Modules\Mailbox\MailboxGateway;
use Tests\Support\UnitTester;

class MailboxGatewayTest extends Unit
{
    protected UnitTester $tester;
    private MailboxGateway $gateway;

    private $testMailbox1;
    private $testMailbox2;
    private $testMailbox3;
    private $testMailbox4;

    public function _before()
    {
        $this->gateway = $this->tester->get(MailboxGateway::class);

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
