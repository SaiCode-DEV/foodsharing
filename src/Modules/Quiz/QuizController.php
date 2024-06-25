<?php

namespace Foodsharing\Modules\Quiz;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Permissions\QuizPermissions;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class QuizController extends FoodsharingController
{
    public function __construct(
        private readonly QuizPermissions $permissions,
    ) {
        parent::__construct();
    }

    #[Route(['/quiz/edit/{quizId}'], name: 'quiz.edit', requirements: ['quizId' => '\d+'])]
    public function edit(?int $quizId): Response
    {
        $readableQuizzes = $this->permissions->getReadableQuizzes();
        if (!count($readableQuizzes)) {
            return $this->redirect('/');
        }
        $vue = $this->prepareVueComponent('vue-quiz-editor', 'QuizEditor', [
            'quizId' => $quizId,
            'readableQuizzes' => $readableQuizzes,
        ]);
        $this->pageHelper->addContent($vue);

        return $this->renderGlobal();
    }

    #[Route(['/quiz', '/quiz/edit'], name: 'quiz.redirect')]
    public function redirectToSecific(): Response
    {
        if (!$this->permissions->maySeeEditQuizPage()) {
            return $this->redirect('/');
        }
        $readableQuizzes = $this->permissions->getReadableQuizzes();
        $quizId = $readableQuizzes[0]['id'];

        return $this->redirect('/quiz/edit/' . $quizId);
    }
}
