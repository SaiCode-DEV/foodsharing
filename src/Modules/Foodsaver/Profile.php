<?php

namespace Foodsharing\Modules\Foodsaver;

use OpenApi\Attributes as OA;

class Profile
{
    #[OA\Property(example: 342528)]
    public int $id;

    #[OA\Property(example: 'Franzi')]
    public string $name;

    #[OA\Property(example: '/api/uploads/99d1fb45-9748-3510-ac02-87b46c40048c')]
    public ?string $avatar = null;

    #[OA\Property(description: 'Whether the user is currently using the sleeping hat function')]
    public ?bool $isSleeping = null;

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

    public static function tryFrom(array $data, string $prefix = ''): ?Profile
    {
        try {
            return new Profile($data, $prefix);
        } catch (\Throwable) {
            return null;
        }
    }
}
