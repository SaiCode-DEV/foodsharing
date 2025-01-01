<?php

namespace Foodsharing\Lib;

use Foodsharing\Lib\Db\Mem;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

use function Sentry\captureException;

/**
 * This class is for handling connections to our WebSocket server.
 *
 * Because it has to handle many connections at the same time, the server written in JavaScript/Node.js and not in PHP.
 * For historical reasons, the docker container containing our WebSocket server has been called "websocket". Its API is
 * provided by websocket/src/RestController.ts.
 */
class WebSocketConnection
{
    private const DEFAULT_TIMEOUT = 30; // sending timeout in seconds

    public function __construct(private readonly Client $guzzle, private readonly Mem $mem)
    {
    }

    /*
     * Registrates the session id for the websocket server.
     *
     * Add entry into user -> session set
     */
    public function registerSession(int $foodsaverId, string $sessionId)
    {
        $this->mem->userAddSession($foodsaverId, $sessionId);
    }

    public function unregisterSession(int $foodsaverId, string $sessionId)
    {
        $this->mem->userRemoveSession($foodsaverId, $sessionId);
    }

    private function post($url, $options): void
    {
        try {
            $this->guzzle->post($url, $options);
        } catch (\Exception $e) {
            captureException($e);
        }
    }

    public function sendSock(int $fsid, string $app, string $method, array $options): void
    {
        $url = SOCK_URL . 'users/' . $fsid . '/' . $app . '/' . $method;
        $this->post($url, [RequestOptions::JSON => $options, RequestOptions::TIMEOUT => self::DEFAULT_TIMEOUT]);
    }

    public function sendSockMulti(array $fsids, string $app, string $method, array $options): void
    {
        $url = SOCK_URL . 'users/' . join(',', $fsids) . '/' . $app . '/' . $method;
        $this->post($url, [RequestOptions::JSON => $options, RequestOptions::TIMEOUT => self::DEFAULT_TIMEOUT]);
    }

    public function isUserOnline(int $fsid): bool
    {
        try {
            $userIsOnline = $this->guzzle->get(SOCK_URL . 'users/' . $fsid . '/is-online')->getBody()->getContents();

            if ($userIsOnline === 'true') {
                return true;
            }
        } catch (\Exception $e) {
            captureException($e);
        }

        return false;
    }
}
