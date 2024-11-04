<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Banana;

use Foodsharing\Modules\Banana\DTO\Banana;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;

class BananaTransactions
{
    public function __construct(
        private readonly BananaGateway $bananaGateway,
        private readonly BellGateway $bellGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
    ) {
    }

    public function addBanana(int $recipientId, int $senderId, string $message): Banana
    {
        $this->bananaGateway->addBanana($recipientId, $senderId, $message);

        $bell = Bell::create(
            'banana_given_title',
            'banana_given',
            'fas fa-gifts',
            ['href' => '/profile/' . $recipientId],
            ['name' => $this->foodsaverGateway->getFoodsaverName($senderId)],
            BellType::createIdentifier(BellType::BANANA, $recipientId, $senderId)
        );
        $this->bellGateway->addBell($recipientId, $bell);

        return $this->bananaGateway->getBanana($recipientId, $senderId);
    }
}
