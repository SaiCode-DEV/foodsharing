<?php

namespace Foodsharing\Utility;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Settings\SettingsTransactions;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Permissions\AchievementPermissions;
use Foodsharing\Permissions\BlogPermissions;
use Foodsharing\Permissions\ContentPermissions;
use Foodsharing\Permissions\MailboxPermissions;
use Foodsharing\Permissions\NewsletterEmailPermissions;
use Foodsharing\Permissions\ProfilePermissions;
use Foodsharing\Permissions\QuizPermissions;
use Foodsharing\Permissions\RegionPermissions;
use Foodsharing\Permissions\ReportPermissions;
use Foodsharing\Permissions\StoreCategoriesPermissions;
use Foodsharing\Permissions\StorePermissions;
use Foodsharing\Permissions\WorkGroupPermissions;
use Twig\Environment;

final class PageHelper
{
    private string $add_css = '';
    private string $content_main = '';
    private string $content_right = '';
    private string $content_left = '';
    private string $content_bottom = '';
    private string $content_top = '';
    private string $content_overtop = '';
    private string $head = '';
    private string $hidden = '';
    private string $js_func = '';
    private string $js = '';
    private array $bread = [];
    private array $title = ['foodsharing'];
    private array $webpackScripts = [];
    private array $webpackStylesheets = [];

    private int $content_left_width = 6;
    private int $content_right_width = 6;

    private array $extraJsServerData = [];

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
        private readonly NewsletterEmailPermissions $newsletterEmailPermissions,
        private readonly WorkGroupPermissions $workGroupPermissions,
        private readonly ProfilePermissions $profilePermissions,
        private readonly StoreCategoriesPermissions $storeCategoriesPermissions,
        private readonly AchievementPermissions $achievementPermissions,
        private readonly RegionGateway $regionGateway,
        private readonly SettingsTransactions $settingsTransactions,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
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

        $contentLeft = $this->getContent(CNT_LEFT);
        $contentRight = $this->getContent(CNT_RIGHT);

        if (!empty($contentLeft)) {
            $mainWidth -= $this->content_left_width;
        }

        if (!empty($contentRight)) {
            $mainWidth -= $this->content_right_width;
        }

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
            'hidden' => $this->hidden,
            'footer' => $this->getFooter(),
            'content' => [
                'main' => [
                    'html' => $this->getContent(CNT_MAIN),
                    'width' => $mainWidth
                ],
                'left' => [
                    'html' => $contentLeft,
                    'width' => $this->content_left_width,
                    'id' => 'left'
                ],
                'right' => [
                    'html' => $contentRight,
                    'width' => $this->content_right_width,
                    'id' => 'right'
                ],
                'top' => [
                    'html' => $this->getContent(CNT_TOP),
                    'id' => 'content_top'
                ],
                'bottom' => [
                    'html' => $this->getContent(CNT_BOTTOM),
                    'id' => 'content_bottom'
                ],
                'overtop' => [
                    'html' => $this->getContent(CNT_OVERTOP)
                ]
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

        return array_merge($this->extraJsServerData, [
            'user' => $userData,
            'permissions' => $permissions,
            'page' => $this->routeHelper->getPage(),
            'subPage' => $this->routeHelper->getSubPage(),
            'isApiRestrictedForLegalReasons' => $this->routeHelper->isApiRestrictedForLegalReasons(),
            'locations' => $location,
            'ravenConfig' => $sentryConfig,
            'version' => defined('SRC_REVISION') ? SRC_REVISION : 'DEV',
            'isDev' => getenv('FS_ENV') === 'dev',
            'isTest' => getenv('FS_ENV') === 'test',
            'locale' => $this->settingsTransactions->getLocale(),
            'geoapifyApiKey' => $geoapifyApiKey
        ]);
    }

    private function getPermissions(): array
    {
        return [
            'mayEditUserProfile' => $this->profilePermissions->mayEditUserProfile($this->session->id()),
            'mayAdministrateUserProfile' => $this->profilePermissions->mayAdministrateUserProfile($this->session->id(), $this->currentUserUnits->getCurrentRegionId()),
            'administrateBlog' => $this->blogPermissions->mayAdministrateBlog(),
            'editQuiz' => $this->quizPermissions->maySeeEditQuizPage(),
            'handleReports' => $this->reportPermissions->mayHandleReports(),
            'addStore' => $this->storePermissions->mayCreateStore(),
            'editContent' => $this->contentPermissions->mayEditContent(),
            'administrateNewsletterEmail' => $this->newsletterEmailPermissions->mayAdministrateNewsletterEmail(),
            'administrateRegions' => $this->regionPermissions->mayAdministrateRegions(),
            'editStoreCategories' => $this->storeCategoriesPermissions->mayEditStoreCategories(),
            'editAchievements' => $this->achievementPermissions->mayEditAchievements(),
        ];
    }

    private function getMenu(): string
    {
        $groups = $this->currentUserUnits->getRegions();

        $regions = [];
        $workingGroups = [];

        foreach ($groups as $group) {
            $groupId = $group['id'];
            $groupType = $group['type'];
            $group = array_merge($group, [
                'mayHandleFoodsaverRegionMenu' => $this->regionPermissions->mayHandleFoodsaverRegionMenu($groupId),
                'hasConference' => $this->regionPermissions->hasConference($groupType),
            ]);
            if (UnitType::isRegion($groupType)) {
                $group['isAdmin'] = $this->currentUserUnits->isAdminFor($groupId);
                $group['mayAccessReports'] = $this->reportPermissions->mayAccessReportsForRegion($groupId);
                $group['isReportAdmin'] = $this->reportPermissions->isReportAdmin($groupId);
                $group['isArbitrationAdmin'] = $this->reportPermissions->isArbitrationAdmin($groupId);
                $group['maySetRegionPin'] = $this->regionPermissions->maySetRegionPin($groupId);
                $regions[] = $group;
            } else {
                $group['isAdmin'] = $this->workGroupPermissions->mayEdit($group);
                $group['hasSubgroups'] = $this->regionGateway->hasSubgroups($groupId);
                if (RegionIDs::isChainsGroup($group['id'])) {
                    $group['isChainGroup'] = true;
                }
                $workingGroups[] = $group;
            }
        }

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

    private function getHeadData(): array
    {
        return [
            'title' => implode(' | ', $this->title),
            'extra' => $this->head,
            'css' => str_replace(["\r", "\n"], '', $this->add_css),
            'jsFunc' => $this->js_func,
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

    private function getContent(int $positionCode): string
    {
        return match ($positionCode) {
            CNT_MAIN => $this->content_main,
            CNT_RIGHT => $this->content_right,
            CNT_TOP => $this->content_top,
            CNT_BOTTOM => $this->content_bottom,
            CNT_LEFT => $this->content_left,
            CNT_OVERTOP => $this->content_overtop,
            default => '',
        };
    }

    public function addContent(string $newContent, int $positionCode = CNT_MAIN): void
    {
        switch ($positionCode) {
            case CNT_MAIN:
                $this->content_main .= $newContent;
                break;
            case CNT_RIGHT:
                $this->content_right .= $newContent;
                break;
            case CNT_TOP:
                $this->content_top .= $newContent;
                break;
            case CNT_BOTTOM:
                $this->content_bottom .= $newContent;
                break;
            case CNT_LEFT:
                $this->content_left .= $newContent;
                break;
            case CNT_OVERTOP:
                $this->content_overtop .= $newContent;
                break;
            default:
                break;
        }
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

    public function addJsFunc(string $nfunc): void
    {
        $this->js_func .= $nfunc;
    }

    public function addTitle(string $name): void
    {
        $this->title[] = $name;
    }

    public function addHidden(string $html): void
    {
        $this->hidden .= $html;
    }

    public function setContentWidth(int $left, int $right): void
    {
        $this->content_left_width = $left;
        $this->content_right_width = $right;
    }
}
