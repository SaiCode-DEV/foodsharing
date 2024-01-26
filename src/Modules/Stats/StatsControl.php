<?php

namespace Foodsharing\Modules\Stats;

use Foodsharing\Modules\Console\ConsoleControl;

class StatsControl extends ConsoleControl
{
    private readonly StatsGateway $statsGateway;

    public function __construct(
        StatsGateway $statsGateway
    ) {
        $this->statsGateway = $statsGateway;
        parent::__construct();
    }

    /**
     * Updates all foodsaver related statistics.
     */
    public function foodsaver()
    {
        self::info('Statistik Auswertung für Foodsaver');
        $this->statsGateway->updateFoodsaverStats();
        self::success('foodsaver ready :o)');
    }

    /**
     * Updates all store team related statistics.
     */
    public function betriebe()
    {
        self::info('Statistik Auswertung für Betriebe');
        $this->statsGateway->updateStoreUsersStats();
        self::success('stores ready :o)');
    }

    /**
     * Updates all region related statistics.
     *
     * @param bool $updateIncrementally Whether to use previous stats to speed up calculations
     */
    public function bezirke($updateIncrementally = true)
    {
        self::info('Statistik Auswertung für Bezirke');

        $this->statsGateway->updateNonIncrementalRegionStats();
        if ($updateIncrementally) {
            $this->statsGateway->updateIncrementalRegionStats();
        } else {
            $this->statsGateway->calculateIncrementalRegionStatsFromScratch();
        }

        self::success('region ready :o)');
    }
}
