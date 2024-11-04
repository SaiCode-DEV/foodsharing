<?php

namespace Foodsharing\Modules\Store\DTO;

use Foodsharing\Modules\Core\DTO\PatchGeoLocation;
use Foodsharing\Validator\MarkdownOrPlainText;
use JMS\Serializer\Annotation\Type;
use OpenApi\Annotations as OA;
use Symfony\Component\Validator\Constraints as Assert;

class PatchStore
{
    /**
     * String which is the name of store.
     */
    #[Assert\Length(max: 120)]
    public ?string $name = null;

    /**
     * Identifier of region which manages and responsible for this store.
     */
    #[Assert\Positive]
    public ?int $regionId = null;

    /**
     * Object with geographic location of store.
     */
    #[Assert\Valid]
    public ?PatchGeoLocation $location = null;

    /**
     * Object with address of store.
     */
    #[Assert\Valid]
    public ?PatchAddress $address = null;

    /**
     * String with public information about the store which is visible
     * for users which are looking for a store.
     */
    #[Assert\Length(max: 200)]
    #[MarkdownOrPlainText]
    public ?string $publicInfo = null;

    /**
     * Enum which represents the expected pickup time range.
     *
     * - 0: NOT_SET
     * - 1: IN_THE_MORNING
     * - 2: AT_NOON_IN_THE_AFTERNOON
     * - 3: IN_THE_EVENING
     * - 4: AT_NIGHT
     */
    #[Assert\Range(min: 0, max: 4)]
    public ?int $publicTime = null;

    /**
     * Identifier of the store category.
     */
    #[Assert\Range(min: 0)]
    public ?int $categoryId = null;

    /**
     * Identifier of the store chain.
     */
    #[Assert\Range(min: 0)]
    public ?int $chainId = null;

    /**
     * Enum which represents the current state of cooperation between foodsharing and store.
     *
     * - 0: UNCLEAR
     * - 1: NO_CONTACT
     * - 2: IN_NEGOTIATION
     * - 4: DOES_NOT_WANT_TO_WORK_WITH_US
     * - 5:COOPERATION_ESTABLISHED
     * - 6: GIVES_TO_OTHER_CHARITY
     * - 7: PERMANENTLY_CLOSED
     */
    #[Assert\Range(min: 0)]
    public ?int $cooperationStatus = null;

    /**
     * String which describes the store.
     */
    #[Assert\Length(max: 16_777_215)]
    public ?string $description = null;

    /**
     * Object with all contact information of store.
     */
    #[Assert\Valid]
    public ?PatchContactData $contact = null;

    /**
     * Date of cooperation between store and foodsharing.
     */
    #[Assert\Date]
    public ?string $cooperationStart = null;

    /**
     * Duration in seconds before user can register to a pickup slot before pickup.
     *
     * - 604800: 1 Week
     * - 1209600: 2 Weeks
     * - 1814400: 3 Weeks
     * - 2419200: 4 Weeks
     */
    #[Assert\Range(min: 0, max: 10_000_000_000)]
    public ?int $calendarInterval = null;

    /**
     * Enum which category of weight per pickup.
     *
     * - 0: UNCLEAR
     * - 1: 1-3 kg
     * - 2: 3-5 kg
     * - 3: 5-10 kg
     * - 4: 10-20 kg
     * - 5: 20-30 kg
     * - 6: 40-50 kg
     * - 7: more then 50 kg
     */
    #[Assert\Range(min: 0, max: 7)]
    public ?int $weight = null;

    /**
     * Enum which represents the effort to create the cooperation betwee store and foodsharing.
     *
     * - 0: NOT_SET
     * - 1: NO_PROBLEM_AT_ALL
     * - 2: AFTER_SOME_PERSUASION
     * - 3: DIFFICULT_NEGOTIATION
     * - 4: LOOKED_BAD_BUT_WORKED
     */
    #[Assert\Range(min: 0, max: 4)]
    public ?int $effort = null;

    /**
     * Boolean which mark store that they shows foodsharing sticker on the store.
     */
    public ?bool $showsSticker = null;

    /**
     * Boolean which represents that store allows using for foodsharing publicity.
     */
    public ?bool $publicity = null;

    /**
     * Enum which represent the state of searching members.
     *
     * - CLOSED = 0 No new members accepted
     * - OPEN = 1 Open for members
     * - OPEN_SEARCHING = 2 Requires new members
     */
    #[Assert\Range(min: 0, max: 2)]
    public ?int $teamStatus = null;

    /**
     * Object with options for managing the store.
     */
    #[Assert\Valid]
    public ?PatchStoreOptionModel $options = null;

    /**
     * List of grocerie which are provided by the store.
     *
     * @OA\Property(type="array", @OA\Items(type="integer"))
     *
     * @var int[] List of grocerie which are provided by the store
     */
    #[Assert\All(new Assert\Positive())]
    #[Type('array<int>')]
    public ?array $groceries = null;
}
