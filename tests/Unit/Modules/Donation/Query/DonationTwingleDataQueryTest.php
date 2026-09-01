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

    /**
     * A deployment that never set the Twingle constants used to raise an Error here,
     * which reached the client as a 500 instead of the documented 503 (#2875).
     */
    public function testMissingConstantIsTreatedAsUnset()
    {
        $config = new \ReflectionMethod(TwingleDonationDataQuery::class, 'config');
        $config->setAccessible(true);

        $this->assertSame('', $config->invoke(null, 'TWINGLE_CONSTANT_THAT_IS_NOT_DEFINED'));
        $this->assertSame((string)TWINGLE_ACCESS_CODE, $config->invoke(null, 'TWINGLE_ACCESS_CODE'));
    }
}
