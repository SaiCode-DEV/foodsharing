<?php

namespace Foodsharing\Modules\Blog\DTO;

use DateTime;

class BlogPost
{
    public int $id;
    public string $title;
    public string $content;
    public DateTime $publishedAt;
    /**
     * Name of this post's author or null if the author's profile was deleted.
     */
    public ?string $authorName = null;
    public string $picture;

    public static function create(
        int $id,
        string $title,
        string $content,
        DateTime $publishedAt,
        ?string $authorName,
        string $picture
    ): BlogPost {
        $b = new BlogPost();
        $b->id = $id;
        $b->title = $title;
        $b->content = $content;
        $b->publishedAt = $publishedAt;
        $b->authorName = $authorName;
        $b->picture = $picture;

        return $b;
    }
}
