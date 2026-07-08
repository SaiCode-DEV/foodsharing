<?php

namespace Foodsharing\Modules\Reaction;

use Foodsharing\Modules\Foodsaver\Profile;

class ReactionTransactions
{
    /**
     * Adds the given reactions to the right posts.
     * The given posts array is edited.
     */
    public function addReactionsToPosts(array $reactions, array &$posts): void
    {
        $postIdMap = [];
        foreach ($posts as &$post) { // generate index for quickly accessing posts via id
            $postIdMap[$post->id] = $post;
        }
        foreach ($reactions as $reaction) { // map reactions to posts
            $user = new Profile($reaction['foodsaver_id'], $reaction['foodsaver_name']);
            $postIdMap[$reaction['post_id']]->reactions[$reaction['key']][] = $user;
        }
    }
}
