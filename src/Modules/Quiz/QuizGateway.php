<?php

namespace Foodsharing\Modules\Quiz;

use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Quiz\DTO\Answer;
use Foodsharing\Modules\Quiz\DTO\Question;
use Foodsharing\Modules\Quiz\DTO\Quiz;

class QuizGateway extends BaseGateway
{
    // Quiz
    public function getQuiz(int $quizId): ?Quiz
    {
        $data = $this->db->fetchById('fs_quiz', '*', $quizId);

        return empty($data) ? null : Quiz::createFromArray($data);
    }

    public function updateQuiz(Quiz $quiz): void
    {
        $this->db->update('fs_quiz', [
            'name' => $quiz->name,
            'desc' => $quiz->description,
            'is_desc_htmlentity_encoded' => $quiz->isDescriptionHtmlEncoded,
            'maxfp' => $quiz->maxFailurePointsToSucceed,
            'questcount' => $quiz->questionCountTimed,
            'questcount_untimed' => $quiz->questionCountUntimed,
        ], ['id' => $quiz->id]);
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function getQuizzes(): array
    {
        return $this->db->fetchAll('SELECT
                `id`, `name`
			FROM fs_quiz
			ORDER BY id
		');
    }

    // Question

    public function getQuestion(int $questionId): ?Question
    {
        $data = $this->db->fetch('SELECT
                q.*,
                hq.fp,
                hq.quiz_id
            FROM fs_question q
            LEFT JOIN fs_question_has_quiz hq ON hq.question_id = q.id
			WHERE q.id = :questionId
		', [':questionId' => $questionId]);

        return empty($data) ? null : Question::createFromArray($data);
    }

    /**
     * @return array<Question>
     */
    public function getQuestions(int $quizId): array
    {
        $questions = $this->db->fetchAll('SELECT
				question.id,
				question.text,
				question.duration,
				question.wikilink,
                question.is_mandatory,
				question_has_quiz.fp
			FROM fs_question question
            LEFT JOIN fs_question_has_quiz question_has_quiz ON question_has_quiz.question_id = question.id
			WHERE question_has_quiz.quiz_id = :quizId
            ORDER BY question.is_mandatory DESC, question.id ASC
		', [':quizId' => $quizId]);

        return array_map(Question::createFromArray(...), $questions);
    }

    /**
     * @return array<Question>
     */
    public function getRandomQuestions(int $count, int $failurePoints, int $quizId): array
    {
        $data = $this->db->fetchAll('SELECT
                q.*,
                hq.fp,
                hq.quiz_id
			FROM fs_question q
			LEFT JOIN fs_question_has_quiz hq ON hq.question_id = q.id
			WHERE hq.quiz_id = :quizId AND hq.fp = :fp AND q.is_mandatory = 0
            ORDER BY RAND()
			LIMIT :count
		', [':quizId' => $quizId, ':fp' => $failurePoints, ':count' => $count]);

        return array_map(Question::createFromArray(...), $data);
    }

    /**
     * @return Question[]
     */
    public function getMandatoryQuestions(int $quizId): array
    {
        $data = $this->db->fetchAll('SELECT
                q.*,
                hq.fp,
                hq.quiz_id
			FROM fs_question q
			LEFT JOIN fs_question_has_quiz hq ON hq.question_id = q.id
			WHERE hq.quiz_id = :quizId AND q.is_mandatory = 1
            ORDER BY RAND()
		', [':quizId' => $quizId]);

        return array_map(Question::createFromArray(...), $data);
    }

    /**
     * Returns the number of questions for each number of failiure points, excluding mandatory questions.
     *
     * @return array<array>
     */
    public function getQuestionCountByFailurePoints(int $quizId): array
    {
        return $this->db->fetchAll('
            SELECT
                hq.fp,
                COUNT(q.id) AS `count`
            FROM fs_question q
            LEFT JOIN fs_question_has_quiz hq
                ON hq.question_id = q.id
            WHERE hq.quiz_id = :quizId
            AND q.is_mandatory = 0
            GROUP BY hq.fp
            ORDER BY hq.fp ASC
        ', [':quizId' => $quizId]);
    }

    public function addQuestion(int $quizId, Question $question): int
    {
        $questionId = $this->db->insert('fs_question', [
            'text' => $question->text,
            'duration' => $question->durationInSeconds,
            'wikilink' => $question->wikilink,
            'is_mandatory' => $question->isMandatory,
        ]);
        $this->db->insert('fs_question_has_quiz', [
            'question_id' => $questionId,
            'quiz_id' => $quizId,
            'fp' => $question->failurePoints
        ]);

        return $questionId;
    }

    public function updateQuestion(Question $question): void
    {
        $this->db->update('fs_question', [
            'text' => $question->text,
            'duration' => $question->durationInSeconds,
            'wikilink' => $question->wikilink,
            'is_mandatory' => $question->isMandatory,
        ], ['id' => $question->id]);
        $this->db->update('fs_question_has_quiz',
            ['fp' => $question->failurePoints],
            ['question_id' => $question->id]
        );
    }

    public function deleteQuestion(int $questionId): void
    {
        $this->db->delete('fs_answer', ['question_id' => $questionId]);
        $this->db->delete('fs_question', ['id' => $questionId]);
        $this->db->delete('fs_question_has_quiz', ['question_id' => $questionId]);
    }

    public function getQuizIdFromQuestionId(int $questionId): int
    {
        return $this->db->fetchValueByCriteria('fs_question_has_quiz', 'quiz_id', ['question_id' => $questionId]);
    }

    // Answer

    public function getAnswer(int $answerId): ?Answer
    {
        $data = $this->db->fetchByCriteria(
            'fs_answer',
            ['id', 'question_id', 'text', 'explanation', 'right'],
            ['id' => $answerId]
        );

        return empty($data) ? null : Answer::createFromArray($data);
    }

    /**
     * @return array<Answer>
     */
    public function getAnswers(int $questionId, bool $includeSolution = true): array
    {
        $columns = ['id', 'text'];
        if ($includeSolution) {
            $columns[] = 'explanation';
            $columns[] = 'right';
        }
        $answers = $this->db->fetchAllByCriteria(
            'fs_answer', $columns, ['question_id' => $questionId]
        );

        return array_map(Answer::createFromArray(...), $answers);
    }

    public function addAnswer(int $questionId, Answer $answer): int
    {
        return $this->db->insert('fs_answer', [
            'question_id' => $questionId,
            'text' => $answer->text,
            'explanation' => $answer->explanation,
            'right' => $answer->answerRating->value
        ]);
    }

    public function updateAnswer(Answer $answer): void
    {
        $this->db->update('fs_answer', [
            'text' => $answer->text,
            'explanation' => $answer->explanation,
            'right' => $answer->answerRating->value,
        ], ['id' => $answer->id]);
    }

    public function deleteAnswer(int $answerId): void
    {
        $this->db->delete('fs_answer', ['id' => $answerId]);
    }

    public function getQuestionIdFromAnswerId(int $answerId): int
    {
        return $this->db->fetchValueById('fs_answer', 'question_id', $answerId);
    }
}
