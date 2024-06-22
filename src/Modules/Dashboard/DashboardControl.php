<?php

namespace Foodsharing\Modules\Dashboard;

use Exception;
use Foodsharing\Modules\Content\ContentGateway;
use Foodsharing\Modules\Core\Control;
use Foodsharing\Modules\Core\DBConstants\Content\ContentId;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Event\EventGateway;
use Foodsharing\Modules\Event\InvitationStatus;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Login\UserStatusTransactions;
use Foodsharing\Modules\Quiz\QuizSessionGateway;
use Foodsharing\Modules\Quiz\QuizTransactions;
use Foodsharing\Modules\Settings\SettingsGateway;
use Foodsharing\Permissions\QuizPermissions;

class DashboardControl extends Control
{
    private array $params;
    private readonly ContentGateway $contentGateway;
    private readonly SettingsGateway $settingsGateway;
    private readonly FoodsaverGateway $foodsaverGateway;
    private readonly EventGateway $eventGateway;
    private readonly QuizSessionGateway $quizSessionGateway;
    private readonly QuizPermissions $quizPermissions;

    /**
     * @throws Exception
     */
    public function __construct(
        DashboardView $view,
        ContentGateway $contentGateway,
        SettingsGateway $settingsGateway,
        FoodsaverGateway $foodsaverGateway,
        EventGateway $eventGateway,
        QuizSessionGateway $quizSessionGateway,
        QuizPermissions $quizPermissions,
        private readonly UserStatusTransactions $userStatusTransactions,
        private readonly QuizTransactions $quizTransactions,
    ) {
        $this->view = $view;
        $this->contentGateway = $contentGateway;
        $this->settingsGateway = $settingsGateway;
        $this->foodsaverGateway = $foodsaverGateway;
        $this->eventGateway = $eventGateway;
        $this->quizSessionGateway = $quizSessionGateway;
        $this->quizPermissions = $quizPermissions;

        parent::__construct();

        if (!$this->session->mayRole()) {
            $this->routeHelper->goAndExit('/');
        }

        $this->params = [];
    }

    /**
     * @throws Exception
     */
    public function index(): void
    {
        $this->userStatusTransactions->updateLastUserStatus($this->session->id());

        $this->params['quiz'] = $this->getQuiz();
        $this->params['quizConfirmation'] = $this->getMissingQuizConfirmation();

        if ($this->session->mayRole(Role::FOODSAVER)) {
            $this->params['events'] = $this->getEvents();
        }

        $this->pageHelper->addContent($this->view->index($this->params), CNT_MAIN);
    }

    private function getEvents(): object
    {
        return (object)[
            'invites' => $this->eventGateway->getEventsByStatus($this->session->id(), [InvitationStatus::INVITED]),
            'accepted' => $this->eventGateway->getEventsByStatus($this->session->id(), [InvitationStatus::ACCEPTED, InvitationStatus::MAYBE]),
        ];
    }

    private function getQuiz(): ?array
    {
        $is_foodsharer = !$this->session->mayRole(Role::FOODSAVER) && $this->foodsaverGateway->getQuizRole($this->session->id())->value < Role::FOODSAVER->value;

        if ($is_foodsharer) {
            $cnt = $this->contentGateway->getContent(ContentId::QUIZ_REMARK_PAGE_33);
            $quiz = [];
            $quiz['body'] = str_replace([
                '{NAME}',
                '{ANREDE}'
            ], [
                $this->session->user('name'),
                $this->translator->trans('salutation.' . $this->session->user('gender'))
            ], $cnt->body);
            $quiz['closeable'] = false;
            $quiz['links'] = [
                (object)[
                    'urlShortHand' => 'quiz_foodsaver',
                    'text' => 'foodsaver.upgrade.FOODSAVER',
                ],
                (object)[
                    'urlShortHand' => 'quiz_learning_video',
                    'text' => 'foodsaver.upgrade.learning',
                ]
            ];

            return $quiz;
        }

        return null;
    }

    private function getMissingQuizConfirmation(): ?int
    {
        $quizRole = $this->quizTransactions->getQuizRoleOfCurrentUser()->value;
        if ($this->session->role()->value >= $quizRole) {
            return null;
        }
        if (!$this->quizPermissions->requiresConfirmation($quizRole)) {
            return null;
        }

        return $quizRole;
    }
}
