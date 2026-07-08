<?php

namespace Foodsharing\Modules\Report\DTO;

class ReportReminderInfo
{
    public int $id;
    public int $foodsaverId;
    public int $regionId;

    public static function createFromArray(array $data): self
    {
        $obj = new self();
        $obj->id = (int)$data['id'];
        $obj->foodsaverId = (int)$data['foodsaver_id'];
        $obj->regionId = (int)$data['regionId'];

        return $obj;
    }
}
