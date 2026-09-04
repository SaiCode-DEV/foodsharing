<?php

declare(strict_types=1);

namespace Tests\Support\Helper;

use Codeception\Module;
use Codeception\TestInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;

class Maildev extends Module
{
    /** Retries for the very first contact, while the service container is still coming up. */
    private const STARTUP_RETRIES = 15;
    /** Retries once the service has answered at least once. */
    private const RETRIES = 2;

    protected array $requiredFields = ['url'];
    private readonly Client $client;
    private bool $maildevReachable = true;
    private bool $everAnswered = false;

    /**
     * Addresses this test expects mail for. maildev has a single inbox, so mail from a
     * previous test that is still in flight lands here too (#2857). Filtering by
     * recipient gives every test its own view of the inbox. Foodsharing::createFoodsharer
     * registers every address it hands out, expectMailTo() covers the rest.
     *
     * @var string[]
     */
    private array $addresses = [];

    public function __construct($moduleContainer, $config = null)
    {
        parent::__construct($moduleContainer, $config);
        $this->client = new Client([
            'base_uri' => $this->config['url'],
            'headers' => ['Accept' => 'application/json'],
            'timeout' => 5,
        ]);
    }

    /**
     * Mails in the inbox, limited to the addresses this test registered. Without any
     * registered address the whole inbox is returned, so a test that never creates a
     * user still sees everything and expectNumMails(0) stays a real check.
     *
     * @throws GuzzleException
     * @throws \JsonException
     */
    final public function getMails(): array
    {
        $responseBody = $this->request('GET', '/email')->getBody()->getContents();
        $mails = json_decode($responseBody, false, 512, JSON_THROW_ON_ERROR);

        if ($this->addresses === []) {
            return $mails;
        }

        return array_values(array_filter($mails, fn ($mail) => $this->isAddressedToThisTest($mail)));
    }

    /**
     * Tell the module which recipients this test is about. Needed when the address does
     * not come from createFoodsaver: a registration attempt, a mail change, a mailbox
     * recipient. Call it before the action that sends the mail.
     */
    public function expectMailTo(string ...$addresses): void
    {
        foreach ($addresses as $address) {
            $this->addresses[] = strtolower($address);
        }
    }

    public function _before(TestInterface $test)
    {
        $this->addresses = [];

        // Once maildev is found unreachable, skip the retrying cleanup for the rest
        // of the run so we don't wait the retry budget before every remaining test.
        if (!$this->maildevReachable) {
            return;
        }

        try {
            $this->deleteAllMails();
        } catch (ConnectException $e) {
            // maildev is a flaky CI service container that occasionally never comes
            // up. Don't let the pre-test mailbox cleanup error every test in the
            // suite over it - skip the cleanup. Tests that actually assert on mail
            // still fail clearly on their own maildev calls.
            $this->maildevReachable = false;
            $this->debug('maildev unreachable, skipping mailbox cleanup for the rest of the run: ' . $e->getMessage());
        }
    }

    public function deleteAllMails(): void
    {
        $this->request('DELETE', '/email/all');
    }

    public function expectNumMails($num, $timeout = 5): void
    {
        if ($timeout) {
            do {
                if (count($this->getMails()) == $num) {
                    return;
                }
                --$timeout;
                sleep(1);
            } while ($timeout > 0);
        }
        $mails = $this->getMails();
        $this->assertCount($num, $mails, $this->describeMails($mails));
    }

    private function isAddressedToThisTest(object $mail): bool
    {
        foreach (['to', 'cc', 'bcc'] as $field) {
            foreach ($mail->$field ?? [] as $recipient) {
                if (in_array(strtolower($recipient->address ?? ''), $this->addresses, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Without this an unexpected mail only shows as a number, and the test that sent
     * it stays unknown. Subject and recipients usually name it right away.
     */
    private function describeMails(array $mails): string
    {
        if ($mails === []) {
            return 'mailbox is empty';
        }

        $lines = array_map(function ($mail): string {
            $to = implode(', ', array_map(
                fn ($recipient) => $recipient->address ?? '?',
                $mail->to ?? []
            ));

            return sprintf('- "%s" to %s', $mail->subject ?? '(no subject)', $to === '' ? '?' : $to);
        }, $mails);

        return "mailbox holds:\n" . implode("\n", $lines);
    }

    /**
     * Sends a request to the maildev service.
     *
     * maildev runs as a CI service container whose DNS alias may not be resolvable the
     * instant the test job starts, so the first contact is given a generous retry budget.
     * After that the budget is small, and once the service has gone away for good every
     * further call fails immediately: the container does die mid-run, and retrying in
     * every call turned a dead service into 30 to 66 minute jobs that ended up failing
     * anyway (#2871).
     *
     * @throws GuzzleException
     */
    private function request(string $method, string $uri): ResponseInterface
    {
        if (!$this->maildevReachable) {
            throw new ConnectException('maildev did not answer earlier in this run, not retrying', new Request($method, $uri));
        }

        $retries = $this->everAnswered ? self::RETRIES : self::STARTUP_RETRIES;
        for ($attempt = 1; $attempt < $retries; ++$attempt) {
            try {
                $response = $this->client->request($method, $uri);
                $this->everAnswered = true;

                return $response;
            } catch (ConnectException) {
                sleep(1);
            }
        }

        try {
            $response = $this->client->request($method, $uri);
            $this->everAnswered = true;

            return $response;
        } catch (ConnectException $e) {
            // Don't let every later call pay the retry budget again.
            $this->maildevReachable = false;
            throw $e;
        }
    }
}
