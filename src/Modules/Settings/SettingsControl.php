<?php

namespace Foodsharing\Modules\Settings;

use Foodsharing\Modules\Content\ContentGateway;
use Foodsharing\Modules\Core\Control;
use Foodsharing\Modules\Core\DBConstants\Content\ContentId;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Quiz\QuizStatus;
use Foodsharing\Modules\Core\DBConstants\Quiz\SessionStatus;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Quiz\QuizGateway;
use Foodsharing\Modules\Quiz\QuizSessionGateway;
use Foodsharing\Permissions\SettingsPermissions;
use Foodsharing\Utility\DataHelper;

class SettingsControl extends Control
{
    private array $foodsaver;
    private readonly SettingsGateway $settingsGateway;
    private readonly QuizGateway $quizGateway;
    private readonly QuizSessionGateway $quizSessionGateway;
    private readonly ContentGateway $contentGateway;
    private readonly FoodsaverGateway $foodsaverGateway;
    private readonly DataHelper $dataHelper;
    private readonly SettingsPermissions $settingsPermissions;

    public function __construct(
        SettingsView $view,
        SettingsGateway $settingsGateway,
        QuizGateway $quizGateway,
        QuizSessionGateway $quizSessionGateway,
        ContentGateway $contentGateway,
        FoodsaverGateway $foodsaverGateway,
        DataHelper $dataHelper,
        SettingsPermissions $settingsPermissions,
    ) {
        $this->view = $view;
        $this->settingsGateway = $settingsGateway;
        $this->quizGateway = $quizGateway;
        $this->quizSessionGateway = $quizSessionGateway;
        $this->contentGateway = $contentGateway;
        $this->foodsaverGateway = $foodsaverGateway;
        $this->dataHelper = $dataHelper;
        $this->settingsPermissions = $settingsPermissions;

        parent::__construct();

        if (!$this->session->mayRole()) {
            $this->routeHelper->goLoginAndExit();
        }

        if (isset($_GET['newmail'])) {
            $this->handle_newmail();
        }

        $this->foodsaver = $this->foodsaverGateway->getFoodsaverDetails($this->session->id());

        if (!isset($_GET['sub'])) {
            $this->routeHelper->goAndExit('/?page=settings&sub=general');
        }

        $this->pageHelper->addTitle($this->translator->trans('settings.title'));
    }

    public function index()
    {
        $this->pageHelper->addBread($this->translator->trans('settings.title'), '/?page=settings');

        $menu = [
            ['name' => $this->translator->trans('settings.header'), 'href' => '/?page=settings&sub=general'],
            ['name' => $this->translator->trans('settings.notifications'), 'href' => '/?page=settings&sub=info'],
            ['name' => $this->translator->trans('settings.businesscard'), 'href' => '/?page=bcard'],
        ];

        if ($this->settingsPermissions->mayUseCalendarExport()) {
            $menu[] = ['name' => $this->translator->trans('settings.calendar.menu'), 'href' => '/?page=settings&sub=calendar'];
        }
        if ($this->session->mayRole() > Role::FOODSHARER) {
            $menu[] = ['name' => $this->translator->trans('settings.passport.menu'), 'href' => '/?page=settings&sub=passport'];
        }

        $this->pageHelper->addContent($this->view->menu($menu, [
            'title' => $this->translator->trans('settings.title'),
            'active' => $this->getSub(),
        ]), CNT_LEFT);

        $menu = [
            ['name' => $this->translator->trans('settings.sleep.title'), 'href' => '/?page=settings&sub=sleeping'],
            ['name' => $this->translator->trans('settings.email'), 'href' => '/?page=settings&sub=changeEmail'],
        ];

        if ($this->foodsaver['rolle'] == Role::FOODSHARER) {
            $menu[] = ['name' => $this->translator->trans('foodsaver.upgrade.to_fs'), 'href' => '/?page=settings&sub=up_fs'];
        } elseif ($this->foodsaver['rolle'] == Role::FOODSAVER) {
            $menu[] = ['name' => $this->translator->trans('foodsaver.upgrade.to_sm'), 'href' => '/?page=settings&sub=up_bip'];
        }

        $menu[] = [
            'name' => $this->translator->trans('foodsaver.delete_account'),
            'href' => '/?page=settings&sub=deleteaccount',
        ];

        $this->pageHelper->addContent($this->view->menu(
            $menu,
            ['title' => $this->translator->trans('settings.account'), 'active' => $this->getSub()]
        ), CNT_LEFT);
    }

    public function sleeping(): void
    {
        if ($sleep = $this->settingsGateway->getSleepData($this->session->id())) {
            $this->pageHelper->addContent($this->view->sleepMode($sleep));
        }
    }

    public function quizsession(): void
    {
        if ($session = $this->quizSessionGateway->getExtendedUserSession($_GET['sid'], $this->session->id())) {
            $this->pageHelper->addContent(
                $this->view->quizSession($session, $session['try_count'], $this->contentGateway)
            );
        } else {
            $this->pageHelper->addContent($this->v_utils->v_info($this->translator->trans('quiz.session_deleted')));
        }
    }

    public function up_fs()
    {
        $quizRole = Role::FOODSAVER;
        if ($this->session->mayRole()) {
            if ($quiz = $this->quizGateway->getQuiz($quizRole)) {
                $this->handleQuizStatus($quiz, $quizRole);
            }
        }
    }

    public function up_bip()
    {
        $quizRole = Role::STORE_MANAGER;
        if ($this->session->mayRole() && $this->foodsaver['rolle'] > Role::FOODSHARER) {
            if (!$this->foodsaver['verified']) {
                $content = $this->contentGateway->get(ContentId::QUIZ_MESSAGE_SM_UNVERIFIED_PAGE_45);
                $this->pageHelper->addContent($this->v_utils->v_field(
                    $content['body'],
                    $content['title'],
                    ['class' => 'ui-padding']
                ));
            } else {
                if ($quiz = $this->quizGateway->getQuiz($quizRole)) {
                    $fsId = $this->session->id();
                    if (!$this->quizSessionGateway->hasPassedQuiz($fsId, Role::FOODSAVER)) {
                        $this->flashMessageHelper->info($this->translator->trans('foodsaver.upgrade.needs_fs_quiz'));
                        $this->routeHelper->goAndExit('/?page=settings&sub=up_fs');
                    }

                    $this->handleQuizStatus($quiz, $quizRole);
                }
            }
        }
    }

    public function up_bot()
    {
        $quizRole = Role::AMBASSADOR;
        if ($this->session->mayRole() && $this->foodsaver['rolle'] >= Role::STORE_MANAGER) {
            if ($quiz = $this->quizGateway->getQuiz($quizRole)) {
                $this->handleQuizStatus($quiz, $quizRole);
            } else {
                $this->pageHelper->addContent(
                    $this->v_utils->v_info(
                        $this->translator->trans('foodsaver.upgrade.quiz_error')
                            . ' <a href=mailto:' . SUPPORT_EMAIL . '>' . SUPPORT_EMAIL . '</a>'
                    )
                );
            }
        } else {
            switch ($this->foodsaver['rolle']) {
                case Role::FOODSHARER:
                    $this->flashMessageHelper->info($this->translator->trans('foodsaver.upgrade.needs_fs'));
                    $this->routeHelper->goAndExit('/?page=settings&sub=up_fs');
                    // no break
                case Role::FOODSAVER:
                    $this->flashMessageHelper->info($this->translator->trans('foodsaver.upgrade.needs_sm'));
                    $this->routeHelper->goAndExit('/?page=settings&sub=up_bip');
                    // no break
                default:
                    $this->routeHelper->goAndExit('/?page=settings');
            }
        }
    }

    private function handleQuizStatus(array $quiz, int $role): void
    {
        $fsId = $this->session->id();
        $quizStatus = $this->quizSessionGateway->getQuizStatus($role, $fsId);
        switch ($quizStatus['status']) {
            case QuizStatus::NEVER_TRIED:
                $this->pageHelper->addContent($this->view->quizIndex($quiz));
                break;

            case QuizStatus::RUNNING:
                $this->pageHelper->addContent($this->view->quizContinue($quiz));
                break;

            case QuizStatus::PASSED:
                $this->confirmRole($role);
                break;

            case QuizStatus::FAILED:
                $failCount = $this->quizSessionGateway->countSessions($fsId, $role, SessionStatus::FAILED);
                $this->pageHelper->addContent($this->view->quizRetry($quiz, $failCount, 3));
                break;

            case QuizStatus::PAUSE:
                if ($role == Role::FOODSAVER) {
                    $this->foodsaverGateway->riseRole($fsId, Role::FOODSHARER);
                }
                $lastTry = $this->quizSessionGateway->getLastTry($fsId, $role);
                $this->pageHelper->addContent($this->view->pause($quizStatus['wait']));
                break;

            case QuizStatus::PAUSE_ELAPSED:
                $this->pageHelper->addContent($this->view->quizIndex($quiz));
                break;

            default:
                $this->pageHelper->addContent($this->view->quizFailed($this->contentGateway->get(ContentId::QUIZ_FAILED_PAGE_13)));
        }
    }

    private function confirmRole(int $role): void
    {
        switch ($role) {
            case Role::FOODSAVER:
                $this->confirm_fs();
                break;

            case Role::STORE_MANAGER:
                $this->confirm_bip();
                break;

            case Role::AMBASSADOR:
                $this->confirmRoleAmbassador();
                break;

            default:
        }
    }

    private function confirm_fs()
    {
        $fsId = $this->session->id();
        if ($this->quizSessionGateway->hasPassedQuiz($fsId, Role::FOODSAVER)) {
            if ($this->submitted()) {
                if (empty($_POST['accepted'])) {
                    $check = false;
                    $this->flashMessageHelper->error($this->translator->trans('foodsaver.upgrade.needs_rv'));
                } else {
                    $this->mem->delPageCache('/?page=dashboard', $fsId);
                    if (!$this->session->mayRole(Role::FOODSAVER)) {
                        $this->foodsaverGateway->riseRole($fsId, Role::FOODSAVER);
                    }
                    $this->flashMessageHelper->success($this->translator->trans('foodsaver.upgrade.fs_success'));
                    $this->routeHelper->goAndExit('/?page=relogin&url=' . urlencode('/?page=dashboard'));
                }
            }
            $cnt = $this->contentGateway->get(ContentId::QUIZ_CONFIRM_FS_PAGE_14);
            $rv = $this->contentGateway->get(ContentId::QUIZ_LEGAL_FOODSAVER);
            $this->pageHelper->addContent($this->view->confirmFs($cnt, $rv));
        }
    }

    private function confirm_bip()
    {
        $fsId = $this->session->id();
        if ($this->quizSessionGateway->hasPassedQuiz($fsId, Role::STORE_MANAGER)) {
            if ($this->submitted()) {
                if (empty($_POST['accepted'])) {
                    $check = false;
                    $this->flashMessageHelper->error($this->translator->trans('foodsaver.upgrade.needs_rv'));
                } else {
                    $this->foodsaverGateway->riseRole($fsId, Role::STORE_MANAGER);
                    $this->session->refreshFromDatabase();
                    $this->flashMessageHelper->success($this->translator->trans('foodsaver.upgrade.sm_success'));
                    $this->routeHelper->goAndExit('/?page=dashboard');
                }
            }
            $cnt = $this->contentGateway->get(ContentId::QUIZ_CONFIRM_SM_PAGE_15);
            $rv = $this->contentGateway->get(ContentId::QUIZ_LEGAL_STOREMANAGER);
            $this->pageHelper->addContent($this->view->confirmBip($cnt, $rv));
        }
    }

    private function confirmRoleAmbassador(): void
    {
        $this->pageHelper->addBread($this->translator->trans('foodsaver.upgrade.to_amb'));
        $fsId = $this->session->id();
        if ($this->quizSessionGateway->hasPassedQuiz($fsId, Role::AMBASSADOR)) {
            $showform = true;

            if ($this->submitted()) {
                global $g_data;
                $g_data = $_POST;

                $isDataComplete = true;

                if (empty($_POST['about_me_public'])) {
                    $isDataComplete = false;
                    $this->flashMessageHelper->error($this->translator->trans('foodsaver.upgrade.needs_publicinfo'));
                }

                if (empty((int)$_POST['bezirk'])) {
                    $isDataComplete = false;
                    $this->flashMessageHelper->error($this->translator->trans('foodsaver.upgrade.needs_region'));
                }

                if ($isDataComplete) {
                    $data = $this->dataHelper->unsetAll($_POST, ['new_bezirk']);
                    $this->foodsaverGateway->updateProfile($fsId, $data);

                    $this->pageHelper->addContent($this->v_utils->v_field(
                        $this->v_utils->v_info($this->translator->trans('foodsaver.upgrade.amb_success')),
                        $this->translator->trans('foodsaver.upgrade.amb_requested'),
                        ['class' => 'ui-padding']
                    ));

                    $g_data = [];
                    $showform = false;
                }
            }

            if ($showform) {
                $this->pageHelper->addJs('
					$("#upBotsch").on("submit", function (ev) {
						check = true;
						if ($("#bezirk").val() == 0) {
							check = false;
							pulseError("' . $this->translator->trans('foodsaver.upgrade.needs_region') . '");
						}

						if (!check) {
							ev.preventDefault();
						}
					});
				');
            }
        }
    }

    public function deleteaccount(): void
    {
        $this->pageHelper->addBread($this->translator->trans('foodsaver.delete_account'));
        $this->pageHelper->addContent($this->view->delete_account($this->session->id()));
    }

    public function general()
    {
        $this->handle_edit();

        $data = $this->foodsaverGateway->getFoodsaver($this->session->id());

        $this->dataHelper->setEditData($data);

        $this->pageHelper->addContent($this->view->foodsaver_form());

        $this->pageHelper->addContent($this->picture_box(), CNT_RIGHT);
    }

    public function passport()
    {
        if ($this->session->mayRole() > Role::FOODSHARER) {
            $this->pageHelper->addBread($this->translator->trans('settings.passport.menu'));
            $this->pageHelper->addContent($this->view->passport());
        } else {
            $this->routeHelper->goAndExit('/?page=settings');
        }
    }

    public function calendar()
    {
        if ($this->settingsPermissions->mayUseCalendarExport()) {
            $this->pageHelper->addBread($this->translator->trans('settings.calendar.menu'));
            $this->pageHelper->addContent($this->view->settingsCalendar());
        } else {
            $this->routeHelper->goAndExit('/?page=settings');
        }
    }

    public function info()
    {
        $this->pageHelper->addBread($this->translator->trans('settings.notifications'));
        $this->pageHelper->addContent($this->view->settingsInfo());
    }

    public function changeEmail()
    {
        $this->pageHelper->addBread($this->translator->trans('settings.email'));
        $this->pageHelper->addContent($this->view->vueComponent('change-email-form', 'ChangeEmailForm'));
    }

    public function handle_edit()
    {
        if ($this->submitted()) {
            $data = $this->dataHelper->getPostData();
            $data['stadt'] = $data['ort'];
            $check = true;

            if (!empty($data['homepage'])) {
                if (!str_starts_with((string)$data['homepage'], 'http')) {
                    $data['homepage'] = 'http://' . $data['homepage'];
                }

                if (!$this->validUrl($data['homepage'])) {
                    $check = false;
                    $this->flashMessageHelper->error($this->translator->trans('foodsaver.url_error'));
                }
            }

            if ($check) {
                if ($oldFs = $this->foodsaverGateway->getFoodsaver($this->session->id())) {
                    $logChangedFields = ['stadt', 'plz', 'anschrift', 'telefon', 'handy', 'geschlecht', 'geb_datum', 'bezirk_id'];
                    $this->settingsGateway->logChangedSetting($this->session->id(), $oldFs, $data, $logChangedFields);
                }

                if (!isset($data['bezirk_id'])) {
                    $data['bezirk_id'] = $this->session->getCurrentRegionId();
                }
                if ($this->foodsaverGateway->updateProfile($this->session->id(), $data)) {
                    try {
                        $this->session->refreshFromDatabase();
                        $this->flashMessageHelper->success($this->translator->trans('foodsaver.edit_success'));
                    } catch (\Exception) {
                        $this->routeHelper->goPageAndExit('logout');
                    }
                } else {
                    $this->flashMessageHelper->error($this->translator->trans('error_unexpected'));
                }
            }
        }
    }

    private function validUrl($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        return true;
    }

    private function picture_box(): string
    {
        $photo = $this->foodsaverGateway->getPhotoFileName($this->session->id());

        return $this->view->picture_box($photo);
    }

    private function handle_newmail()
    {
        if ($email = $this->settingsGateway->getNewMail($this->session->id(), $_GET['newmail'])) {
            $this->pageHelper->addJs("ajreq('changemail3');");
        } else {
            $this->flashMessageHelper->info($this->translator->trans('foodsaver.mailchange_error'));
        }
    }
}
