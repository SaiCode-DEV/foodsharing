<?php

namespace Foodsharing\Modules\Core\DBConstants\Region;

class WorkgroupFunction
{
    final public const int WELCOME = 1; // Begrüßungsteam
    final public const int VOTING = 2; // Abstimmung / Wahlen
    final public const int FSP = 3; // Fairteiler / FoodSharePoint
    final public const int STORES_COORDINATION = 4; // Betriebskoordinationsteam
    final public const int REPORT = 5; // Meldebearbeitungsteam
    final public const int MEDIATION = 6; // Mediationsteam
    final public const int ARBITRATION = 7; // Schiedsstelle
    final public const int FSMANAGEMENT = 8; //Foodsaververwaltung
    final public const int PR = 9; // Öffentlichkeitsarbeit
    final public const int MODERATION = 10; // Moderationsteam
    final public const int BOARD = 11; // Vorstand
    final public const int ELECTION = 12; // Wahlen

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
