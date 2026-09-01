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
        $accessCode = self::config('TWINGLE_ACCESS_CODE');
        $statusApi = self::config('TWINGLE_PROJECT_STATUS_API');
        if ($accessCode === '' || $statusApi === '' || $projectId <= 0) {
            throw new ServiceUnavailableHttpException('', 'Twingle access code or project ID are not defined');
        }

        return $this->httpClient->request(
            'GET',
            str_replace('{projectId}', (string)$projectId, $statusApi),
            ['headers' => [
                'accept' => 'application/json',
                'x-access-code' => $accessCode,
            ]]
        )->toArray();
    }

    public function getProjects(): array
    {
        $accessCode = self::config('TWINGLE_ACCESS_CODE');
        if ($accessCode === '') {
            throw new ServiceUnavailableHttpException('Twingle access code or project ID are not defined');
        }

        $organizationId = self::config('TWINGLE_ORGANIZATION_ID');
        if ($organizationId === '') {
            throw new ServiceUnavailableHttpException('TWINGLE_ORGANIZATION_ID is not defined');
        }

        $listApi = self::config('TWINGLE_PROJECT_LIST_API');
        if ($listApi === '') {
            throw new ServiceUnavailableHttpException('TWINGLE_PROJECT_LIST_API is not defined');
        }

        return $this->httpClient->request(
            'GET',
            str_replace('{organisationId}', $organizationId, $listApi),
            ['headers' => [
                'accept' => 'application/json',
                'x-access-code' => $accessCode,
            ]]
        )->toArray();
    }

    /**
     * Reads a configuration constant that a deployment may not have set at all.
     * Without this a missing one raises an Error instead of the unavailable
     * response the callers already handle.
     */
    private static function config(string $name): string
    {
        return defined($name) ? (string)constant($name) : '';
    }
}
