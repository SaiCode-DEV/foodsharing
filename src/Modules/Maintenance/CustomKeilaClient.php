<?php

namespace Foodsharing\Modules\Maintenance;

use Dompie\KeilaApiClient\ApiClientV1;
use Dompie\KeilaApiClient\KeilaResponse;
use Foodsharing\Modules\Foodsaver\DTO\NewsletterSubscriber;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

/**
 * Extension of class ApiClientV1 that allows synchronising external IDs. This class also takes care of the mapping
 * between NewsletterSubscriber objects and Kaila's data format.
 */
class CustomKeilaClient extends ApiClientV1
{
    public function __construct(
        private readonly Client $httpClient,
        string $baseUrl = '',
        string $keilaApiKey = '',
        string $apiPath = 'api'
    ) {
        parent::__construct($httpClient, $baseUrl, $keilaApiKey, $apiPath);
    }

    public function createContact(NewsletterSubscriber $contact, array $customData = []): KeilaResponse
    {
        $data = $this->subscriberToArray($contact, $customData);
        $request = $this->buildKeilaRequest()
            ->withPath('/contacts')
            ->withJsonData($data);

        return KeilaResponse::new($this->httpClient->post($request->getUri(), $request->getOptions()));
    }

    public function patchContact(string $contactId, NewsletterSubscriber $contact, array $customData = []): ResponseInterface
    {
        $data = $this->subscriberToArray($contact, $customData);
        $request = $this->buildKeilaRequest()
            ->withPath('/contacts/' . $contactId)
            ->withJsonData($data);

        return $this->httpClient->patch($request->getUri(), $request->getOptions());
    }

    /**
     * Fetches all contacts from Keila's paginated API. Returns an associative array that maps Keila's internal contact
     * IDs to NewsletterSubscriber objects. The internal IDs are strings and are different from the foodsharer IDs that
     * are stored in the NewsletterSubscriber. In Keila, the foodsaver ID is stored in the contacts as 'external_id'.
     *
     * @return NewsletterSubscriber[]
     */
    public function fetchAllContacts(ApiClientV1 $client): array
    {
        $contacts = [];

        $page = 0;
        do {
            $fetched = 0;
            $response = KeilaResponse::new($client->contactIndex([], $page, 1000));
            if ($response->getGuzzleResponse()->getStatusCode() === 200) {
                $fetched = $response->getDataItemCount();
                foreach ($response->getDataItems() as $item) {
                    $contacts[$item['id']] = $this->arrayToSubscriber($item);
                }
            }
            ++$page;
        } while ($fetched > 0);

        return $contacts;
    }

    private function subscriberToArray(NewsletterSubscriber $contact, array $customData = []): array
    {
        $data = [
            'email' => $contact->email,
            'first_name' => $contact->firstName,
            'external_id' => (string)$contact->id,
        ];
        if (!empty($customData)) {
            $data['data'] = $customData;
        }

        return $data;
    }

    private function arrayToSubscriber(array $data): NewsletterSubscriber
    {
        return new NewsletterSubscriber(intval($data['external_id']), $data['first_name'], $data['email']);
    }
}
