<?php

namespace Foodsharing\RestApi\Models\Mailbox;

use OpenApi\Annotations as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Connects the UUID of an uploaded file with the original filename. This is necessary when sending an email with
 * attachments because the original name is not saved when uploading a file.
 */
class EmailSendAttachment
{
    /**
     * UUID of the uploaded file.
     *
     * @OA\Property(example="uuid")
     */
    #[Assert\NotBlank]
    public string $uuid = '';

    /**
     * Original name of the file.
     *
     * @OA\Property(example="Testdatei.jpg")
     */
    #[Assert\NotBlank]
    public string $filename = '';
}
