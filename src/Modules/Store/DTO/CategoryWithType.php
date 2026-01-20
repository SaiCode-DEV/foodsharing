<?php

namespace Foodsharing\Modules\Store\DTO;

/**
 * Store category that extends CommonLabel and adds an optional `subType`
 * integer.
 */
class CategoryWithType extends CommonLabel
{
    public function __construct(public int $id = 0, public string $name = '', public ?int $subType = null)
    {
        parent::__construct($id, $name);
    }

    public static function createFromArray(array $data): CategoryWithType
    {
        $obj = new CategoryWithType();
        $obj->id = $data['id'];
        $obj->name = $data['name'];
        $obj->subType = $data['subType'] ?? null;

        return $obj;
    }
}
