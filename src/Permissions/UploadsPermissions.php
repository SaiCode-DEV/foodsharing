<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Uploads\UploadUsage;
use Foodsharing\Modules\Uploads\DTO\UploadedFile;

final readonly class UploadsPermissions
{
    public function __construct(
        private Session $session,
        private MailboxPermissions $mailboxPermissions,
    ) {
    }

    /**
     * Returns if a previously uploaded file may be used as attachment for an email that the user wants to send.
     */
    public function mayUseUploadAsEmailAttachment(UploadedFile $file): bool
    {
        return $file->uploaderId === $this->session->id();
    }

    /**
     * Returns whether a user may download a previously uploaded file.
     */
    public function mayAccessUpload(UploadedFile $file): bool
    {
        return match ($file->usedIn) {
            UploadUsage::EMAIL_ATTACHMENT => $this->session->mayRole() && $this->mailboxPermissions->mayMessage($file->usageId),
            default => true,
        };
    }
}
