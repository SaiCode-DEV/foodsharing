<?php

namespace Foodsharing\Modules\Store;

use Carbon\Carbon;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\UserOptionType;
use Foodsharing\Modules\Core\DBConstants\Store\CooperationStatus;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Settings\SettingsGateway;
use Foodsharing\Utility\EmailHelper;
use Symfony\Contracts\Translation\TranslatorInterface;

class StoreMaintenanceTransactions
{
    public function __construct(
        private readonly StoreGateway $storeGateway,
        private readonly EmailHelper $emailHelper,
        private readonly TranslatorInterface $translator,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly SettingsGateway $settingsGateway,
        private readonly PickupGateway $pickupGateway,
    ) {
    }

    public function triggerFetchWarningNotification(): array
    {
        $activeStores = $this->storeGateway->getAllStores([CooperationStatus::COOPERATION_ESTABLISHED]);

        $start = Carbon::now();
        $end = $start->copy()->addDays(2);
        $fetchWarnings = [];
        $storesWithNotification = 0;
        $mailsDisabledViaSettings = 0;

        // collect empty slot information:
        foreach ($activeStores as $store) {
            $pickups = $this->pickupGateway->getPickupSlots($store['id'], $start, $end, $end);
            $occupiedSlots = array_sum(array_map('count', array_column($pickups, 'occupiedSlots')));
            $emptySlots = array_sum(array_column($pickups, 'totalSlots')) - $occupiedSlots;

            if ($emptySlots > 0) {
                ++$storesWithNotification;
                $storeManagers = $this->storeGateway->getStoreManagers($store['id']);
                foreach ($storeManagers as $storeManagerId) {
                    if (!isset($fetchWarnings[$storeManagerId])) {
                        $storeManager = $this->foodsaverGateway->getFoodsaver($storeManagerId);
                        $fetchWarnings[$storeManagerId] = [
                            'email' => $storeManager['email'],
                            'anrede' => $this->translator->trans('salutation.' . $storeManager['geschlecht']),
                            'name' => $storeManager['name'],
                            'stores' => [],
                        ];
                    }
                    $fetchWarnings[$storeManagerId]['stores'][] = [
                        'link' => BASE_URL . '/store/' . $store['id'],
                        'name' => $store['name'],
                        'count' => $emptySlots
                    ];
                }
            }
        }

        // Remove mails for users with disabled pickup reminder:
        $storeManagerIds = array_keys($fetchWarnings);
        $batchedIds = array_chunk($storeManagerIds, 100);
        foreach ($batchedIds as $batch) {
            $disabledSettings = $this->settingsGateway->getUsersOption($batch, UserOptionType::DISABLE_PICKUP_REMINDER);
            foreach ($disabledSettings as $setting) {
                if ($setting['option']) {
                    unset($fetchWarnings[$setting['userId']]);
                    ++$mailsDisabledViaSettings;
                }
            }
        }

        // Send mails:
        foreach ($fetchWarnings as $storeManagerId => $fetchWarning) {
            $this->emailHelper->tplMail('chat/fetch_warning', $fetchWarning['email'],
                $fetchWarning + ['settings' => BASE_URL . '/user/current/settings?sub=info']);
        }

        return [
            'start' => $start->format('c'),
            'end' => $end->format('c'),
            'stores checked' => count($activeStores),
            'stores with notifications' => $storesWithNotification,
            'mails disabled via settings' => $mailsDisabledViaSettings,
            'warned foodsavers' => count($fetchWarnings),
        ];
    }
}
