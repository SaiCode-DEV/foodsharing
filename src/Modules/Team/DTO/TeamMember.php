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

    public static function createFromArray(array $data): TeamMember
    {
        $member = new TeamMember();
        $member->id = $data['id'];
        $member->name = $data['name'];
        $member->photo = $data['photo'] ?? null;
        $member->aboutMePublic = $data['about_me_public'];
        $member->position = $data['position'];

        return $member;
    }
}
