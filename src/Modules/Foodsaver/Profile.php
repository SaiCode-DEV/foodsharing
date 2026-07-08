<?php

namespace Foodsharing\Modules\Foodsaver;

use OpenApi\Attributes as OA;

class Profile
{
    #[OA\Property(example: 342528)]
    public int $id;

    #[OA\Property(description: 'Null for a deleted user', example: 'Franzi')]
    public ?string $name;

    #[OA\Property(example: '/api/uploads/99d1fb45-9748-3510-ac02-87b46c40048c')]
    public ?string $avatar = null;

    #[OA\Property(description: 'Whether the user is currently using the sleeping hat function')]
    public ?bool $isSleeping = null;

    public function __construct(int $id, ?string $name, ?string $avatar = null, ?bool $isSleeping = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->avatar = $avatar;
        $this->isSleeping = $isSleeping;
    }
}
