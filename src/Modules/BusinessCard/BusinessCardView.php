<?php

namespace Foodsharing\Modules\BusinessCard;

use Foodsharing\Modules\Core\View;

class BusinessCardView extends View
{
    public function top()
    {
        return $this->topbar(
            $this->translator->trans('bcard.card'),
            $this->translator->trans('bcard.claim'),
            '<img src="/img/bcard.png">'
        );
    }

    public function optionForm($selectedData)
    {
        $this->pageHelper->addJs('
            const form = $("#buisnesscard-form");
            const selectBox = $(".input-wrapper .select", form);
			selectBox.on("change", function () {
                form.trigger("submit");
            });
			form.on("submit", function (ev) {
                ev.preventDefault();
                const value = selectBox.val();
                if (value == "") {
                    pulseError(\'' . $this->translator->trans('bcard.choose') . '\');
                } else {
                    goTo("/user/current/settings?sub=makeCard&opt=" + encodeURIComponent(value));
                }
            });
        ');

        return $this->v_utils->v_quickform(
            $this->translator->trans('bcard.actions'),
            [
                $this->v_utils->v_form_select(
                    'businesscard-options',
                    [
                        'desc' => $this->translator->trans('bcard.desc'),
                        'label' => $this->translator->trans('bcard.role'),
                        'values' => $selectedData
                    ]
                ),
            ],
            [
                'id' => 'buisnesscard',
                'submit' => $this->translator->trans('bcard.generate')
            ]
        );
    }
}
