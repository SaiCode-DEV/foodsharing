<?php

use Foodsharing\Kernel;
use Foodsharing\Modules\Console\ConsoleControl;

require __DIR__ . '/vendor/autoload.php';

require_once 'config.inc.php';

$env = $_SERVER['FS_ENV'] ?? getenv('FS_ENV') ?? 'dev';
$debug = (bool)($_SERVER['APP_DEBUG'] ?? ('prod' !== $env));
$kernel = new Kernel($env, $debug);
$kernel->boot();

global $container;
$container = $kernel->getContainer();

$app = 'Console';
$method = 'index';

if (isset($argv[3]) && $argv[3] == 'quiet') {
    define('QUIET', true);
}

if (isset($argv) && is_array($argv)) {
    if (count($argv) > 1) {
        $app = $argv[1];
    }
    if (count($argv) > 2) {
        $method = $argv[2];
    }
}

if ($app !== 'Mails' && strtolower($method) !== 'queueworker') {
    echo "run.php is deprecated, please use bin/console or its dev wrapper scripts/symfony-console\n";
    exit(1);
}

$app = '\\Foodsharing\\Modules\\' . $app . '\\' . $app . 'Control';
echo "Starting $app::$method...\n";

$appInstance = $container->get(ltrim($app, '\\'));

if (is_callable([$appInstance, $method]) &&
    $appInstance instanceof ConsoleControl) {
    // all callable commands must inherit from ConsoleControl
    // that way, what can be called is explicit
    $appInstance->$method();
} else {
    echo 'Modul ' . $app . ' konnte nicht geladen werden';
}
