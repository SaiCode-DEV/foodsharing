<?php

namespace Foodsharing\Modules\Core\DBConstants\Configuration;

/**
 * Keys for key-value pairs in the 'configuration' database table.
 */
enum ConfigurationKey: string
{
    // Keys used by the donation page
    case DONATION_CAMPAIGN_ID = 'campaignId';
    case DONATION_FRIENDSHIP_CIRCLE_ID = 'friendshipCircleId';
    case DONATION_BANNER_ID = 'donationBannerId';
    case DONATION_ONE_TIME_DONATION_ID = 'oneTimeDonationId';
    case DONATION_SHOW_CAMPAIGN_CARD = 'showCampaignCard';
    case DONATION_SHOW_DONATION_BANNER = 'showDonationBanner';
    case DONATION_IFRAME_CAMPAIGN_URL = 'iframeCampaignUrl';
    case DONATION_IFRAME_FRIENDSHIP_CIRCLE_URL = 'iframeFriendshipCircleUrl';
    case DONATION_IFRAME_ONE_TIME_URL = 'iframeOneTimeUrl';

    // Keys used by the daily maintenance and statistics calculation
    case STATISTICS_FOODSAVER_LAST_UPDATE = 'foodsaver_statistics_last_update';
}
