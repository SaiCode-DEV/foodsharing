<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Donation\Query;

use Codeception\Test\Unit;
use Foodsharing\Modules\Donation\Query\TwingleDonationDataQuery;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tests\Support\UnitTester;

class DonationTwingleDataQueryTest extends Unit
{
    protected ?MockObject $httpClient = null;
    protected UnitTester $unitTester;
    private TwingleDonationDataQuery $twingleDonationDataQuery;

    /**
     * @throws Exception
     */
    public function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->twingleDonationDataQuery = new TwingleDonationDataQuery('', httpClient: $this->httpClient);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws Exception
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function testOne()
    {
        $responseData = [
            'amount' => 29810,
            'donators' => 899,
            'percentage' => 29.81,
            'target' => 100000,
            'allow_more' => false,
        ];

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->expects($this->once())
            ->method('toArray')
            ->willReturn($responseData);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($responseMock);

        $twingleData = $this->twingleDonationDataQuery->execute();

        $this->assertIsArray($twingleData);
        $this->assertEquals($responseData, $twingleData);
    }
}
