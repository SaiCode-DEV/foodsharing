<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Donation;

use OpenApi\Attributes as OA;

class DonationData
{
    #[OA\Property(description: 'Campaign ID', example: 12345)]
    public int $campaignId = 0;

    #[OA\Property(description: 'Friendship Circle ID', example: 12345)]
    public int $friendshipCircleId = 0;

    #[OA\Property(description: 'Donation Modal ID', example: 12345)]
    public int $donationModalId = 0;

    #[OA\Property(description: 'One-Time Donation ID', example: 12345)]
    public int $oneTimeDonationId = 0;

    #[OA\Property(description: 'Show Campaign Card', example: true)]
    public bool $showCampaignCard = false;

    #[OA\Property(description: 'Show Donation Modal', example: false)]
    public bool $showDonationModal = false;

    #[OA\Property(description: 'Show Donation Modal in Hours for Logged In Users', example: 24)]
    public int $showDonationModalInHoursForLoggedInUsers = 0;

    #[OA\Property(description: 'Show Donation Modal in Hours for Logged Out Users', example: 1)]
    public int $showDonationModalInHoursForLoggedOutUsers = 0;

    #[OA\Property(description: 'Donation Modal Info URL', example: 'donation/campaign')]
    public string $donationModalInfoUrl = '';

    #[OA\Property(
        description: 'Donation Popup Modal URL',
        example: 'spendenkampagne-ueberregionale-arbeit/tw65a581c764fa1',
    )]
    public string $donationModalPopupUrl = '';

    #[OA\Property(description: 'Iframe Friendship Circle URL', example: 'freundeskreis/tw5ba5f44dcb36f')]
    public string $iframeFriendshipCircleUrl = '';

    #[OA\Property(description: 'Iframe One-Time URL', example: 'one-time-donation/abcdefg123')]
    public string $iframeOneTimeUrl = '';

    #[OA\Property(description: 'Iframe Campaign URL', example: 'spendenkampagne-ueberregionale-arbeit/abcdefg123')]
    public string $iframeCampaignUrl = '';

    #[OA\Property(description: 'Iframe Self-Service URL', example: 'https://spenden.twingle.de/selfservice/ABCDEFG123')]
    public string $iframeSelfserviceUrl = '';

    #[OA\Property(description: 'Show Donation Campaign Part 1', example: true)]
    public bool $showDonationCampaignPart1 = false;

    #[OA\Property(description: 'Show Donation Campaign Part 2', example: true)]
    public bool $showDonationCampaignPart2 = false;

    #[OA\Property(description: 'Show Donation Campaign Gallery', example: true)]
    public bool $showDonationCampaignGallery = false;
}
