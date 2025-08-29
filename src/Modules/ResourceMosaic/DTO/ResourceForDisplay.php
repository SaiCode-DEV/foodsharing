<?php

declare(strict_types=1);

namespace Foodsharing\Modules\ResourceMosaic\DTO;

use Foodsharing\Modules\Foodsaver\Profile;

class ResourceForDisplay
{
    public int $id;
    public string $name;
    public ?string $description;
    public array $categories;
    public Profile $user;
    public bool $isHomeRegion;
    public bool $isUserActive;
    public bool $isPrivate;
    public bool $isFavorite;
    public int $openness;
    public array $images;
}
