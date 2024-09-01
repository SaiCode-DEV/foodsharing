<?php

namespace Foodsharing\Modules\Foodsaver;

class Profile
{
    public int $id;

    public ?string $name;

    public ?string $avatar;

    public ?int $sleepStatus;

    public function __construct(int $id, ?string $name, ?string $avatar, ?int $sleepStatus)
    {
        $this->id = $id;
        $this->name = $name;
        $this->avatar = $avatar;
        if (!is_null($sleepStatus)) {
            $this->sleepStatus = $sleepStatus;
        }
    }

    public static function createFromArray(array $data, string $prefix = ''): ?Profile
    {
        if (!isset($data[$prefix . 'id'])) {
            return null;
        }

        return new self(
            $data[$prefix . 'id'],
            $data[$prefix . 'name'],
            $data[$prefix . 'photo'],
            $data[$prefix . 'sleep_status'] ?? null
        );
    }
}
