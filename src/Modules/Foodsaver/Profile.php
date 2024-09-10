<?php

namespace Foodsharing\Modules\Foodsaver;

class Profile
{
    public int $id;
    public ?string $name = null;
    public ?string $avatar = null;
    public ?bool $isSleeping;

    public function __construct(array $data, string $prefix = '')
    {
        $this->id = $data[$prefix . 'id'];
        if (isset($data[$prefix . 'name'])) {
            $this->name = $data[$prefix . 'name'];
        }
        if (isset($data[$prefix . 'photo'])) {
            $this->avatar = $data[$prefix . 'photo'];
        }
        if (isset($data[$prefix . 'is_sleeping']) && !is_null($data[$prefix . 'is_sleeping'])) {
            $this->isSleeping = $data[$prefix . 'is_sleeping'];
        }
    }
}
