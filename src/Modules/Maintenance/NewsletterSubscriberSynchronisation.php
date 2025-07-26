<?php

namespace Foodsharing\Modules\Maintenance;

use Carbon\Carbon;
use Foodsharing\Modules\Foodsaver\DTO\NewsletterSubscriber;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Utility\ConsoleHelper;
use GuzzleHttp\Client;

/**
 * This class contains the logic for the one-way synchronisation from the database to Keila. It makes sure that Keila's
 * contact list is a copy of the list of active foodsharers who have subscribed to the newsletter. This class only
 * deals with NewsletterSubscriber objects. The actual mapping to Kaila's API is handled in the CustomKeilaClient class.
 */
class NewsletterSubscriberSynchronisation
{
    /*
     * Update this whenever you add new fields to the NewsletterSubscriber object. This forces the synchronisation to
     * update all existing contacts in Keila.
     * This should be a unix timestamp, together with a human readable date in the comment.
     */
    private const int LAST_SCHEMA_CHANGE_DATE = 1_751_117_609; // 2025-06-28 15:33 UTC

    public function __construct(
        private readonly FoodsaverGateway $foodsaverGateway
    ) {
    }

    public function start(string $serverUrl, string $apiToken): void
    {
        $httpClient = new Client();
        $client = new CustomKeilaClient($httpClient, $serverUrl, $apiToken);

        // Fetch all newsletter subscribers from the database
        $subscribers = $this->foodsaverGateway->getNewsletterSubscribers();
        $subscriberIds = array_map(fn ($s) => $s->id, $subscribers);
        ConsoleHelper::info(count($subscriberIds) . ' subscribers in the database');

        // Fetch all contacts from Keila
        $synchronisedSubscribers = $client->fetchAllContacts($client);
        $synchronisedIds = array_map(fn ($s) => $s->id, $synchronisedSubscribers);
        ConsoleHelper::info(count($synchronisedIds) . ' subscribers in Keila');

        // Find all contacts that are not in the database list anymore. These need to be deleted.
        $contactsNeedDeletion = array_filter($synchronisedSubscribers, fn ($value) => !in_array($value->id, $subscriberIds));
        $deleted = 0;
        foreach ($contactsNeedDeletion as $id => $contact) {
            $response = $client->contactDelete($id);
            if ($response->getStatusCode() === 200) {
                ++$deleted;
            }
        }
        ConsoleHelper::info(count($contactsNeedDeletion) . ' contacts to be deleted, ' . $deleted . ' actually deleted');

        // Find all contacts that are not yet in Keila. These need to be created.
        $contactsToCreate = array_filter($subscribers, fn ($value) => !in_array($value->id, $synchronisedIds));
        $created = 0;
        foreach ($contactsToCreate as $contact) {
            $response = $client->createContact($contact);
            if ($response->getGuzzleResponse()->getStatusCode() === 200) {
                ++$created;
            }
        }
        ConsoleHelper::info(count($contactsToCreate) . ' contacts to be created, ' . $created . ' actually created');

        // If fields were added to or removed from the contact schema, all existing contacts need to be updated in Keila
        $lastSynchronisation = Carbon::now()->subHours(24)->setTimezone('UTC');
        $forceUpdate = $lastSynchronisation->getTimestamp() < self::LAST_SCHEMA_CHANGE_DATE;

        // Find all that need to be updated: only if it exist in both places and if the contact's data has changed
        $contactsNeedUpdate = [];
        foreach ($synchronisedSubscribers as $keilaId => $contact) {
            $original = $this->array_find($subscribers, fn ($s) => $s->id === $contact->id);
            if (!is_null($original) && ($forceUpdate || $this->needsUpdate($original, $contact))) {
                $contactsNeedUpdate[$keilaId] = $original;
            }
        }

        $updated = 0;
        foreach ($contactsNeedUpdate as $id => $contact) {
            $response = $client->patchContact($id, $contact);
            if ($response->getStatusCode() === 200) {
                ++$updated;
            }
        }
        ConsoleHelper::info(count($contactsNeedUpdate) . ' contacts to be updated, ' . $updated . ' actually updated');
    }

    /**
     * Determines if the previously synchronised contact needs to be updated in Keila by comparing it to the original.
     * Both objects have the same foodsharing-id. This function needs to be adapted if additional fields are added to the
     * synchronised contacts.
     *
     * @param NewsletterSubscriber $original the contact from the database
     * @param NewsletterSubscriber $synchronised the contact from Keila
     * @return bool if the contact in Keila needs to be updated
     */
    private function needsUpdate(NewsletterSubscriber $original, NewsletterSubscriber $synchronised): bool
    {
        return $original->firstName !== $synchronised->firstName
            || $original->email !== $synchronised->email;
    }

    /**
     * array_find is only defined in PHP 8.4.
     */
    private function array_find(array $array, callable $callback): mixed
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return null;
    }
}
