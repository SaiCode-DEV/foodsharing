<?php

namespace Foodsharing\Modules\Dashboard;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Content\ContentGateway;
use Foodsharing\Modules\Core\DBConstants\Content\ContentId;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Quiz\QuizID;
use Foodsharing\Modules\Event\EventGateway;
use Foodsharing\Modules\Event\InvitationStatus;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Quiz\QuizTransactions;
use Foodsharing\Permissions\QuizPermissions;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends FoodsharingController
{
    public function __construct(
        private readonly DashboardView $view,
        private readonly ContentGateway $contentGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly EventGateway $eventGateway,
        private readonly QuizPermissions $quizPermissions,
        private readonly QuizTransactions $quizTransactions,
    ) {
        parent::__construct();
    }

    #[Route('/dashboard', name: 'dashboard')]
    public function index(): Response
    {
        if (!$this->session->mayRole()) {
            return $this->redirect('/');
        }

        $params = [
            'quiz' => $this->getQuiz(),
            'quizConfirmation' => $this->getMissingQuizConfirmation(),
        ];

        if ($this->session->mayRole(Role::FOODSAVER)) {
            $params['events'] = $this->getEvents();
        }

        $this->pageHelper->addContent($this->view->index($params));

        return $this->renderGlobal();
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
        if (!$this->quizPermissions->requiresConfirmation(QuizID::from($quizRole))) {
            return null;
        }

        return $quizRole;
    }
}
