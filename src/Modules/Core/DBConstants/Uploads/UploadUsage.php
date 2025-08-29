<?php

namespace Foodsharing\Modules\Core\DBConstants\Uploads;

/**
 * Column 'used_in' in table 'uploads'.
 *
 * Denotes in which module an uploaded file is being used.
 *
 * INT(4) UNSIGNED NOT NULL
 */
enum UploadUsage: int
{
    /*
     * The image is a user's profile photo (column fs_foodsaver.photo). The usage_id links to fs_foodsaver.id.
     */
    case PROFILE_PHOTO = 0;
    /*
     * The image is the header picture of a food share point (column fs_fairteiler.photo). The usage_id links to
     * fs_fairteiler.id.
     */
    case FOOD_SHARE_POINT_TITLE = 1;
    /*
     * The image is the header picture of a blog post (column fs_blog_post.picture). The usage_id links to
     * fs_blog_post.id.
     */
    case BLOG_POST = 2;
    /*
     * The image is the header picture of a working group (column fs_bezirk.photo). The usage_id links to fs_bezirk.id.
     */
    case WORKING_GROUP_TITLE = 3;
    /*
     * The image is a photo of a food basket (column fs_basket.photo). The usage_id links to fs_basket.id.
     */
    case BASKET = 4;
    /*
     * The image is attached to a wall post (column fs_wallpost.attach). The usage_id links to `fs_wallpost.id`.
     */
    case WALL_POST = 5;
    /*
     * The file is attached to an email (column fs_mailbox_message.attach). The usage_id links to fs_mailbox_message.id.
     */
    case EMAIL_ATTACHMENT = 6;
    /*
     * The file is attached to a resource (column fs_resource.images). The usage_id links to fs_resource.id.
     */
    case RESOURCE = 7;
}
