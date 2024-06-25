<?php

namespace Foodsharing\Modules\Uploads\DTO;

use Foodsharing\Modules\Core\DBConstants\Uploads\UploadUsage;

final class UploadedFile
{
    public function __construct(
        public string $filePath,
        readonly public int $fileSize,
        readonly public string $hashedBody,
        readonly public string $mimeType,
        readonly public int $uploaderId,
        readonly public ?UploadUsage $usedIn,
        readonly public ?int $usageId,
    ) {
    }
}
