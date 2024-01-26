<?php

namespace Foodsharing\Modules\Team;

use Foodsharing\Modules\Core\View;

class TeamView extends View
{
    public function user($user): string
    {
        $socials = '';

        if ($user['homepage'] != '') {
            $socials .= '<li><a title="' . $this->translator->trans('terminology.homepage') . '" href="' . $user['homepage'] . '" target="_blank"><i class="fas fa-globe"></i></a></li>';
        }

        if (!empty($socials)) {
            $socials = '
			<ul id="team-socials">
				' . $socials . '
			</ul>';
        }

        $photo = $this->fixPhotoPath($user['photo'], '');
        $out = '

		<div id="team-user" class="corner-all">
			<span class="img" style="background-image:url(' . $photo . ');"></span>
			<h1>' . $user['name'] . '</h1>
			<small>' . $user['position'] . '</small>
			<p>' . nl2br((string)$user['desc']) . '</p>

			<span class="foot corner-bottom">
				' . $socials . '
			</span>
		</div>';

        return $out;
    }

    public function contactForm($user): string
    {
        return $this->v_utils->v_quickform('Schreibe ' . $user['name'] . ' eine E-Mail!', [
            $this->v_utils->v_form_text('name'),
            $this->v_utils->v_form_text('email'),
            $this->v_utils->v_form_textarea('message'),
            $this->v_utils->v_form_hidden('id', (int)$user['id'])
        ], ['id' => 'contactform']);
    }

    public function teamList($team, $header): string
    {
        $out = '
		<ul id="team-list" class="linklist">';

        foreach ($team as $t) {
            $socials = '&nbsp;';
            if ($t['homepage'] != '') {
                $socials .= '<i class="fas fa-globe"><span>' . $t['homepage'] . '</span></i>';
            }

            $photoFile = $this->fixPhotoPath($t['photo'], 'q_');

            $out .= '
			<li>
				<a id="t-' . $t['id'] . '" href="/team/' . $t['id'] . '" class="corner-all" target="_self">
					<span class="img" style="background-image:url(' . $photoFile . ');"></span>
					<h3>' . $t['name'] . ' ' . $t['nachname'] . '</h3>
					<span class="subtitle">' . $t['position'] . '</span>
					<span class="desc">
						' . $this->sanitizerService->tt($t['desc'], 240) . '
					</span>
					<span class="foot corner-bottom">
						' . $socials . '
					</span>
				</a>
			</li>';
        }

        $out .= '
		</ul>';

        return $header['body'] . $out;
    }

    /**
     * Returns the correct path to a user's profile photo. The prefix is only used for old photos from the /images
     * directory.
     */
    private function fixPhotoPath(?string $photo, string $prefix): string
    {
        if (empty($photo)) {
            return '';
        }

        return !str_starts_with($photo, '/api') ? '/images/' . $prefix . $photo : $photo;
    }
}
