<?php

namespace Foodsharing\Modules\Map\DTO;

use Foodsharing\Modules\Foodsaver\Profile;

class UserMapBubbleData
{
    public Profile $profile;
    public ?string $aboutMeIntern = null;

    public static function create(Profile $profile, ?string $aboutMeIntern): self
    {
        $data = new self();
        $data->profile = $profile;
        $data->aboutMeIntern = $aboutMeIntern;

        return $data;
    }
}
