<?php

namespace Foodsharing\Modules\Uploads;

final class UploadAttributes
{
    public const int MIN_WIDTH_AND_HEIGHT = 16;
    public const int MAX_WIDTH = 800;
    public const int MAX_HEIGHT = 500;
    public const int MIN_QUALITY = 1;
    public const int MAX_QUALITY = 100;
    public const int DEFAULT_QUALITY = 80;
    public const int MAX_UPLOAD_FILE_SIZE = 1_572_864; // 1.5 * 1024 * 1024
}
