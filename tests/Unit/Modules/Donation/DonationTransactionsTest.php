<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Donation;

use Codeception\Test\Unit;
use Foodsharing\Modules\Donation\DonationGateway;
use Foodsharing\Modules\Donation\DonationTransactions;
use Foodsharing\Modules\Donation\Query\TwingleDonationDataQuery;
use Foodsharing\RestApi\Models\Donation\DonationDataResponse;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tests\Support\UnitTester;

class DonationTransactionsTest extends Unit
{
    protected UnitTester $tester;

    private const array TWINGLE_STATUS = [
        'amount' => 29810,
        'donators' => 742,
        'percentage' => 29.81,
        'target' => 100000,
        'allow_more' => false,
    ];

    /**
     * A project id of 0 means that the project is not configured. Passing it on to Twingle turned the whole
     * request into a 503, which the client shows as a server error on every page load.
     */
    public function testUnsetProjectsAreNotQueried(): void
    {
        $donationData = new DonationDataResponse();
        $donationData->campaignId = 12573;

        $query = $this->createMock(TwingleDonationDataQuery::class);
        $query->expects($this->once())
            ->method('getProjectStatus')
            ->with(12573)
            ->willReturn(self::TWINGLE_STATUS);

        $information = $this->createTransactions($donationData, $query)->getDonationInformation()->donationInformation;

        $this->assertCount(1, $information);
        $this->assertSame(12573, $information[0]->projectId);
        $this->assertSame(742, $information[0]->donationProjectStatus->donators);
    }

    public function testNoConfiguredProjectGivesEmptyInformation(): void
    {
        $query = $this->createMock(TwingleDonationDataQuery::class);
        $query->expects($this->never())->method('getProjectStatus');

        $information = $this->createTransactions(new DonationDataResponse(), $query)->getDonationInformation()->donationInformation;

        $this->assertSame([], $information);
    }

    private function createTransactions(DonationDataResponse $donationData, TwingleDonationDataQuery $query): DonationTransactions
    {
        $gateway = $this->createMock(DonationGateway::class);
        $gateway->method('getDonationData')->willReturn($donationData);

        return new DonationTransactions(new ArrayAdapter(), $query, $gateway);
    }
}
