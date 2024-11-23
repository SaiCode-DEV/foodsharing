<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Content\ContentId;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

final class ContentPermissions
{
    private readonly Session $session;

    private array $PR_PARTNER_CONTENT_IDS = [
        ContentId::PARTNER_PAGE_10,
        ContentId::PARTNER_PAGE_AU_79,
    ];

    private array $QUIZ_CONTENT_IDS = [
        ContentId::QUIZ_DESCRIPTION_PAGE_12,
        ContentId::QUIZ_FAILED_PAGE_13,
        ContentId::QUIZ_CONFIRM_FS_PAGE_14,
        ContentId::QUIZ_CONFIRM_SM_PAGE_15,
        ContentId::QUIZ_CONFIRM_AMB_PAGE_16,
        ContentId::QUIZ_FAILED_FS_TRY_1_PAGE_19,
        ContentId::QUIZ_FAILED_FS_TRY_2_PAGE_20,
        ContentId::QUIZ_FAILED_FS_TRY_3_PAGE_21,
        ContentId::QUIZ_FAILED_SM_TRY_1_PAGE_22,
        ContentId::QUIZ_FAILED_SM_TRY_2_PAGE_23,
        ContentId::QUIZ_FAILED_SM_TRY_3_PAGE_24,
        ContentId::QUIZ_FAILED_AMB_TRY_1_PAGE_25,
        ContentId::QUIZ_FAILED_AMB_TRY_2_PAGE_26,
        ContentId::QUIZ_FAILED_AMB_TRY_3_PAGE_27,
        ContentId::QUIZ_REMARK_PAGE_33,
        ContentId::QUIZ_POPUP_SM_PAGE_34,
        ContentId::QUIZ_POPUP_AMB_PAGE_35,
    ];

    private array $START_CONTENT_IDS = [
        ContentId::STARTPAGE_BLOCK1_DE,
        ContentId::STARTPAGE_BLOCK2_DE,
        ContentId::STARTPAGE_BLOCK3_DE,
        ContentId::STARTPAGE_BLOCK1_BETA,
        ContentId::STARTPAGE_BLOCK2_BETA,
        ContentId::STARTPAGE_BLOCK3_BETA,
        ContentId::STARTPAGE_BLOCK1_AT,
        ContentId::STARTPAGE_BLOCK2_AT,
        ContentId::STARTPAGE_BLOCK3_AT,
        ContentId::STARTPAGE_BLOCK1_CH,
        ContentId::STARTPAGE_BLOCK2_CH,
        ContentId::STARTPAGE_BLOCK3_CH,
    ];

    private array $IT_CONTENT_IDS = [
        ContentId::SECURITY_PAGE,
        ContentId::BROADCAST_MESSAGE,
    ];

    private array $POLITICAL_CAMPAIGNS_CONTENT_IDS = [
        ContentId::PETITION_BANNER,
    ];

    public function __construct(Session $session, private readonly CurrentUserUnitsInterface $currentUserUnits)
    {
        $this->session = $session;
    }

    public function mayEditContent(): bool
    {
        return $this->session->mayRole(Role::ORGA)
            || $this->currentUserUnits->isAdminFor(RegionIDs::EDITORIAL_GROUP)
            || $this->currentUserUnits->isAdminFor(RegionIDs::QUIZ_AND_REGISTRATION_WORK_GROUP)
            || $this->currentUserUnits->isAdminFor(RegionIDs::PR_PARTNER_AND_TEAM_WORK_GROUP)
            || $this->currentUserUnits->isAdminFor(RegionIDs::IT_SUPPORT_GROUP)
            || $this->currentUserUnits->isAdminFor(RegionIDs::PR_START_PAGE)
            || $this->currentUserUnits->isAdminFor(RegionIDs::POLITICAL_CAMPAIGNS);
    }

    /**
     * Returns a list of content IDs that the user may edit, or null indicating that the user may edit all content
     * pages.
     *
     * @return int[]|null
     */
    public function getEditableContentIds(): ?array
    {
        if ($this->session->mayRole(Role::ORGA)
            || $this->currentUserUnits->isAdminFor(RegionIDs::EDITORIAL_GROUP)) {
            return null;
        }

        $regionContentMap = [
            RegionIDs::QUIZ_AND_REGISTRATION_WORK_GROUP => $this->QUIZ_CONTENT_IDS,
            RegionIDs::PR_PARTNER_AND_TEAM_WORK_GROUP => $this->PR_PARTNER_CONTENT_IDS,
            RegionIDs::IT_SUPPORT_GROUP => $this->IT_CONTENT_IDS,
            RegionIDs::PR_START_PAGE => $this->START_CONTENT_IDS,
            RegionIDs::POLITICAL_CAMPAIGNS => $this->POLITICAL_CAMPAIGNS_CONTENT_IDS,
        ];

        $ids = [];

        foreach ($regionContentMap as $regionID => $contentIDs) {
            if ($this->currentUserUnits->isAdminFor($regionID)) {
                $ids = array_merge($ids, $contentIDs);
            }
        }

        return $ids;
    }

    public function mayEditContentId(int $id): bool
    {
        if ($this->session->mayRole(Role::ORGA)
            || $this->currentUserUnits->isAdminFor(RegionIDs::EDITORIAL_GROUP)) {
            return true;
        }

        $regionContentMap = [
            RegionIDs::QUIZ_AND_REGISTRATION_WORK_GROUP => $this->QUIZ_CONTENT_IDS,
            RegionIDs::PR_PARTNER_AND_TEAM_WORK_GROUP => $this->PR_PARTNER_CONTENT_IDS,
            RegionIDs::PR_START_PAGE => $this->START_CONTENT_IDS,
            RegionIDs::POLITICAL_CAMPAIGNS => $this->POLITICAL_CAMPAIGNS_CONTENT_IDS,
        ];

        foreach ($regionContentMap as $regionID => $contentIDs) {
            if ($this->currentUserUnits->isAdminFor($regionID) && in_array($id, $contentIDs)) {
                return true;
            }
        }

        return false;
    }

    public function mayCreateContent(): bool
    {
        return $this->session->mayRole(Role::ORGA)
            || $this->currentUserUnits->isAdminFor(RegionIDs::EDITORIAL_GROUP);
    }
}
