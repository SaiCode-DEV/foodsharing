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
    case DONATION_MODAL_ID = 'donationModalId';
    case DONATION_ONE_TIME_DONATION_ID = 'oneTimeDonationId';
    case DONATION_SHOW_CAMPAIGN_CARD = 'showCampaignCard';
    case DONATION_SHOW_DONATION_MODAL = 'showDonationModal';
    case DONATION_SHOW_DONATION_MODAL_IN_HOURS_FOR_LOGGED_IN_USERS = 'showDonationModalInHoursForLoggedInUsers';
    case DONATION_SHOW_DONATION_MODAL_IN_HOURS_FOR_LOGGED_OUT_USERS = 'showDonationModalInHoursForLoggedOutUsers';
    case DONATION_SHOW_CAMPAIGN_PART_1 = 'showDonationCampaignPart1';
    case DONATION_SHOW_CAMPAIGN_PART_2 = 'showDonationCampaignPart2';
    case DONATION_SHOW_CAMPAIGN_GALLERY = 'showDonationCampaignGallery';
    case DONATION_MODAL_INFO_URL = 'donationModalInfoUrl';
    case DONATION_MODAL_POPUP_URL = 'donationModalPopupUrl';
    case DONATION_IFRAME_CAMPAIGN_URL = 'iframeCampaignUrl';
    case DONATION_IFRAME_FRIENDSHIP_CIRCLE_URL = 'iframeFriendshipCircleUrl';
    case DONATION_IFRAME_ONE_TIME_URL = 'iframeOneTimeUrl';
    case DONATION_IFRAME_SELF_SERVICE_URL = 'iframeSelfserviceUrl';

    // Keys used by the daily maintenance and statistics calculation
    case STATISTICS_FOODSAVER_LAST_UPDATE = 'foodsaver_statistics_last_update';
}
