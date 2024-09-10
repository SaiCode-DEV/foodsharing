<?php

namespace Foodsharing\Modules\Application\DTO;

use Foodsharing\Modules\Foodsaver\Profile;

class WorkingGroupApplication
{
    /**
     * Id of the group for which the user applied.
     */
    public int $groupId;

    /**
     * The user who applied to a working group.
     */
    public Profile $applicant;

    /**
     * The text that the user wrote in the application.
     */
    public string $applicationText;

    public static function create(int $groupId, array $data): WorkingGroupApplication
    {
        $application = new self();
        $application->groupId = $groupId;
        $application->applicant = new Profile($data);
        $application->applicationText = $data['application'];

        return $application;
    }
}
