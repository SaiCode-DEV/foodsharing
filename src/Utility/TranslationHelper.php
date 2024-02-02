<?php

namespace Foodsharing\Utility;

use Foodsharing\Modules\Core\DBConstants\Foodsaver\Gender;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;

final class TranslationHelper
{
    public function genderWord(int $gender, string $m, string $f, string $d): string
    {
        if ($gender == Gender::MALE) {
            $out = $m;
        } elseif ($gender == Gender::FEMALE) {
            $out = $f;
        } else {
            $out = $d;
        }

        return $out;
    }

    public function getRoleName(?Role $role, int $gender): string
    {
        $roleName = $role->getRoleName();

        return $this->genderWord($gender,
            'terminology.' . $roleName . '.m',
            'terminology.' . $roleName . '.f',
            'terminology.' . $roleName . '.d'
        );
    }
}
