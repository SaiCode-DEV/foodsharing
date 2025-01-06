<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Quiz\DTO;

use OpenApi\Attributes as OA;

class ActiveQuestion
{
    public Question $question;

    #[OA\Property(description: 'Time already spent on this question. Only set if current quiz session is timed.', example: 10)]
    public ?int $questionAge = null;

    #[OA\Property(description: 'Whether the last question was skipped because it timed out.', example: false)]
    public bool $timedOut;
}
