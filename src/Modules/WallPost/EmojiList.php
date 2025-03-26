<?php

namespace Foodsharing\Modules\WallPost;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class EmojiList
{
    public const EMOJIS = [
        'banana' => '🍌',
        'laughing' => '😆',
        'heart' => '❤️',
        'thumbsup' => '👍',
        'thumbsdown' => '👎'
    ];

    public static function assertIsValidEmoji(string $key): void
    {
        if (!isset(EmojiList::EMOJIS[$key])) {
            throw new BadRequestHttpException("$key is not a valid reaction key.");
        }
    }
}
