<?php

namespace Foodsharing\Modules\Team\DTO;

/**
 * Contains information about a user to be displayed on the team page.
 */
class TeamMember
{
    public int $id;

    public string $name;

    public ?string $photo = null;

    public string $aboutMePublic;

    public string $position;

    public static function create(int $id, string $name, ?string $photo, string $aboutMePublic, string $position): TeamMember
    {
        $member = new TeamMember();
        $member->id = $id;
        $member->name = $name;
        $member->photo = $photo;
        $member->aboutMePublic = $aboutMePublic;
        $member->position = $position;

        return $member;
    }
}
