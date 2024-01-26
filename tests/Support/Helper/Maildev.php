<?php

declare(strict_types=1);

namespace Tests\Support\Helper;

use Codeception\Module;
use Codeception\TestInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class Maildev extends Module
{
    protected array $requiredFields = ['url'];
    private readonly Client $client;

    public function __construct($moduleContainer, $config = null)
    {
        parent::__construct($moduleContainer, $config);
        $this->client = new Client(['base_uri' => $this->config['url'], 'headers' => ['Accept' => 'application/json']]);
    }

    /**
     * @throws GuzzleException
     * @throws \JsonException
     */
    final public function getMails()
    {
        $responseBody = $this->client->get('/email')->getBody()->getContents();

        return json_decode($responseBody, false, 512, JSON_THROW_ON_ERROR);
    }

    public function _before(TestInterface $test)
    {
        $this->deleteAllMails();
    }

    public function deleteAllMails(): void
    {
        $this->client->delete('/email/all');
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
}
