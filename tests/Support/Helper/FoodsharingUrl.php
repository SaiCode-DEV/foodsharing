<?php

declare(strict_types=1);

namespace Tests\Support\Helper;

use Codeception\Module\Db;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

class FoodsharingUrl extends Db
{
    public function storeUrl($storeId): string
    {
        return '/store/' . (int)$storeId;
    }

    public function storeListUrl($regionId): string
    {
        return '/region/' . (int)$regionId . '/stores';
    }

    public function storeNewUrl($regionId): string
    {
        return '/region/' . (int)$regionId . '/store/new';
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

        return '/region?&sub=' . $sub . '&bid=' . (int)$id;
    }

    public function regionWallUrl($id): string
    {
        return '/region?bid=' . (int)$id . '&sub=wall';
    }

    public function foodSharePointRegionListUrl($region_id): string
    {
        return '/fairteiler?bid=' . (int)$region_id;
    }

    public function foodSharePointGetUrlShort($food_share_point_id): string
    {
        return '/fairteiler/' . (int)$food_share_point_id;
    }

    public function foodSharePointGetUrl($food_share_point_id): string
    {
        return '/fairteiler?sub=ft&id=' . (int)$food_share_point_id;
    }

    public function foodSharePointEditUrl($food_share_point_id): string
    {
        return '/fairteiler?sub=edit&id=' . (int)$food_share_point_id;
    }

    public function foodBasketInfoUrl($basket_id): string
    {
        return '/essenskoerbe/' . (int)$basket_id;
    }

    public function settingsUrl(): string
    {
        return '/user/current/settings?sub=general';
    }

    public function eventAddUrl($regionId): string
    {
        return '/event/add';
    }

    public function apiReportListForRegion($regionId): string
    {
        return 'api/report/region/' . (int)$regionId;
    }
}
