<?php

namespace Foodsharing\Modules\Core\DBConstants\Region;

class WorkgroupFunction
{
    public const WELCOME = 1; // Begrüßungsteam
    public const VOTING = 2; // Abstimmung / Wahlen
    public const FSP = 3; // Fairteiler / FoodSharePoint
    public const STORES_COORDINATION = 4; // Betriebskoordinationsteam
    public const REPORT = 5; // Meldebearbeitungsteam
    public const MEDIATION = 6; // Mediationsteam
    public const ARBITRATION = 7; // Schiedsstelle
    public const FSMANAGEMENT = 8; //Foodsaververwaltung
    public const PR = 9; // Öffentlichkeitsarbeit
    public const MODERATION = 10; // Moderationsteam
    public const BOARD = 11; // Vorstand
    public const ELECTION = 12; // Wahlen

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
