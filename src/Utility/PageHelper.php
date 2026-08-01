<?php

namespace Foodsharing\Utility;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\CategoryType;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Settings\SettingsTransactions;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Permissions\AchievementPermissions;
use Foodsharing\Permissions\BlogPermissions;
use Foodsharing\Permissions\CategoriesPermissions;
use Foodsharing\Permissions\ContentPermissions;
use Foodsharing\Permissions\DonationPermissions;
use Foodsharing\Permissions\EmailBlocklistPermissions;
use Foodsharing\Permissions\MailboxPermissions;
use Foodsharing\Permissions\OAuthPermissions;
use Foodsharing\Permissions\ProfilePermissions;
use Foodsharing\Permissions\QuizPermissions;
use Foodsharing\Permissions\RegionPermissions;
use Foodsharing\Permissions\ReportPermissions;
use Foodsharing\Permissions\ResourcePermissions;
use Foodsharing\Permissions\StorePermissions;
use Foodsharing\Permissions\WorkGroupPermissions;
use Twig\Environment;

final class PageHelper
{
    private string $content_main = '';
    private string $js = '';
    private array $bread = [];
    private array $title = ['foodsharing'];
    private array $webpackScripts = [];
    private array $webpackStylesheets = [];

    public function __construct(
        private readonly Session $session,
        private readonly Sanitizer $sanitizerService,
        private readonly Environment $twig,
        private readonly RouteHelper $routeHelper,
        private readonly MailboxPermissions $mailboxPermissions,
        private readonly QuizPermissions $quizPermissions,
        private readonly ReportPermissions $reportPermissions,
        private readonly StorePermissions $storePermissions,
        private readonly ContentPermissions $contentPermissions,
        private readonly BlogPermissions $blogPermissions,
        private readonly RegionPermissions $regionPermissions,
        private readonly WorkGroupPermissions $workGroupPermissions,
        private readonly ProfilePermissions $profilePermissions,
        private readonly CategoriesPermissions $categoriesPermissions,
        private readonly AchievementPermissions $achievementPermissions,
        private readonly RegionGateway $regionGateway,
        private readonly SettingsTransactions $settingsTransactions,
        private readonly ResourcePermissions $resourcePermissions,
        private readonly OAuthPermissions $oauthPermissions,
        private readonly EmailBlocklistPermissions $emailBlocklistPermissions,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
        private readonly DonationPermissions $donationPermissions,
    ) {
    }

    public function render(string $template, array $context = []): void
    {
        echo $this->twig->render($template, $context);
        exit;
    }

    public function generateAndGetGlobalViewData(): array
    {
        $this->addMessages();
        $mainWidth = 24;

        $bodyClasses = [];

        if ($this->session->mayRole()) {
            $bodyClasses[] = 'loggedin';
        }

        if ($this->session->mayRole(Role::FOODSAVER)) {
            $bodyClasses[] = 'fs';
        }

        $page = $this->routeHelper->getSymfonyRoute();

        $bodyClasses[] = 'page-' . $page;

        return [
            'head' => $this->getHeadData(),
            'bread' => $this->bread,
            'bodyClasses' => $bodyClasses,
            'serverDataJSON' => json_encode($this->getServerData()),
            'menu' => $this->getMenu(),
            'route' => $page,
            'dev' => getenv('FS_ENV') === 'dev',
            'footer' => $this->getFooter(),
            'notificationsWrapper' => $this->getNotificationsWrapper(),
            'content' => [
                'main' => [
                    'html' => $this->content_main,
                    'width' => $mainWidth
                ],
            ]
        ];
    }

    /**
     * This is used to set window.serverData on in the frontend.
     */
    public function getServerData(): array
    {
        $userData = [
            'id' => $this->session->id(),
            'firstname' => $this->session->user('name') ?? '',
            'lastname' => $this->session->user('nachname') ?? '',
            'may' => $this->session->mayRole(),
            'homeRegionId' => $this->currentUserUnits->getCurrentRegionId() ?? null,
            'hasMailbox' => $this->mailboxPermissions->mayHaveMailbox(),
            'isFoodsaver' => $this->session->mayRole(Role::FOODSAVER),
            'verified' => $this->session->isVerified(),
            'avatar' => $this->session->user('photo') ?? null,
        ];

        $permissions = null;
        if ($this->session->mayRole()) {
            $permissions = $this->getPermissions();
        }

        $location = $this->session->user('location') ?? null;

        $sentryConfig = null;

        if (defined('RAVEN_JAVASCRIPT_CONFIG')) {
            $sentryConfig = RAVEN_JAVASCRIPT_CONFIG;
        }

        $geoapifyApiKey = defined('GEOAPIFY_API_KEY') ? GEOAPIFY_API_KEY : null;

        $userGroupsAndRegions = $this->getUserGroupsAndRegions();
        $groups = $userGroupsAndRegions['groups'];
        $regions = $userGroupsAndRegions['regions'];

        return [
            'user' => $userData,
            'permissions' => $permissions,
            'page' => $this->routeHelper->getPage(),
            'subPage' => $this->routeHelper->getSubPage(),
            'locations' => $location,
            'ravenConfig' => $sentryConfig,
            'version' => defined('SRC_REVISION') ? SRC_REVISION : 'DEV',
            'isDev' => getenv('FS_ENV') === 'dev',
            'isTest' => getenv('FS_ENV') === 'test',
            'locale' => $this->settingsTransactions->getLocale(),
            'geoapifyApiKey' => $geoapifyApiKey,
            'groups' => $groups,
            'regions' => $regions,
        ];
    }

    public function getUserGroupsAndRegions(): array
    {
        $groups = [];
        $regions = [];

        if ($this->session->mayRole()) {
            $userGroups = $this->currentUserUnits->getRegions();

            foreach ($userGroups as $group) {
                $groupId = $group['id'];
                $groupType = $group['type'];
                $group = array_merge($group, [
                    'mayHandleFoodsaverRegionMenu' => $this->regionPermissions->mayHandleFoodsaverRegionMenu($groupId),
                    'hasConference' => $this->regionPermissions->hasConference($groupType),
                    'hasResources' => $this->resourcePermissions->maySeeResources($group['id'], $group['type']),
                ]);
                if (UnitType::isRegion($groupType)) {
                    $group['isAdmin'] = $this->currentUserUnits->isAdminFor($groupId);
                    $group['mayAccessReports'] = $this->reportPermissions->mayAccessReportsForRegion($groupId);
                    $group['isReportAdmin'] = $this->reportPermissions->isReportAdmin($groupId);
                    $group['isArbitrationAdmin'] = $this->reportPermissions->isArbitrationAdmin($groupId);
                    $group['maySetRegionPin'] = $this->regionPermissions->maySetRegionPin($groupId);
                } else {
                    $group['isAdmin'] = $this->workGroupPermissions->mayEdit($group);
                    $group['hasSubgroups'] = $this->regionGateway->hasSubgroups($groupId);
                    if (RegionIDs::isChainsGroup($group['id'])) {
                        $group['isChainGroup'] = true;
                    }
                }
                if ($group['isAdmin']) {
                    $group['mailboxId'] = $this->regionGateway->getMailboxId($groupId);
                }
                if (UnitType::isRegion($groupType)) {
                    $regions[] = $group;
                } else {
                    $groups[] = $group;
                }
            }
        }

        return [
            'groups' => $groups,
            'regions' => $regions,
        ];
    }

    private function getPermissions(): array
    {
        return [
            'mayEditUserProfile' => $this->profilePermissions->mayEditUserProfile($this->session->id()),
            'mayAdministrateUserProfile' => $this->profilePermissions->mayAdministrateUserProfile($this->session->id(), $this->currentUserUnits->getCurrentRegionId()),
            'mayAdministrateOAuthClients' => $this->oauthPermissions->mayAdministrateOAuthClients(),
            'mayAdministrateEmailBlocklist' => $this->emailBlocklistPermissions->mayAdministrateEmailBlocklist(),
            'administrateBlog' => $this->blogPermissions->mayAdministrateBlog(),
            'editQuiz' => $this->quizPermissions->maySeeEditQuizPage(),
            'handleReports' => $this->reportPermissions->mayHandleReports(),
            'addStore' => $this->storePermissions->mayCreateStore(),
            'editContent' => $this->contentPermissions->mayEditContent(),
            'administrateRegions' => $this->regionPermissions->mayAdministrateRegions(),
            'editStoreCategories' => $this->categoriesPermissions->mayEditCategories(CategoryType::STORE),
            'editResourceCategories' => $this->categoriesPermissions->mayEditCategories(CategoryType::RESOURCE),
            'editAchievements' => $this->achievementPermissions->mayEditAchievements(),
            'editDonationPage' => $this->donationPermissions->mayEditDonationPage(),
        ];
    }

    private function getMenu(): string
    {
        $userGroupsAndRegions = $this->getUserGroupsAndRegions();
        $regions = $userGroupsAndRegions['regions'];
        $workingGroups = $userGroupsAndRegions['groups'];

        $props = [
            'regions' => $regions,
            'groups' => $workingGroups,
        ];

        return $this->twig->render(
            'partials/vue-wrapper.twig',
            [
                'id' => 'vue-topbar',
                'component' => 'topbar',
                'props' => $props,
            ]
        );
    }

    private function getFooter(): string
    {
        return $this->twig->render(
            'partials/vue-wrapper.twig',
            [
                'id' => 'vue-footer',
                'component' => 'Footer',
                'props' => [],
            ]
        );
    }

    /**
     * Render a global notifications mount point so the Notifications Vue
     * component is mounted outside of the topbar/navigation and not covered
     * by modal backdrops that intentionally overlay the topbar.
     */
    private function getNotificationsWrapper(): string
    {
        return $this->twig->render(
            'partials/vue-wrapper.twig',
            [
                'id' => 'vue-ui-notifications',
                'component' => 'UiNotifications',
                'props' => [],
            ]
        );
    }

    private function getHeadData(): array
    {
        return [
            'title' => implode(' | ', $this->title),
            'js' => $this->js,
            'stylesheets' => $this->webpackStylesheets,
            'scripts' => $this->webpackScripts
        ];
    }

    private function addMessages(): void
    {
        if (!isset($_SESSION['msg'])) {
            $_SESSION['msg'] = [];
        }
        if (isset($_SESSION['msg']['error']) && !empty($_SESSION['msg']['error'])) {
            foreach ($_SESSION['msg']['error'] as $error) {
                $this->addJs('pulseError("' . $this->sanitizerService->jsSafe($error, '"') . '");');
            }
        }
        if (isset($_SESSION['msg']['success']) && !empty($_SESSION['msg']['success'])) {
            foreach ($_SESSION['msg']['success'] as $success) {
                $this->addJs('pulseSuccess("' . $this->sanitizerService->jsSafe($success, '"') . '");');
            }
        }
        if (isset($_SESSION['msg']['info']) && !empty($_SESSION['msg']['info'])) {
            foreach ($_SESSION['msg']['info'] as $info) {
                $this->addJs('pulseInfo("' . $this->sanitizerService->jsSafe($info, '"') . '");');
            }
        }
        $_SESSION['msg']['info'] = [];
        $_SESSION['msg']['success'] = [];
        $_SESSION['msg']['error'] = [];
    }

    public function addContent(string $newContent): void
    {
        $this->content_main .= $newContent;
    }

    public function addBread(string $name, string $href = ''): void
    {
        $this->bread[] = ['name' => $name, 'href' => $href];
    }

    public function addWebpackScript(string $src): void
    {
        $this->webpackScripts[] = $src;
    }

    public function addWebpackStylesheet(string $src): void
    {
        $this->webpackStylesheets[] = $src;
    }

    public function addJs(string $njs): void
    {
        $this->js .= $njs;
    }

    public function addTitle(string $name): void
    {
        $this->title[] = $name;
    }
}
