<?php

namespace Foodsharing\Modules\Basket;

use Foodsharing\Lib\Session;
use Foodsharing\Lib\View\Utils;
use Foodsharing\Lib\Xhr\XhrDialog;
use Foodsharing\Modules\Core\DBConstants\BasketRequests\Status as RequestStatus;
use Foodsharing\Utility\ImageHelper;
use Foodsharing\Utility\TimeHelper;
use Symfony\Contracts\Translation\TranslatorInterface;

class BasketXhr
{
    public function __construct(
        private readonly Session $session,
        private readonly TranslatorInterface $translator,
        private readonly Utils $v_utils,
        private readonly BasketGateway $basketGateway,
        private readonly TimeHelper $timeHelper,
        private readonly ImageHelper $imageService
    ) {
        // allowed methods for users who are not logged in
        $allowed = [
            'login',
        ];

        if (!$this->session->mayRole() && !in_array($_GET['m'], $allowed)) {
            echo json_encode(
                [
                    'status' => 1,
                    'script' => 'pulseError("' . $this->translator->trans('basket.no-login') . '");',
                ],
                JSON_THROW_ON_ERROR
            );
            exit;
        }
    }

    public function removeRequest(): ?array
    {
        $request = $this->basketGateway->getRequest($_GET['id'], $_GET['fid'], $this->session->id());
        if (!$request) {
            return null;
        }

        $dia = new XhrDialog();

        $dia->addOpt('width', '400');
        $dia->noOverflow();
        $dia->setTitle($this->translator->trans('basket.change-state', ['{name}' => $request['fs_name']]));

        $pronoun = $this->translator->trans('pronoun.' . $request['fs_gender']);
        $dia->addContent(
            '<div>
				<img src="' . $this->imageService->img($request['fs_photo']) . '" style="float: left; margin-right: 10px;">
				<p>' . $this->translator->trans('request_time') . ' '
                . $this->timeHelper->niceDate($request['time_ts'])
                . '</p>
				<div class="clear"></div>
			</div>'
            . $this->v_utils->v_form_radio('fetchstate', [
                'values' => [
                    [
                        'id' => RequestStatus::DELETED_PICKED_UP,
                        'name' => $this->translator->trans('basket.state.okay', ['{pronoun}' => $pronoun]),
                    ],
                    [
                        'id' => RequestStatus::NOT_PICKED_UP,
                        'name' => $this->translator->trans('basket.state.nope', ['{pronoun}' => $pronoun]),
                    ],
                    [
                        'id' => RequestStatus::DELETED_OTHER_REASON,
                        'name' => $this->translator->trans('basket.state.gone'),
                    ],
                    [
                        'id' => RequestStatus::DENIED,
                        'name' => $this->translator->trans('basket.state.deny'),
                    ],
                ],
                'selected' => RequestStatus::DELETED_PICKED_UP,
            ])
        );
        $dia->addAbortButton();
        $dia->addButton($this->translator->trans('button.next'),
            'ajreq(\'finishRequest\',{'
            . 'app: \'basket\','
            . 'id:' . (int)$_GET['id'] . ','
            . 'fid:' . (int)$_GET['fid'] . ','
            . 'sk: $(\'#fetchstate-wrapper input:checked\').val()'
            . '});'
        );

        return $dia->xhrout();
    }

    public function finishRequest(): array
    {
        if (!isset($_GET['sk']) || (int)$_GET['sk'] <= 0) {
            return [
                'status' => 1,
                'script' => 'pulseError("' . $this->translator->trans('error_unexpected') . '");',
            ];
        }

        if ($this->basketGateway->getRequest($_GET['id'], $_GET['fid'], $this->session->id())) {
            $this->basketGateway->setStatus($_GET['id'], $_GET['sk'], $_GET['fid']);

            return [
                'status' => 1,
                'script' => '
					pulseInfo("' . $this->translator->trans('basket.state.finished') . '");
					$(".xhrDialog").dialog("close");
					$(".xhrDialog").dialog("destroy");
					$(".xhrDialog").remove();',
            ];
        } else {
            return [
                'status' => 1,
                'script' => 'pulseError("' . $this->translator->trans('error_unexpected') . '");',
            ];
        }
    }
}
