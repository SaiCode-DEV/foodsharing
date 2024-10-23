<?php

namespace Foodsharing\Modules\Content;

use Foodsharing\Modules\Core\View;

class ContentView extends View
{
    /**
     * @deprecated use ContentEntry.vue instead
     */
    public function simple(array $cnt): string
    {
        return '
		<div class="page-container page-simple">
			<h1>' . $cnt['title'] . '</h1>
			' . $cnt['body'] . '
		</div>';
    }
}
