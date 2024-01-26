<?php

namespace Foodsharing\Modules\Foodsaver;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Basket\BasketGateway;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Quiz\QuizSessionGateway;
use Foodsharing\Modules\Store\StoreTransactions;

class FoodsaverTransactions
{
    private readonly FoodsaverGateway $foodsaverGateway;
    private readonly QuizSessionGateway $quizSessionGateway;
    private readonly BasketGateway $basketGateway;
    private readonly StoreTransactions $storeTransactions;
    private readonly Session $session;

    public function __construct(
        FoodsaverGateway $foodsaverGateway,
        QuizSessionGateway $quizSessionGateway,
        BasketGateway $basketGateway,
        StoreTransactions $storeTransactions,
        Session $session
    ) {
        $this->foodsaverGateway = $foodsaverGateway;
        $this->quizSessionGateway = $quizSessionGateway;
        $this->basketGateway = $basketGateway;
        $this->storeTransactions = $storeTransactions;
        $this->session = $session;
    }

    public function downgradeAndBlockForQuizPermanently(int $fsId): int
    {
        $this->quizSessionGateway->blockUserForQuiz($fsId, Role::FOODSAVER);

        $this->storeTransactions->leaveAllStoreTeams($fsId);

        return $this->foodsaverGateway->downgradePermanently($fsId);
    }

    public function deleteFoodsaver(int $foodsaverId, ?string $reason): void
    {
        // set all active baskets of the user to deleted
        $this->basketGateway->removeActiveUserBaskets($foodsaverId);

        $this->storeTransactions->leaveAllStoreTeams($foodsaverId);

        // delete the user
        $this->foodsaverGateway->deleteFoodsaver($foodsaverId, $this->session->id(), $reason);
    }
}
