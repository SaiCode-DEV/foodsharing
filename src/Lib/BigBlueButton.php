<?php

namespace Foodsharing\Lib;

use GuzzleHttp\Client;

class BigBlueButton
{
    final public const string DEFAULT_CLIENT = 'meet.example.org';
    private readonly Client $client;
    private readonly string $url;
    private readonly string $secret;
    private readonly string $dialin;

    public function __construct(string $url, string $secret, string $dialin, Client $client)
    {
        $this->client = $client;
        $this->url = $url;
        $this->secret = $secret;
        $this->dialin = $dialin;
    }

    public function isEnabled(): bool
    {
        return $this->client != self::DEFAULT_CLIENT;
    }

    public function createRoom($roomName, $roomKey, $logoutHost)
    {
        $url = $this->createRoomURL($roomName, $roomKey, $logoutHost);
        try {
            $res = $this->client->get($url)->getBody()->getContents();
            $res = new \SimpleXMLElement($res);

            if ($res->returncode != 'SUCCESS') {
                return null;
            }

            return [
                'dialin' => (string)$res->dialNumber,
                'id' => (string)$res->voiceBridge
            ];
        } catch (\Exception) {
            return null;
        }
    }

    private function createRoomURL($roomName, $roomKey, $logoutHost)
    {
        $params = [
            'name' => $roomName,
            'meetingID' => 'fs-' . $roomKey,
            'attendeePW' => 'ap',
            'moderatorPW' => 'mp',
            'dialNumber' => $this->dialin,
            'logoutURL' => 'https://' . $logoutHost
        ];

        return $this->buildUrl('create', $params);
    }

    public function joinURL($roomKey, $username, $avatar, $isModerator = false)
    {
        $params = [
            'fullName' => $username,
            'avatarURL' => $avatar,
            'meetingID' => 'fs-' . $roomKey,
            'password' => $isModerator ? 'mp' : 'ap',
            'redirect' => 'true'
        ];

        return $this->buildUrl('join', $params);
    }

    private function buildUrl($method, $params)
    {
        $p = http_build_query($params);

        return 'https://' . $this->url . '/bigbluebutton/api/' . $method . '?' . $p . '&checksum=' . sha1($method . $p . $this->secret);
    }
}
