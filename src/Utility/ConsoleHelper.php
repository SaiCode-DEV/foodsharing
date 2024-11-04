<?php

namespace Foodsharing\Utility;

class ConsoleHelper
{
    public static function error($msg): void
    {
        if (self::isQuiet()) {
            return;
        }
        echo "\033[31m" . self::cliTime() . " [ERROR]\t" . $msg . " \033[0m\n";
    }

    public static function info($msg): void
    {
        if (self::isQuiet()) {
            return;
        }
        echo '' . self::cliTime() . " [INFO]\t" . $msg . "\n";
    }

    public static function success($msg): void
    {
        if (self::isQuiet()) {
            return;
        }
        echo "\033[32m" . self::cliTime() . " [INFO]\t" . $msg . " \033[0m\n";
    }

    private static function cliTime(): string
    {
        return date('Y-m-d H:i:s');
    }

    private static function isQuiet(): bool
    {
        return defined('QUIET') && QUIET;
    }
}
