<?php

namespace Foodsharing\Modules\Store\DTO;

class MinimalStoreIdentifier
{
    public int $id = 0;
    public string $name = '';

    public static function createFromArray($queryResult, $prefix = '')
    {
        $obj = new MinimalStoreIdentifier();
        $obj->id = $queryResult["{$prefix}id"];
        $obj->name = $queryResult["{$prefix}name"];

        return $obj;
    }
}
