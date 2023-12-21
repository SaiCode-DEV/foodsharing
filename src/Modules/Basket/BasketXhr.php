<?php

namespace Foodsharing\Modules\Basket;

use Foodsharing\Lib\Xhr\Xhr;
use Foodsharing\Lib\Xhr\XhrDialog;
use Foodsharing\Modules\Core\Control;
use Foodsharing\Modules\Core\DBConstants\BasketRequests\Status as RequestStatus;
use Foodsharing\Utility\ImageHelper;
use Foodsharing\Utility\TimeHelper;

class BasketXhr extends Control
{
    private BasketGateway $basketGateway;
    private TimeHelper $timeHelper;
    private ImageHelper $imageService;

    public function __construct(
        BasketView $view,
        BasketGateway $basketGateway,
        TimeHelper $timeHelper,
        ImageHelper $imageService
    ) {
        $this->view = $view;
        $this->basketGateway = $basketGateway;
        $this->timeHelper = $timeHelper;
        $this->imageService = $imageService;

        parent::__construct();

        // allowed methods for users who are not logged in
        $allowed = [
            'bubble',
            'login',
            'nearbyBaskets',
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

    public function nearbyBaskets(): void
    {
        $xhr = new Xhr();

        if (isset($_GET['coordinates']) && $basket = $this->basketGateway->listNearbyBasketsByDistance(
            $this->session->id(),
            [
                'lat' => $_GET['coordinates'][0],
                'lon' => $_GET['coordinates'][1],
            ]
        )) {
            $xhr->addData('baskets', $basket);
        }

        $xhr->send();
    }

    public function bubble(): array
    {
        $basket = $this->basketGateway->getBasket($_GET['id']);
        if (!$basket) {
            return [
                'status' => 1,
                'script' => 'pulseError("' . $this->translator->trans('basket.error') . '");',
            ];
        }

        if ($basket['fsf_id'] == 0) {
            $dia = new XhrDialog();

            // What does the user see if not logged in?
            if (!$this->session->mayRole()) {
                $dia->setTitle($this->translator->trans('terminology.basket'));
            } else {
                $dia->setTitle($this->translator->trans('basket.by', ['{name}' => $basket['fs_name']]));
            }
            $dia->addContent($this->view->twig->render('partials/vue-wrapper.twig', [
                'id' => 'basket-bubble',
                'component' => 'BasketBubble',
                'props' => [
                    'basketId' => $basket['id'],
                ],
                'initialData' => [],
            ]));

            $modal = false;
            if (isset($_GET['modal'])) {
                $modal = true;
            }
            $dia->addOpt('modal', 'false', $modal);
            $dia->addOpt('resizeable', 'false', false);

            $dia->noOverflow();

            return $dia->xhrout();
        }

        return $this->fsBubble($basket);
    }

    private function fsBubble(array $basket): array
    {
        $dia = new XhrDialog();

        $dia->setTitle($this->translator->trans('basket.on', ['{platform}' => BASE_URL]));

        $dia->addContent($this->view->fsBubble($basket));
        $modal = false;
        if (isset($_GET['modal'])) {
            $modal = true;
        }
        $dia->addOpt('modal', 'false', $modal);
        $dia->addOpt('resizeable', 'false', false);

        $dia->addOpt('width', 400);
        $dia->noOverflow();

        $dia->addJs('$(".fsbutton").button();');

        return $dia->xhrout();
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
