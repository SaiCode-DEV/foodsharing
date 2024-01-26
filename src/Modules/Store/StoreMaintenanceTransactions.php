<?php

namespace Foodsharing\Modules\Store;

use DateInterval;
use DateTime;
use Foodsharing\Modules\Core\DBConstants\Store\CooperationStatus;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Store\DTO\PickupInformation;
use Foodsharing\Utility\EmailHelper;
use Symfony\Contracts\Translation\TranslatorInterface;

class StoreMaintenanceTransactions
{
    public function __construct(
        private readonly StoreGateway $storeGateway,
        private readonly PickupTransactions $pickupTransactions,
        private readonly EmailHelper $emailHelper,
        private readonly TranslatorInterface $translator,
        private readonly FoodsaverGateway $foodsaverGateway
    ) {
    }

    public function triggerFetchWarningNotification(): array
    {
        $activeStores = $this->storeGateway->getAllStores(
            [CooperationStatus::COOPERATION_STARTING,
            CooperationStatus::COOPERATION_ESTABLISHED]
        );

        $start = new DateTime(); // Now
        $end = (new DateTime())->add(DateInterval::createFromDateString('48 hours')); // 48 hours later

        $foodsavers = [];
        $storesWithNotification = 0;
        $totalCountPickups = 0;
        $totalCountEmptyPickups = 0;

        foreach ($activeStores as $store) {
            $allPickups = $this->pickupTransactions->getPickupsWithUsersForPickupsInRange($store['id'], $start, $end);
            $totalCountPickups += count($allPickups);

            $emptyPickups = array_filter(
                $allPickups,
                fn (PickupInformation $pickup) => !$pickup->hasConfirmedUser()
            );

            $countEmptyPickups = count($emptyPickups);
            $totalCountEmptyPickups += $countEmptyPickups;
            if ($countEmptyPickups != 0) {
                ++$storesWithNotification;

                $storeManagers = $this->storeGateway->getStoreManagers($store['id']);
                foreach ($storeManagers as $foodsaverId) {
                    $foodsavers[] = $foodsaverId;

                    $fs = $this->foodsaverGateway->getFoodsaver($foodsaverId);
                    $this->emailHelper->tplMail('chat/fetch_warning', $fs['email'], [
                        'anrede' => $this->translator->trans('salutation.' . $fs['geschlecht']),
                        'name' => $fs['name'],
                        'betrieb' => $store['name'],
                        'link' => BASE_URL . '/?page=fsbetrieb&id=' . $store['id']
                    ]);
                }
            }
        }

        return [
            'start' => $start->format('c'),
            'end' => $end->format('c'),
            'count_stores' => count($activeStores),
            'count_stores_with_notifications' => $storesWithNotification,
            'count_unique_foodsavers' => count(array_unique($foodsavers)),
            'count_warned_foodsavers' => count($foodsavers),
            'count_total_pickups' => $totalCountPickups,
            'count_total_empty_pickups' => $totalCountEmptyPickups
        ];
    }
}
