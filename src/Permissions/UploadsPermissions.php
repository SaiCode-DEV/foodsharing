<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Uploads\DTO\UploadedFile;

final class UploadsPermissions
{
    private readonly Session $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * Returns if a previously uploaded file may be used as attachment for an email that the user wants to send.
     */
    public function mayUseUploadAsEmailAttachment(UploadedFile $file): bool
    {
        return $file->uploaderId === $this->session->id();
    }
}
