<?php

namespace Foodsharing\Modules\Uploads;

final class UploadAttributes
{
    public const MIN_WIDTH_AND_HEIGHT = 16;
    public const MAX_WIDTH = 800;
    public const MAX_HEIGHT = 500;
    public const MIN_QUALITY = 1;
    public const MAX_QUALITY = 100;
    public const DEFAULT_QUALITY = 80;
    public const MAX_UPLOAD_FILE_SIZE = 1_572_864; // 1.5 * 1024 * 1024
}
