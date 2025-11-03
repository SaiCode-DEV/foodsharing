<?php

/* If you make changes here check if changes at the production server are needed */

/* Adding Whoops during testing can be very useful as the screenshots in the tests/_output folder can show a nice
     error message. It also catches warnings and the whole site runs in a way that is always throwing warnings out.
     But hopefully we fix all those at some point :)
*/
// Foodsharing\Debug\Whoops::register();

include __DIR__ . '/c3.php';

$protocol = 'http';
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
    $protocol = 'https';
}

$host = 'lmr.local/';

define('SITE_ENVIRONMENT', 'test');

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
define('SESSION_COOKIE_DOMAINS', ['lmr.local']);

define('MAILER_HOST', 'smtp://maildev:1025');

define('MEM_ENABLED', true);

define('SOCK_URL', 'http://websocket:1338/');
define('REDIS_HOST', 'redis');
define('REDIS_PORT', 6379);

define('DELAY_MICRO_SECONDS_BETWEEN_MAILS', 1330000);

define('IMAP', []);
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

define('CSRF_TEST_TOKEN', '__TESTTOKEN__');

define('WEBPUSH_PUBLIC_KEY', 'BGBBW8RtRe4LpGT+6Q7BJGGSbgcULM/w9BrxBLva2AVf85Pj7t4xrViT3lsxn8Dp0fpJ1SPoDbwP1n6gt3/R7ps='); // test public key
define('WEBPUSH_PRIVATE_KEY', 'z5g0ssYryhDhQnwVAZ2Q2oOiqF3ZngJzkLXMrww8gDU='); // test private key

// Test key for firebase cloud messaging
define('FCM_KEY', '');

define('TWINGLE_URL', 'https://spenden.twingle.de/status/E4yxc5T7YJh7nZvL93Yu7PlUzwCMjD2p80u8YK0Vgyw');

define('MAX_DELETE_OLD_ACCOUNTS_PER_DAY', 100);

define('WALLET_LABEL', 'foodsharing');
define('GOOGLE_WALLET_KEY_PATH', __DIR__ . '/keys/google.json');
// If GOOGLE_WALLET_ISSUER_ID is a empty string, then the Google Wallet is deactivated in the backend.
define('GOOGLE_WALLET_ISSUER_ID', '');
define('GOOGLE_WALLET_CLASS_ID', 'foodsharing-passport');

define('APPLE_WALLET_CERTIFICATE_PATH', __DIR__ . '/keys/apple.p12');
define('APPLE_WALLET_CERTIFICATE_PASS', '8Kz9YxgAVFWRmqj9ZT');
define('APPLE_WALLET_TEAM_ID', 'H97D45LYHL');
define('APPLE_WALLET_PASS_TYPE_ID', 'pass.de.foodsharing.passport');
