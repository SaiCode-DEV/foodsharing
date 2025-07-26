<?php

namespace Foodsharing\Modules\Foodsaver\DTO;

/**
 * Contains all the data of a newsletter subscriber that is synchronised with Keila.
 */
class NewsletterSubscriber
{
    public function __construct(
        public int $id = 0,
        public string $firstName = '',
        public string $email = '',
    ) {
    }
}
