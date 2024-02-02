<?php

declare(strict_types=1);

namespace Tests\Support\Helper;

use Codeception\Module\Db;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

class FoodsharingUrl extends Db
{
    public function storeUrl($storeId): string
    {
        return '/?page=fsbetrieb&id=' . (int)$storeId;
    }

    public function storeEditUrl($storeId): string
    {
        return '/?page=betrieb&id=' . (int)$storeId . '&a=edit';
    }

    public function storeListUrl($storeId): string
    {
        return '/?page=betrieb&bid=' . (int)$storeId;
    }

    public function storeNewUrl(): string
    {
        return '/?page=betrieb&a=new';
    }

    public function groupEditUrl($groupId): string
    {
        return '/?page=groups&sub=edit&id=' . (int)$groupId;
    }

    public function groupMemberListUrl($groupId): string
    {
        return '/region?sub=members&bid=' . (int)$groupId;
    }

    public function groupListUrl(): string
    {
        return '/?page=groups';
    }

    public function forumThreadUrl($id, $regionId = null): string
    {
        if (!isset($regionId)) {
            $regionId = $this->grabFromDatabase('fs_bezirk_has_theme', 'bezirk_id', ['theme_id' => $id]);
        }

        return '/region?bid=' . (int)$regionId . '&sub=forum&tid=' . (int)$id;
    }

    public function forumUrl($id, $botforum = false): string
    {
        $sub = $botforum ? 'botforum' : 'forum';

        return '/region?bid=' . (int)$id . '&sub=' . $sub;
    }

    public function regionWallUrl($id): string
    {
        return '/region?bid=' . (int)$id . '&sub=wall';
    }

    public function foodSharePointRegionListUrl($region_id): string
    {
        return '/?page=fairteiler&bid=' . (int)$region_id;
    }

    public function foodSharePointGetUrlShort($food_share_point_id): string
    {
        return '/fairteiler/' . (int)$food_share_point_id;
    }

    public function foodSharePointGetUrl($food_share_point_id): string
    {
        return '/?page=fairteiler&sub=ft&id=' . (int)$food_share_point_id;
    }

    public function foodSharePointEditUrl($food_share_point_id): string
    {
        return '/?page=fairteiler&sub=ft&id=' . (int)$food_share_point_id . '&sub=edit';
    }

    public function foodBasketInfoUrl($basket_id): string
    {
        return '/essenskoerbe/' . (int)$basket_id;
    }

    public function settingsUrl(): string
    {
        return '/?page=settings&sub=general';
    }

    public function eventAddUrl($regionId): string
    {
        return '/?page=event&sub=add&bid=' . (int)$regionId;
    }

    public function apiReportListForRegion($regionId): string
    {
        return 'api/report/region/' . (int)$regionId;
    }

    public function upgradeQuizUrl(int $quizRole): string
    {
        $result = '/?page=settings&sub=up_';

        return match ($quizRole) {
            Role::STORE_MANAGER->value => $result . 'bip',
            Role::AMBASSADOR->value => $result . 'bot',
            default => $result . 'fs',
        };
    }
}
