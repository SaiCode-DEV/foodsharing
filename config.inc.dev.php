<?php

/* If you make changes here check if changes at the production server are needed */

$protocol = 'http';
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
    $protocol = 'https';
}

define('SITE_ENVIRONMENT', 'development');

$host = 'localhost:18080';

define('PROTOCOL', $protocol);
define('DB_HOST', 'db');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_DB', 'foodsharing');
define('ERROR_REPORT', E_ALL);
define('BASE_URL', $protocol . '://' . $host);

define('VERSION', '0.8.3');

define('DEFAULT_EMAIL', 'no-reply@foodsharing.network');
define('SUPPORT_EMAIL', 'support@foodsharing.network');
define('DEFAULT_EMAIL_NAME', 'Foodsharing');
define('EMAIL_PUBLIC', 'info@foodsharing.de');
define('EMAIL_PUBLIC_NAME', 'Foodsharing');
define('PLATFORM_MAILBOX_HOST', 'foodsharing.network');

define('MAILBOX_OWN_DOMAINS', ['foodsharing.network', 'lebensmittelretten.de', 'foodsharing.de']);

define('MAILER_HOST', 'smtp://maildev:1025');

define('MEM_ENABLED', true);

define('SOCK_URL', 'http://websocket:1338/');
define('REDIS_HOST', 'redis');
define('REDIS_PORT', 6379);

define('DELAY_MICRO_SECONDS_BETWEEN_MAILS', 1330000);

define('IMAP', [
    ['host' => 'imap', 'user' => 'user', 'password' => 'pass']
]);
define('IMAP_FAILED_BOX', 'INBOX/FailedProcessing');

define('BOUNCE_IMAP_HOST', null);
define('BOUNCE_IMAP_USER', null);
define('BOUNCE_IMAP_PASS', null);
define('BOUNCE_IMAP_PORT', null);
define('BOUNCE_IMAP_SERVICE_OPTION', null);
define('BOUNCE_IMAP_UNPROCESSED_BOX', null);

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', './');
}

define('WEBPUSH_PUBLIC_KEY', 'BGBBW8RtRe4LpGT+6Q7BJGGSbgcULM/w9BrxBLva2AVf85Pj7t4xrViT3lsxn8Dp0fpJ1SPoDbwP1n6gt3/R7ps='); // test public key
define('WEBPUSH_PRIVATE_KEY', 'z5g0ssYryhDhQnwVAZ2Q2oOiqF3ZngJzkLXMrww8gDU='); // test private key

// # Geoapify map tiles
define('GEOAPIFY_API_KEY', 'b4e6bf0dbc48447fb4ee29d77c08eb09');

// Test key for firebase cloud messaging
define('FCM_KEY', '');

define('TWINGLE_URL', 'https://spenden.twingle.de/status/E4yxc5T7YJh7nZvL93Yu7PlUzwCMjD2p80u8YK0Vgyw');

define('MAX_DELETE_OLD_ACCOUNTS_PER_DAY', 100);

define('ZAMMAD_URL', 'http://zammad-nginx:8080');
define('ZAMMAD_TICKET_TOKEN', 'bDH0R-1hbTHL3c4Rd8JECX--YLMIFufIJ3BOEd8W7lW8zlPz_IMya2Te22pxbkvF');

define('BUNDESTAG_PETITION_PAGE_URL', 'http://nginx:8080/mock/petition');

define('WALLET_LABEL', 'foodsharing');
define('GOOGLE_WALLET_KEY_PATH', __DIR__ . '/keys/google.json');
// If GOOGLE_WALLET_ISSUER_ID is a empty string, then the Google Wallet is deactivated in the backend.
define('GOOGLE_WALLET_ISSUER_ID', '');
define('GOOGLE_WALLET_CLASS_ID', 'foodsharing-passport');

define('APPLE_WALLET_CERTIFICATE_PATH', __DIR__ . '/keys/apple.p12');
define('APPLE_WALLET_CERTIFICATE_PASS', '8Kz9YxgAVFWRmqj9ZT');
define('APPLE_WALLET_TEAM_ID', 'H97D45LYHL');
define('APPLE_WALLET_PASS_TYPE_ID', 'pass.de.foodsharing.passport');
