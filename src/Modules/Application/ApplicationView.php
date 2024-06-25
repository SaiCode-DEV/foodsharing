<?php

namespace Foodsharing\Modules\Application;

use Foodsharing\Modules\Application\DTO\WorkingGroupApplication;
use Foodsharing\Modules\Core\View;
use Foodsharing\Modules\Foodsaver\Profile;

class ApplicationView extends View
{
    private string $groupName;
    private int $groupId;

    public function setGroupName(int $id, string $name)
    {
        $this->groupName = $name;
        $this->groupId = $id;
    }

    public function applicationMenu(Profile $applicant)
    {
        return $this->v_utils->v_menu([
            ['click' => 'tryAcceptApplication(' . $this->groupId . ',' . $applicant->id . ');return false;', 'name' => $this->translator->trans('yes')],
            ['click' => 'tryDeclineApplication(' . $this->groupId . ',' . $applicant->id . ');return false;', 'name' => $this->translator->trans('no')]
        ], $this->translator->trans('group.apply.accept'));
    }

    public function application(WorkingGroupApplication $application)
    {
        $out = $this->headline(
            $this->translator->trans('group.application_region', ['{group}' => $this->groupName]) . ' ' . $this->translator->trans('group.application_from') . ' ' . $application->applicant->name,
            $application->applicant->avatar,
            $application->applicant->id
        );

        $cnt = nl2br($application->applicationText);

        $cnt = $this->v_utils->v_input_wrapper($application->applicant->name, $cnt);
        $cnt .= '<div class="clear"></div>';

        $out .= $this->v_utils->v_field($cnt, $this->translator->trans('group.motivation'), ['class' => 'ui-padding']);

        return $out;
    }

    private function headline(string $title, ?string $img, int $userId): string
    {
        return '
		<div class="welcome ui-padding margin-bottom ui-corner-all">
			<div class="welcome_profile_image">
				<a href="/profile/' . $userId . '">
					<img width="50" height="50" src="' . $this->imageService->img($img) . '">
				</a>
			</div>
			<div class="welcome_profile_name">
				<div class="user_display_name">
					' . $title . '
				</div>
			</div>
		</div>';
    }
}
