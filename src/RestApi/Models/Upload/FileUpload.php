<?php

namespace Foodsharing\RestApi\Models\Upload;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Represents a file that is uploaded to the API.
 */
class FileUpload
{
    #[OA\Property(description: 'Name of the uploaded file')]
    #[Assert\Length(min: 1)]
    #[Assert\NotBlank]
    public string $filename;

    #[OA\Property(description: 'Base64 encoded content of the file')]
    #[Assert\Length(min: 1)]
    public string $body;
}
