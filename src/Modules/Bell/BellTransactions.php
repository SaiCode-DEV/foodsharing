<?php

namespace Foodsharing\Modules\Bell;

use Foodsharing\Modules\Bell\DTO\Bell;

class BellTransactions
{
    public function __construct(
        private readonly BellGateway $bellGateway,
    ) {
    }

    /**
     * Adds a bell for a group of users. This bell will replace already existing bells of the same indentifier.
     * This can be used to group multiple events of the same type into one bell to reduce the number of bells.
     *
     * @param ?Bell $baseBell Template for the bell that should be added. Icon, vars and href may be adjusted for the created bells.
     * @param int $entityId The id of the entity for which the bell should be grouped.
     *      This could be a forum thread id for posts in that forum or store id for store wall posts.
     * @param ?string $pluralIcon replacement for the icon of the $baseBell to be used for grouped bells
     */
    public function addGroupedBellEvent(array $foodsaverIds, ?Bell $baseBell = null, int $entityId = 0, ?string $pluralIcon = null): void
    {
        $groups = $this->bellGateway->groupFoodsaversByUnreadBell($foodsaverIds, $baseBell->identifier, true);
        // the grouped bell that follows notifies the same users
        $this->bellGateway->deleteBellsForFoodsaversByIdentifier($foodsaverIds, $baseBell->identifier, true, false);
        foreach ($groups as &$group) {
            $count = 1;
            $href = null;
            if ($group['bellId']) {
                $this->bellGateway->deleteBellForFoodsavers($group['bellId'], $group['foodsaverIds']);
                $vars = unserialize($group['vars']);
                $entityId = $vars['entityId'] ?? $entityId;
                $count = ($vars['count'] ?? 1) + 1;
                $href = unserialize($group['attr'])['href'];
            }
            $bell = $this->createGroupedBell($baseBell, $entityId, $count, $href, $pluralIcon);
            $this->bellGateway->addBellForUsers($group['foodsaverIds'], $bell);
        }
    }

    /**
     * Removes a bell for a group of users. If a grouped bell with the same indentifier exisits, it's count will be decremented instead of removing the bell completely.
     *
     * @param Bell $baseBell Template for the bell that gets used to create decremented bells. Icon, vars and href may be adjusted for the created bells.
     * @param int $entityId The id of the entity for which the bell should be grouped.
     *      This could be a forum thread id for posts in that forum or store id for store wall posts.
     * @param ?string $pluralIcon replacement for the icon of the $baseBell to be used for grouped bells
     */
    public function removeGroupedBellEvent(array $foodsaverIds, Bell $baseBell, int $entityId, ?string $pluralIcon = null): void
    {
        $groups = $this->bellGateway->groupFoodsaversByUnreadBell($foodsaverIds, $baseBell->identifier, false);
        foreach ($groups as &$group) {
            if ($group['bellId']) {
                $vars = unserialize($group['vars']);
                $bellEntityId = $vars['entityId'] ?? null;
                if ($bellEntityId && $bellEntityId <= $entityId) { // Don't remove bells with unknown or newer target
                    $this->bellGateway->deleteBellForFoodsavers($group['bellId'], $group['foodsaverIds']);
                    $count = ($vars['count'] ?? 1) - 1;
                    if ($count > 0) {
                        $bell = $this->createGroupedBell($baseBell, $bellEntityId, $count, null, $pluralIcon);
                        $this->bellGateway->addBellForUsers($group['foodsaverIds'], $bell);
                    }
                }
            }
        }
    }

    private function createGroupedBell(Bell $baseBell, int $entityId, int $count, ?string $href, ?string $pluralIcon): Bell
    {
        $bell = clone $baseBell;
        if ($href) {
            $bell->link_attributes['href'] = $href;
        }
        $bell->vars['count'] = $count;
        $bell->vars['entityId'] = $entityId;
        $bell->body .= ($count == 1 ? '.one' : '.many');
        if ($pluralIcon && $count > 1) {
            $bell->icon = $pluralIcon;
        }

        return $bell;
    }
}
