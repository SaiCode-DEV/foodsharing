<?php

namespace Foodsharing\Modules\Settings;

use Foodsharing\Lib\Xhr\XhrDialog;
use Foodsharing\Modules\Core\Control;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;

class SettingsXhr extends Control
{
    private readonly FoodsaverGateway $foodsaverGateway;
    private readonly SettingsGateway $settingsGateway;

    public function __construct(
        SettingsView $view,
        SettingsGateway $settingsGateway,
        FoodsaverGateway $foodsaverGateway,
    ) {
        $this->view = $view;
        $this->foodsaverGateway = $foodsaverGateway;
        $this->settingsGateway = $settingsGateway;

        parent::__construct();

        if (!$this->session->mayRole()) {
            return;
        }
    }

    public function changemail3()
    {
        $email = $this->settingsGateway->getMailChange($this->session->id());
        if (!$email) {
            return;
        }

        $dia = new XhrDialog();
        $dia->setTitle($this->translator->trans('settings.email'));

        $dia->addContent($this->view->changemail3($email));

        $dia->addButton('Abbrechen', 'ajreq(\'abortchangemail\');$(\'#' . $dia->getId() . '\').dialog(\'close\');');
        $dia->addButton('Bestätigen', 'ajreq(\'changemail4\',{did:\'' . $dia->getId() . '\'});');

        return $dia->xhrout();
    }

    public function abortchangemail(): void
    {
        $this->settingsGateway->abortChangemail($this->session->id());
    }

    public function changemail4(): array
    {
        $fsId = $this->session->id();
        $currentEmail = $this->foodsaverGateway->getEmailAddress($fsId);

        if (!$currentEmail) {
            return [
                'status' => 1,
                'script' => '
					pulseError("' . $this->translator->trans('error_unexpected') . '");
				',
            ];
        }

        $newEmail = $this->settingsGateway->getMailChange($fsId);
        if (!$newEmail) {
            return [
                'status' => 1,
                'script' => 'pulseInfo("' . $this->translator->trans('error_unexpected') . '");',
            ];
        }

        if ($this->settingsGateway->changeMail($fsId, $newEmail) == 0) {
            return [
                'status' => 1,
                'script' => 'pulseInfo("' . $this->translator->trans('settings.changemail.occupied') . '");',
            ];
        }

        $this->settingsGateway->logChangedSetting(
            $fsId,
            ['email' => $this->session->user('email')],
            ['email' => $newEmail],
            ['email']
        );
        $dialogId = strip_tags((string)$_GET['did']);

        return [
            'status' => 1,
            'script' => '
				pulseInfo("' . $this->translator->trans('settings.changemail.done') . '");
				$("#' . $dialogId . '").dialog("close");
			',
        ];
    }
}
