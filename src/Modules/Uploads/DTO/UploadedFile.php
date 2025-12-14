<?php

namespace Foodsharing\Modules\Uploads\DTO;

use Foodsharing\Modules\Core\DBConstants\Uploads\UploadUsage;

final class UploadedFile
{
    public function __construct(
        public string $filePath,
        public readonly int $fileSize,
        public readonly string $hashedBody,
        public readonly string $mimeType,
        public readonly ?int $uploaderId,
        public readonly ?UploadUsage $usedIn,
        public readonly ?int $usageId,
    ) {
    }
}
