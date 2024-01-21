<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\Models;

use OpenApi\Attributes as OA;

class HttpCodeMessageModel
{
    #[OA\Property(title: 'HTTP status code', type: 'integer')]
    public readonly int $code;

    #[OA\Property(title: 'further information', type: 'string')]
    public readonly string $message;

    public function __construct(int $code, string $message)
    {
        $this->code = $code;
        $this->message = $message;
    }
}
