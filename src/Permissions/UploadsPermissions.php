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
    public function mayAccessUpload(UploadedFile $file): bool
    {
        return match ($file->usedIn) {
            UploadUsage::EMAIL_ATTACHMENT => $this->session->mayRole() && $this->mailboxPermissions->mayMessage($file->usageId),
            default => true,
        };
    }

    /**
     * Returns if the current user may set the usage_id and usage_type of a previously uploaded file. This is only
     * allowed if the file is not already in use and if the user has uploaded that file.
     */
    public function maySetUploadUsage(string $uuid): bool
    {
        if (!$this->session->mayRole()) {
            return false;
        }

        $file = $this->uploadsGateway->getUploadedFile($uuid);
        if (!$file) {
            // File does not exist
            return false;
        }

        if ($file->usedIn != null) {
            // File is already in use
            return false;
        }

        // File needs to be uploaded by the user
        return $file->uploaderId === $this->session->id();
    }
}
