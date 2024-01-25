<?php

namespace Foodsharing\Modules\StoreChain\DTO;

use Foodsharing\Validator\NoHtml;
use Foodsharing\Validator\NoMarkdown;
use Foodsharing\Validator\NoMultiLineText;
use JMS\Serializer\Annotation\Type;
use OpenApi\Annotations as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class that represents the data of a store chain, in a format in which it is sent to the client.
 * This is not an entity class, it does not provide any domain logic nor does it contain any access
 * logic. You can see it more like a Data Transfer Object (DTO) used to pass a chains data between
 * parts of the application in a unified format.
 */
class PatchStoreChain
{
    /**
     * Name of the chain.
     *
     * Field does not support HTML, Markdown or multiline strings.
     *
     * @OA\Property(example="MyChain GmbH")
     *
     * @NoHtml
     *
     * @NoMultiLineText
     * @NoMarkdown
     */
    #[Assert\Length(min: 1, max: 120)]
    public ?string $name = null;

    /**
     * Indicates the cooperation status of this chain.
     * - '0' - Not Cooperating
     * - '1' - Waiting, i.e. in negotiation
     * - '2' - Cooperating.
     *
     * @OA\Property(enum={0, 1, 2}, example=2)
     */
    #[Assert\Range(min: 0, max: 2)]
    public ?int $status = null;

    /**
     * ZIP code of the chains headquater.
     *
     * @OA\Property(example="48149", nullable=true)
     *
     * @NoHtml
     *
     * @NoMultiLineText
     * @NoMarkdown
     */
    #[Assert\Length(min: 1, max: 5)]
    public ?string $headquartersZip = null;

    /**
     * City of the chains headquater.
     *
     * Field does not support HTML, Markdown or multiline strings.
     *
     * @OA\Property(example="Münster", nullable=true)
     *
     * @NoHtml
     *
     * @NoMultiLineText
     * @NoMarkdown
     */
    #[Assert\Length(min: 1, max: 50)]
    public ?string $headquartersCity = null;

    /**
     * Country of the chains headquater.
     *
     * Field does not support HTML, Markdown or multiline strings.
     *
     * @OA\Property(example="Germany")
     *
     * @NoMultiLineText
     * @NoMarkdown
     */
    #[Assert\Length(max: 50)]
    public ?string $headquartersCountry = null;

    /**
     * Whether the chain can be referred to in press releases.
     */
    public ?bool $allowPress = null;

    /**
     * Identifier of a forum thread related to this chain.
     *
     * @OA\Property(example=12345)
     */
    #[Assert\Range(min: 0)]
    public ?int $forumThread = null;

    /**
     * Miscellaneous notes.
     *
     * Field does not support HTML, Markdown or multiline strings.
     *
     * @OA\Property(example="Cooperating since 2021", nullable=true)
     *
     * @NoHtml
     *
     * @NoMultiLineText
     * @NoMarkdown
     */
    #[Assert\Length(max: 200)]
    public ?string $notes = null;

    /**
     * Information about the chain to be displayed on every related stores page.
     *
     * @OA\Property(example="Pickup times between 10:00 and 12:15", nullable=true)
     */
    #[Assert\Length(max: 16777215)]
    public ?string $commonStoreInformation = null;

    /**
     * Identifiers of key account managers.
     *
     * @OA\Property(type="array", description="Managers of this chain",	items={"type"="integer"})
     *
     * @var int[] List of grocerie which are provided by the store
     */
    #[Assert\All(new Assert\Positive())]
    #[Type('array<int>')]
    public ?array $kams = null;

    /**
     * Count of estimated stores.
     *
     * Only visible to members of AG store chain
     *
     * @OA\Property(example=12)
     */
    #[Assert\Range(min: 0)]
    public ?int $estimatedStoreCount = null;
}
