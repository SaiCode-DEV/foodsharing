<?php

namespace Foodsharing\Modules\Quiz;

use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Store\StoreGateway;

class QuizTransactions
{
    public function __construct(
        private readonly QuizSessionGateway $quizSessionGateway,
        private readonly StoreGateway $storeGateway,
        private readonly FoodsaverGateway $foodsaverGateway
    ) {
    }

    public function refreshQuizData(int $fsId, Role $fsRole): int
    {
        $this->refreshFsQuizRole($fsId);

        return $this->nextQuizTodo($fsId, $fsRole);
    }

    public function refreshFsQuizRole(int $fsId): int
    {
        foreach ([Role::AMBASSADOR, Role::STORE_MANAGER, Role::FOODSAVER] as $quizRole) {
            if ($this->quizSessionGateway->hasPassedQuiz($fsId, $quizRole->value)) {
                return $this->foodsaverGateway->setQuizRole($fsId, $quizRole->value);
            }
        }

        return $this->foodsaverGateway->setQuizRole($fsId, Role::FOODSHARER->value);
    }

    public function nextQuizTodo(int $fsId, Role $fsRole): int
    {
        $doesManageStores = (int)$this->storeGateway->getStoreCountForBieb($fsId) > 0;

        if ($fsRole == Role::FOODSAVER && !$this->quizSessionGateway->hasPassedQuiz($fsId, Role::FOODSAVER->value)) {
            return Role::FOODSAVER->value;
        } elseif (($fsRole == Role::STORE_MANAGER || $fsRole == Role::AMBASSADOR || $fsRole == Role::ORGA || $doesManageStores) && !$this->quizSessionGateway->hasPassedQuiz($fsId, Role::STORE_MANAGER->value)) {
            return Role::STORE_MANAGER->value;
        } elseif (($fsRole == Role::AMBASSADOR || $fsRole == Role::ORGA || $this->foodsaverGateway->isAdminForAnyGroupOrRegion($fsId)) && !$this->quizSessionGateway->hasPassedQuiz($fsId, Role::AMBASSADOR->value)) {
            return Role::AMBASSADOR->value;
        }

        return Role::FOODSHARER->value;
    }
}
