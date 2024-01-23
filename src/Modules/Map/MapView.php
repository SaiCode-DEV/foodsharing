<?php

namespace Foodsharing\Modules\Map;

use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\View;

class MapView extends View
{
    public function lMap()
    {
        $this->pageHelper->addHidden('
			<div id="b_content" class="loading">
				<div class="inner">
					' . $this->v_utils->v_input_wrapper($this->translator->trans('status'), $this->translator->trans('map.donates'), 'bcntstatus') . '
					' . $this->v_utils->v_input_wrapper($this->translator->trans('storeview.managers'), '...', 'bcntverantwortlich') . '
					' . $this->v_utils->v_input_wrapper($this->translator->trans('specials'), '...', 'bcntspecial') . '
				</div>
				<input type="hidden" class="fetchbtn" name="fetchbtn" value="' . $this->translator->trans('storeview.want_to_fetch') . '" />
			</div>
		');

        return '<div id="map"></div>';
    }

    public function mapControl()
    {
        return $this->vueComponent('map-control', 'MapControl', [
            'maySeeStores' => $this->session->mayRole(Role::FOODSAVER)
        ]);
    }
}
