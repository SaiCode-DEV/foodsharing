<?php

declare(strict_types=1);

namespace Tests\Support\Helper;

use Codeception\Module;
use Codeception\TestInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class Maildev extends Module
{
    protected array $requiredFields = ['url'];
    private readonly Client $client;

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
     * @throws GuzzleException
     * @throws \JsonException
     */
    final public function getMails()
    {
        $responseBody = $this->request('GET', '/email')->getBody()->getContents();

        return json_decode($responseBody, false, 512, JSON_THROW_ON_ERROR);
    }

    public function _before(TestInterface $test)
    {
        $this->deleteAllMails();
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
        $this->assertCount($num, $this->getMails());
    }

    /**
     * Sends a request to the maildev service, retrying while the service is not yet
     * reachable. maildev runs as a CI service container whose DNS alias may not be
     * resolvable the instant the test job starts; without this, every mail-dependent
     * test errors in _before() with "cURL error 6: Could not resolve host: maildev".
     *
     * @throws GuzzleException
     */
    private function request(string $method, string $uri, int $retries = 15): ResponseInterface
    {
        for ($attempt = 1; $attempt < $retries; ++$attempt) {
            try {
                return $this->client->request($method, $uri);
            } catch (ConnectException) {
                sleep(1);
            }
        }

        // Final attempt: let a ConnectException propagate so the test fails with a clear error.
        return $this->client->request($method, $uri);
    }
}
