<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Donation\Query;

use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TwingleDonationDataQuery
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Returns the raw data from the Twingle server.
     *
     * @return array{"amount": int,"donators": int,"percentage": float,"target": int,"allow_more": bool}
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function getProjectStatus(int $projectId): array
    {
        // @phpstan-ignore-next-line
        if (!empty(TWINGLE_ACCESS_CODE) && !empty($projectId) && $projectId > 0) {
            $headers = [
                'accept' => 'application/json',
                'x-access-code' => TWINGLE_ACCESS_CODE,
            ];

            return $this->httpClient->request(
                'GET',
                str_replace('{projectId}', (string)$projectId, TWINGLE_PROJECT_STATUS_API),
                ['headers' => $headers]
            )->toArray();
        } else {
            throw new ServiceUnavailableHttpException('', 'Twingle access code or project ID are not defined');
        }
    }

    public function getProjects(): array
    {
        // @phpstan-ignore-next-line
        if (empty(TWINGLE_ACCESS_CODE)) {
            throw new ServiceUnavailableHttpException('Twingle access code or project ID are not defined');
        }

        // @phpstan-ignore-next-line
        if (empty(TWINGLE_ORGANIZATION_ID)) {
            throw new ServiceUnavailableHttpException('TWINGLE_ORGANIZATION_ID is not defined');
        }

        $headers = [
            'accept' => 'application/json',
            'x-access-code' => TWINGLE_ACCESS_CODE,
        ];

        return $this->httpClient->request(
            'GET',
            str_replace('{organisationId}', (string)TWINGLE_ORGANIZATION_ID, TWINGLE_PROJECT_LIST_API),
            ['headers' => $headers]
        )->toArray();
    }
}
