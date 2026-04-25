<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Donation\Query;

use Codeception\Test\Unit;
use Foodsharing\Modules\Donation\Query\TwingleDonationDataQuery;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tests\Support\UnitTester;

class DonationTwingleDataQueryTest extends Unit
{
    protected UnitTester $tester;
    private TwingleDonationDataQuery $twingleDonationDataQuery;

    public function _before(): void
    {
        $httpClient = $this->tester->get(HttpClientInterface::class);
        $this->twingleDonationDataQuery = new TwingleDonationDataQuery(httpClient: $httpClient);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function testProjectStatus()
    {
        $twingleData = $this->twingleDonationDataQuery->getProjectStatus(123);

        $this->assertIsArray($twingleData);
        $this->assertArrayHasKey('percentage', $twingleData);
    }

    public function testExceptionForInvalidProject()
    {
        $this->expectException(ServiceUnavailableHttpException::class);
        $this->twingleDonationDataQuery->getProjectStatus(0);
    }
}
