<?php

namespace Foodsharing\RestApi\Models\Passport;

use JMS\Serializer\Annotation\Type;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class that represents the data for creating a region passport.
 *
 * This class contains the user IDs for which a region passport should be generated.
 * The data is provided in a format in which it is sent to the client.
 */
#[OA\Schema(description: 'Data for creating a region passport')]
class CreateRegionPassportModel
{
    /**
     * Users for passport generation as array.
     */
    #[OA\Property(description: 'Users for passport generation', type: 'array', items: new OA\Items(type: 'integer'))]
    #[Assert\All(new Assert\Positive())]
    #[Type('array<int>')]
    public array $userIds = [];

    #[OA\Property(description: 'Flag to create PDF', type: 'boolean')]
    #[Assert\Type('boolean')]
    public ?bool $createPdf = null;

    #[OA\Property(description: 'Flag to renew the passport', type: 'boolean')]
    #[Assert\NotNull]
    #[Assert\Type('boolean')]
    public ?bool $renew = null;

    #[OA\Property(description: 'Flag to create bell and mail', type: 'boolean')]
    #[Assert\Type('boolean')]
    public bool $informUser = true;

    #[OA\Property(description: 'Flag for automatic paper selection. If true, passport size is used for a single passport,
    DIN A4 for multiple. If false, DIN A4 is always used.', type: 'boolean')]
    #[Assert\NotNull]
    #[Assert\Type('boolean')]
    public bool $usePaperSizeDinA4;
}
