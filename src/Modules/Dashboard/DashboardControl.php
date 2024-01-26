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
use Foodsharing\Modules\Quiz\QuizSessionGateway;
use Foodsharing\Modules\Settings\SettingsGateway;

class DashboardControl extends Control
{
    private array $params;
    private ContentGateway $contentGateway;
    private SettingsGateway $settingsGateway;
    private FoodsaverGateway $foodsaverGateway;
    private EventGateway $eventGateway;
    private QuizSessionGateway $quizSessionGateway;

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
    ) {
        $this->view = $view;
        $this->contentGateway = $contentGateway;
        $this->settingsGateway = $settingsGateway;
        $this->foodsaverGateway = $foodsaverGateway;
        $this->eventGateway = $eventGateway;
        $this->quizSessionGateway = $quizSessionGateway;

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
        $this->session->updateLastActivity();

        $this->params['quiz'] = $this->getQuiz();

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
        $is_foodsharer = !$this->session->mayRole(Role::FOODSAVER) && !$this->quizSessionGateway->hasPassedQuiz($this->session->id(), Role::FOODSAVER);

        if ($is_foodsharer) {
            $cnt = $this->contentGateway->get(ContentId::QUIZ_REMARK_PAGE_33);
            $cnt['body'] = str_replace([
                '{NAME}',
                '{ANREDE}'
            ], [
                $this->session->user('name'),
                $this->translator->trans('salutation.' . $this->session->user('gender'))
            ], (string)$cnt['body']);
            $cnt['closeable'] = false;
            $cnt['links'] = [
                (object)[
                    'urlShortHand' => 'quiz_foodsaver',
                    'text' => 'foodsaver.upgrade.to_fs',
                ],
                (object)[
                    'urlShortHand' => 'quiz_learning_video',
                    'text' => 'foodsaver.upgrade.learning',
                ]
            ];

            return $cnt;
        }

        return null;
    }
}
