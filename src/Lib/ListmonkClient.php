<?php

namespace Foodsharing\Lib;

use AdnanHussainTurki\ListMonk\ListMonk;
use Exception;
use GuzzleHttp\Exception\ClientException;
use RuntimeException;

/**
 * This class allows to connect with Listmonk's API in order to (un-)subscribe newsletter recipients. Subscribers and
 * lists are stored in Listmonk's database. There, subscribers can be on multiple (or none) of the lists. Listmonk uses
 * its own internal UUIDs which do not match the foodsaver ID. Instead, when sending requests to the API, we use the
 * e-mail address as a unique identifier.
 *
 * For this class to work, the PHP constants LISTMONK_URL, LISTMONK_USER, LISTMONK_LIST_ID, and LISTMONK_TOKEN need to
 * be defined. If those are not configured most functions in this class do nothing. If it is configured but Listmonk
 * cannot be reached, the functions will throw a RuntimeException.
 *
 * For now, this class only cares about exactly on of the lists, which is defined by LISTMONK_LIST_ID. This means that
 * from the point of view of this class, a user from the main foodsharing database has subscribed to the newsletter if
 * the email address is registered as a subscriber and is a member of that one list.
 */
class ListmonkClient
{
    private ListMonk $client;

    public function __construct()
    {
        $this->client = new ListMonk(LISTMONK_URL, LISTMONK_USER, LISTMONK_TOKEN);
    }

    /**
     * Tests if PHP is configured to connect to Listmonk. In the dev setup, this is not the case and should not create
     * any errors.
     */
    private function isConfigured(): bool
    {
        return !empty($this->client->getServerUrl()) && !empty($this->client->getUsername()) && !empty($this->client->getPassword())
            && defined('LISTMONK_LIST_ID') && intval(LISTMONK_LIST_ID) > 0;
    }

    /**
     * Makes sure that the subscriber with the email address exists in Listmonk and is put on the newsletter list. If
     * the subscriber already exists, nothing happens and the list status is not changed.
     *
     * @param string $name the user name
     * @param string $emailAddress unique email address of the subscriber
     * @throws RuntimeException if Listmonk is not running or if its API returns an error
     */
    public function addSubscriber(string $emailAddress, string $name): void
    {
        if (!$this->isConfigured()) {
            return;
        }

        try {
            $this->client->http('/api/subscribers', 'post', [
                'name' => $name,
                'email' => $emailAddress,
                'status' => 'enabled',
                'lists' => [LISTMONK_LIST_ID],
                'preconfirm_subscriptions' => true,
            ]);
        } catch (ClientException $e) {
            // A 409 response means that the user already exists
            $message = json_decode($e->getMessage(), true) . ', ' . $e->getCode();
            if ($e->getCode() !== 409) {
                throw new RuntimeException($message);
            }
        } catch (Exception $e) {
            // Listmonk cannot be reached or returned an internal error
            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * Removes the email address as a subscriber from Listmonk. If the subscriber does not exists, nothing happens.
     *
     * @param string $emailAddress unique email address of the subscriber
     * @throws RuntimeException
     */
    public function removeSubscriber(string $emailAddress): void
    {
        if (!$this->isConfigured()) {
            return;
        }

        $id = $this->findSubscriber($emailAddress);
        if ($id) {
            try {
                $this->client->http('/api/subscribers/' . $id, 'delete');
            } catch (ClientException $e) {
                // An 404 response means that the user did not exists
                if ($e->getCode() !== 404) {
                    throw new RuntimeException($e->getMessage());
                }
            } catch (Exception $e) {
                // Listmonk cannot be reached or returned an internal error
                throw new RuntimeException($e->getMessage());
            }
        }
    }

    /**
     * Checks if the user is registered in Listmonk and is a member of the list. A user who is registered in Listmonk
     * but not a member of the list is not considered an active subscriber, and in that case this function will return
     * false. It will also return false if the PHP constants are not defined.
     *
     * @param string $emailAddress unique email address of the subscriber
     * @return bool if the user with the e-mail address has an active subscription to the newsletter list
     */
    public function hasSubscribed(string $emailAddress): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $id = $this->findSubscriber($emailAddress);
        if (!$id) {
            return false;
        }

        try {
            $response = $this->client->http('/api/subscribers/' . $id, 'get');
            $decoded = json_decode($response->getContents(), true, flags: JSON_INVALID_UTF8_SUBSTITUTE);
            $lists = $decoded['data']['lists'];

            return array_find($lists, fn ($list) => $list['id'] === LISTMONK_LIST_ID) !== null;
        } catch (ClientException) {
            // The user does not exist
            return false;
        } catch (Exception $e) {
            // Listmonk cannot be reached or returned an internal error
            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * Finds the Linkmonk-internal UUID of the subscriber.
     *
     * @return ?string the UUID or null if the subscription does not exist
     * @throws RuntimeException if Listmonk cannot be reached or returns an internal error
     */
    private function findSubscriber(string $emailAddress): mixed
    {
        $query = urlencode("subscribers.email = '$emailAddress'");
        try {
            $response = $this->client->http('/api/subscribers?query=' . $query, 'get');
            $decoded = json_decode($response->getContents(), true, flags: JSON_INVALID_UTF8_SUBSTITUTE);
            $results = $decoded['data']['results'];

            return sizeof($results) > 0 ? $results[0]['id'] : null;
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage());
        }
    }
}
