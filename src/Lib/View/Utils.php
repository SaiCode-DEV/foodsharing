<?php

namespace Foodsharing\Lib\View;

class Utils
{
    public function __construct(
    ) {
    }

    private function v_statusMessage(string $type, string $msg, string $title, string $icon): string
    {
        $title = $title ? '<strong>' . $title . '</strong> ' : '';

        return '<div class="alert alert-' . $type . '">' . $icon . ' ' . $title . $msg . '</div>';
    }

    /**
     * @deprecated Before using this in new code, please consider bootstrap-vue alerts instead:
     * https://bootstrap-vue.org/docs/components/alert
     */
    public function v_info(string $msg, string $title = '', string $icon = ''): string
    {
        $icon = $icon ?: '<i class="fas fa-info-circle"></i>';

        return $this->v_statusMessage('info', $msg, $title, $icon);
    }
}
