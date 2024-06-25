<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Donation\Query;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TwingleDonationDataQuery
{
    public function __construct(
        private readonly string $twingleDonationJsonStatusUrl,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return array{"amount": int,"donators": int,"percentage": float,"target": int,"allow_more": bool}
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function execute(): array
    {
        return $this->httpClient->request('GET', $this->twingleDonationJsonStatusUrl)->toArray();
    }
}
