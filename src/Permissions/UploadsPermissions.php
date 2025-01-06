<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Uploads\UploadUsage;
use Foodsharing\Modules\Uploads\DTO\UploadedFile;
use Foodsharing\Modules\Uploads\UploadsGateway;

final readonly class UploadsPermissions
{
    public function __construct(
        private Session $session,
        private UploadsGateway $uploadsGateway,
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
    public function mayAccessUpload(string $uuid): bool
    {
        $file = $this->uploadsGateway->getUploadedFile($uuid);
        if (!$file) {
            // Users should not be able to download a file before its usage has been set
            return false;
        }

        return match ($file->usedIn) {
            UploadUsage::EMAIL_ATTACHMENT => $this->session->mayRole() && $this->mailboxPermissions->mayMessage($file->usageId),
            default => true,
        };
    }
}
