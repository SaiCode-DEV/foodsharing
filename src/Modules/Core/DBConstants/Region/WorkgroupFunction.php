<?php

namespace Foodsharing\Modules\Core\DBConstants\Region;

class WorkgroupFunction
{
    final public const WELCOME = 1; // Begrüßungsteam
    final public const VOTING = 2; // Abstimmung / Wahlen
    final public const FSP = 3; // Fairteiler / FoodSharePoint
    final public const STORES_COORDINATION = 4; // Betriebskoordinationsteam
    final public const REPORT = 5; // Meldebearbeitungsteam
    final public const MEDIATION = 6; // Mediationsteam
    final public const ARBITRATION = 7; // Schiedsstelle
    final public const FSMANAGEMENT = 8; //Foodsaververwaltung
    final public const PR = 9; // Öffentlichkeitsarbeit
    final public const MODERATION = 10; // Moderationsteam
    final public const BOARD = 11; // Vorstand
    final public const ELECTION = 12; // Wahlen

    public static function isValidFunction(int $value): bool
    {
        return in_array($value, range(self::WELCOME, self::ELECTION));
    }

    /**
     * This function determines if a workgroupfunction is a restricted function
     * meaning it should only be edited by the workgroup creation group to ensure
     * votes or other non-programmable logic has been fullfilled.
     *
     * @param int $value Workgroup function value
     *
     * @return bool true if it is restricted, false if it is not
     */
    public static function isRestrictedWorkgroupFunction(int $value): bool
    {
        return in_array($value, [
            self::REPORT,
            self::ARBITRATION,
            self::FSMANAGEMENT,
            self::BOARD,
        ]);
    }
}
