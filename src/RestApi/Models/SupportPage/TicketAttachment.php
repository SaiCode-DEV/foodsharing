<?php

namespace Foodsharing\RestApi\Models\SupportPage;

/**
 * Contains the content and meta data for a file that is attached to a support ticket. Attachments to support tickets
 * are not uploaded to the regular API because the files are forwarded to the support system immediately and do not need
 * to remain in the database.
 */
class TicketAttachment
{
    /**
     * Original file name used for displaying.
     */
    public string $fileName;
    /**
     * The file's content type.
     */
    public string $contentType;
    /**
     * The file's content.
     */
    public string $content;

    public static function create(
        string $fileName,
        string $contentType,
        string $content,
    ): TicketAttachment {
        $e = new TicketAttachment();
        $e->fileName = $fileName;
        $e->contentType = $contentType;
        $e->content = $content;

        return $e;
    }
}
