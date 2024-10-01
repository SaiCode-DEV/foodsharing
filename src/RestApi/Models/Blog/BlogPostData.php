<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\Models\Blog;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(description: 'Contains data for adding or editing a blog post')]
class BlogPostData
{
    #[OA\Property(description: 'Id of the blog post when editing an existing post. Can be null when adding a new post', type: 'integer')]
    public ?int $id;

    #[OA\Property(description: 'Id of the region with which this post is associated.', type: 'integer')]
    public int $regionId;

    #[OA\Property(description: 'Title of the post', type: 'string')]
    #[Assert\Length(min: 1, max: 100)]
    public string $title;

    #[OA\Property(description: 'Short teaser text that is shown in the list of blog posts', type: 'string')]
    #[Assert\Length(max: 500)]
    public string $teaser;

    #[OA\Property(description: 'The full content of the post', type: 'string')]
    #[Assert\Length(max: 16777215)]
    public string $content;

    #[OA\Property(description: 'UUID of an uploaded picture. If null, the post will have no picture.', type: 'string')]
    public ?string $picture;

    public function __construct(
        ?int $id,
        int $regionId,
        string $title,
        string $teaser,
        string $content,
        ?string $picture,
    ) {
        $this->id = $id;
        $this->regionId = $regionId;
        $this->title = $title;
        $this->teaser = $teaser;
        $this->content = $content;
        $this->picture = $picture;
    }
}
