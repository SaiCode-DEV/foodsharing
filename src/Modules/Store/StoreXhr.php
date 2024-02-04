<?php

namespace Foodsharing\Modules\Store;

use Carbon\Carbon;
use Foodsharing\Lib\Xhr\Xhr;
use Foodsharing\Lib\Xhr\XhrDialog;
use Foodsharing\Lib\Xhr\XhrResponses;
use Foodsharing\Modules\Core\Control;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Store\StoreLogAction;
use Foodsharing\Modules\Store\DTO\OneTimePickup;
use Foodsharing\Permissions\StorePermissions;

class StoreXhr extends Control
{
    private $storeGateway;
    private $storePermissions;
    private $storeTransactions;

    public function __construct(
        StoreView $view,
        StoreGateway $storeGateway,
        StorePermissions $storePermissions,
        StoreTransactions $storeTransactions
    ) {
        $this->view = $view;
        $this->storeGateway = $storeGateway;
        $this->storePermissions = $storePermissions;
        $this->storeTransactions = $storeTransactions;

        parent::__construct();

        if (!$this->session->mayRole(Role::FOODSAVER)) {
            exit;
        }
    }

    public function savedate()
    {
        $storeId = (int)$_GET['bid'];
        if (!$this->storePermissions->mayAddPickup($storeId)) {
            return XhrResponses::PERMISSION_DENIED;
        }

        if (strtotime((string)$_GET['time']) == false) {
            return;
        }
        $date = Carbon::createFromTimeString($_GET['time']);

        $totalSlots = $_GET['fetchercount'];
        if (!is_numeric($totalSlots)) {
            return;
        }

        try {
            $pickup = new OneTimePickup();
            $pickup->date = $date;
            $pickup->slots = $totalSlots;

            $this->storeTransactions->createOrUpdatePickup($storeId, $pickup);
            $this->flashMessageHelper->success($this->translator->trans('pickup.edit.added'));

            return [
                'status' => 1,
                'script' => 'reload();'
            ];
        } catch (PickupValidationException) {
        }
    }

    public function signout()
    {
        $xhr = new Xhr();
        $status = $this->storeGateway->getUserTeamStatus($this->session->id(), $_GET['id']);
        if ($status === TeamStatus::Coordinator) {
            $xhr->addMessage($this->translator->trans('storeedit.team.cannot-leave'), 'error');
        } elseif ($status >= TeamStatus::Applied) {
            $storeId = intval($_GET['id']);
            $userId = $this->session->id();
            if (is_null($userId)) {
                return XhrResponses::PERMISSION_DENIED;
            }
            $this->storeTransactions->removeStoreMember($storeId, $userId);
            $this->storeGateway->addStoreLog($storeId, $userId, null, null, StoreLogAction::LEFT_STORE);
            $xhr->addScript('goTo("/?page=relogin&url=" + encodeURIComponent("/?page=dashboard") );');
        } else {
            $xhr->addMessage($this->translator->trans('store.not-in-team'), 'error');
        }
        $xhr->send();
    }

    public function bubble(): array
    {
        $storeId = intval($_GET['id']);
        if ($store = $this->storeGateway->getMyStore($this->session->id(), $storeId)) {
            $dia = $this->buildBubbleDialog($store, $storeId);

            return $dia->xhrout();
        }

        return [
                'status' => 1,
                'script' => 'pulseError("' . $this->translator->trans('store.error') . '");',
        ];
    }

    private function buildBubbleDialog(array $store, int $storeId): XhrDialog
    {
        $teamStatus = $this->storeGateway->getUserTeamStatus($this->session->id(), $storeId);
        $store['inTeam'] = $teamStatus > TeamStatus::Applied;
        $store['pendingRequest'] = $teamStatus == TeamStatus::Applied;
        $dia = new XhrDialog();
        $dia->setTitle($store['name']);
        $dia->addContent($this->view->vueComponent('store-bubble', 'StoreBubble', [
            'storeId' => $storeId,
        ]));

        $modal = false;
        if (isset($_GET['modal'])) {
            $modal = true;
        }
        $dia->addOpt('modal', 'false', $modal);
        $dia->addOpt('resizeable', 'false', false);
        $dia->noOverflow();

        return $dia;
    }
}
