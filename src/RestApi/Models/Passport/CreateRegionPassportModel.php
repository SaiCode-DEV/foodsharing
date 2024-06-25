<?php

namespace Foodsharing\RestApi\Models\Passport;

use JMS\Serializer\Annotation\Type;
use OpenApi\Annotations as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class that represents the data for creating a region passport.
 *
 * This class contains the user IDs for which a region passport should be generated.
 * The data is provided in a format in which it is sent to the client.
 */
class CreateRegionPassportModel
{
    /**
     * Users for passport generation as array.
     *
     * @OA\Property(type="array", description="Users for passport generation",	items={"type"="integer"})
     */
    #[Assert\All(new Assert\Positive())]
    #[Type('array<int>')]
    public array $userIds = [];
}
