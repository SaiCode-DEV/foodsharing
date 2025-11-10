<?php

namespace Foodsharing\Lib\View;

use Foodsharing\Utility\IdentificationHelper;
use Foodsharing\Utility\PageHelper;
use Symfony\Contracts\Translation\TranslatorInterface;

class Utils
{
    public function __construct(
        private readonly PageHelper $pageHelper,
        private readonly IdentificationHelper $identificationHelper,
        private readonly TranslatorInterface $translator
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

    public function v_menu(array $items, string $title = '', array $option = []): string
    {
        $id = $this->identificationHelper->id('vmenu');

        $out = '<ul class="linklist">';

        foreach ($items as $item) {
            if (!isset($item['href'])) {
                $item['href'] = '#';
            }

            $click = '';
            if (isset($item['click'])) {
                $click = ' onclick="' . $item['click'] . '"';
            }
            $sel = '';
            if ($item['href'] == '?' . $_SERVER['QUERY_STRING']) {
                $sel = ' active';
            }
            $out .= '<li><a class="ui-corner-all' . $sel . '" href="' . $item['href'] . '"' . $click . '>'
                . $item['name']
                . '</a></li>';
        }

        $out .= '</ul>';

        if (!$title) {
            return '
				<div class="ui-widget ui-widget-content ui-corner-all ui-padding">
					' . $out . '
				</div>';
        }

        return '
			<h3 class="head ui-widget-header ui-corner-top">' . $title . '</h3>
			<div class="ui-widget ui-widget-content ui-corner-bottom margin-bottom ui-padding">
				<div id="' . $id . '">
					' . $out . '
				</div>
			</div>';
    }

    public function v_form_radio(string $id, array $option = [], $selectedDefault = ''): string
    {
        $id = $this->identificationHelper->id($id);
        $label = $this->translator->trans($id);

        $this->jsValidate($option, $id, $label);

        $selected = $option['selected'] ?? $selectedDefault;
        $values = $option['values'] ?? [];

        $disabled = '';
        if (isset($option['disabled']) && $option['disabled'] === true) {
            $disabled = 'disabled="disabled" ';
        }

        $out = '';
        if (!empty($values)) {
            foreach ($values as $v) {
                $sel = '';
                if ($selected == $v['id']) {
                    $sel = ' checked="checked"';
                }
                $out .= '
				<label><input name="' . $id . '" type="radio" value="' . $v['id'] . '"' . $sel . ' ' . $disabled . '/>' . $v['name'] . '</label><br />';
            }
        }
        $out .= '';

        return $this->v_input_wrapper($label, $out, $id, $option);
    }

    private function jsValidate(array $option, string $id, $name): array
    {
        $out = ['class' => '', 'msg' => []];

        if (isset($option['required'])) {
            $out['class'] .= ' required';
            if (!isset($option['required']['msg'])) {
                $out['msg']['required'] = $this->translator->trans('validate.required', ['{it}' => $name]);
            }
        }

        return $out;
    }

    public function v_input_wrapper(string $label, string $content, $id = false, array $option = []): string
    {
        if (isset($option['nowrapper'])) {
            return $content;
        }

        if ($id === false) {
            $id = $this->identificationHelper->id('input');
        }
        $star = '';
        $error_msg = '';
        $check = $this->jsValidate($option, $id, $label);

        if (isset($option['required'])) {
            $star = '<span class="req-star"> *</span>';
            if (isset($option['required']['msg'])) {
                $error_msg = $option['required']['msg'];
            } else {
                $error_msg = $this->translator->trans('validate.required', ['{it}' => $label]);
            }
        }

        if (isset($option['label'])) {
            $label = $option['label'];
        }

        if (isset($option['collapse'])) {
            $label = '<i class="fas fa-caret-right"></i> ' . $label;
            $this->pageHelper->addJs('
				$("#' . $id . '-wrapper .element-wrapper").hide();
			');

            $option['click'] = 'collapse_wrapper(\'' . $id . '\')';
        }

        if (isset($option['click'])) {
            $label = '<a href="#" onclick="' . $option['click'] . '; return false;">' . $label . '</a>';
        }

        $label_in = '<label class="wrapper-label ui-widget" for="' . $id . '">' . $label . $star . '</label>';
        if (isset($option['nolabel'])) {
            $label_in = '';
        }

        $desc = '';
        if (isset($option['desc'])) {
            $desc = '<div class="desc">' . $option['desc'] . '</div>';
        }

        if (isset($option['class'])) {
            $check['class'] .= ' ' . $option['class'];
        }

        return '
		<div class="input-wrapper' . $check['class'] . '" id="' . $id . '-wrapper">
		' . $label_in . '
		' . $desc . '
		<div class="element-wrapper">
			' . $content . '
		</div>
		<input type="hidden" id="' . $id . '-error-msg" value="' . $error_msg . '" />
		<div class="clear"></div>
		</div>';
    }

    public function v_field(string $content, $title = false, array $option = [], ?string $titleIcon = null, ?string $titleSpanId = null): string
    {
        $class = '';
        if (isset($option['class'])) {
            $class = ' ' . $option['class'] . '';
        }

        $corner = 'corner-bottom';
        if ($title !== false) {
            $titleHtml = '<div class="head ui-widget-header ui-corner-top">';
            if ($titleSpanId !== null) {
                $titleHtml .= '<span id="' . $titleSpanId . '">';
            }
            if ($titleIcon) {
                $titleHtml .= '<i class="' . $titleIcon . '"></i> ';
            }
            $titleHtml .= htmlspecialchars((string)$title);
            if ($titleSpanId !== null) {
                $titleHtml .= '</span>';
            }
            $titleHtml .= '</div>';
        } else {
            $titleHtml = '';
            $corner = 'corner-all';
        }

        return '
		<div class="field">
			' . $titleHtml . '
			<div class="ui-widget ui-widget-content ' . $corner . ' margin-bottom' . $class . '">
				' . $content . '
			</div>
		</div>';
    }
}
