<?php

namespace Foodsharing\Modules\Store\DTO;

use DateTime;
use Foodsharing\Modules\Categories\StoreCategoryType;
use Foodsharing\Modules\Core\DBConstants\Store\ConvinceStatus;
use Foodsharing\Modules\Core\DBConstants\Store\CooperationStatus;
use Foodsharing\Modules\Core\DBConstants\Store\PublicityStatus;
use Foodsharing\Modules\Core\DBConstants\Store\PublicTimes;
use Foodsharing\Modules\Core\DBConstants\Store\StickerStatus;
use Foodsharing\Modules\Core\DBConstants\Store\TeamSearchStatus;
use Foodsharing\Modules\Core\DTO\Address;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Core\DTO\MinimalIdentifier;
use Foodsharing\Modules\Region\DTO\MinimalRegionIdentifier;
use JMS\Serializer\Annotation\Type;
use OpenApi\Attributes as OA;

/**
 * This class is a representation of an Store.
 */
class Store
{
    /**
     * Unique identifier of the store in database.
     */
    public int $id;

    /**
     * Name of the store.
     */
    public string $name;

    /**
     * Region which is manages and is responsible for this store.
     */
    public MinimalRegionIdentifier $region;

    /**
     * Location of the store.
     */
    public GeoLocation $location;

    /**
     * Street name with street number.
     */
    public Address $address;

    /**
     * Public information about the store which is visible
     * for users which are looking for a store.
     */
    public string $publicInfo;

    /**
     * Enum which represents the expected pickup time range.
     */
    public PublicTimes $publicTime;

    /**
     * Identifier of the store category.
     *
     * @see StoreGateway::getStoreCategories()
     */
    public ?MinimalIdentifier $category = null;

    /**
     * Information about the store chain.
     *
     * Only set if store is related to a store chain.
     *
     * @see StoreGateway::getBasics_chain()
     */
    #[OA\Property(nullable: true)]
    public ?StoreChainInformation $chain = null;

    /**
     * Enum which represents the current state of cooperation between foodsharing and store.
     */
    public ?CooperationStatus $cooperationStatus = CooperationStatus::UNCLEAR;

    /**
     * Date of cooperation between store and foodsharing.
     */
    #[Type("DateTime<'Y-m-d'>")]
    public ?DateTime $cooperationStart = null;

    /**
     * String which describes the store.
     *
     * Only visible to store team members
     */
    #[OA\Property(nullable: true)]
    public ?string $description = null;

    /**
     * Contact information for store contact.
     *
     * Only visible to store managers or organaisators
     */
    #[OA\Property(nullable: true)]
    public ?ContactData $contact;

    /**
     * Duration in seconds before user can register to a pickup slot before pickup.
     *
     * - 0 : not set
     * - 604800: 1 Week
     * - 1209600: 2 Weeks
     * - 1814400: 3 Weeks
     * - 2419200: 4 Weeks
     */
    public int $calendarInterval = 0;

    /**
     * Enum which category of weight per pickup.
     *
     * - 0: UNCLEAR
     * - 1: 1-3 kg
     * - 2: 3-5 kg
     * - 3: 5-10 kg
     * - 4: 10-20 kg
     * - 5: 20-30 kg
     * - 6: 30-40 kg
     * - 7: 40-50 kg
     * - 8: more than 50 kg
     */
    public int $weight = 0;

    /**
     * Enum which represents the effort to create the cooperation betwee store and foodsharing.
     *
     * Only visible to store managers or organaisators
     */
    #[OA\Property(nullable: true)]
    public ?ConvinceStatus $effort = ConvinceStatus::NOT_SET;

    /**
     * Boolean which represents that store allow using for foodsharing publicity.
     *
     * Only visible to store team members
     */
    #[OA\Property(nullable: true)]
    public ?PublicityStatus $publicity = PublicityStatus::NOT_CHOSEN;

    /**
     * Boolean which mark store that they shows foodsharing sticker on the store.
     *
     * Only visible to store managers or organaisators
     */
    #[OA\Property(nullable: true)]
    public ?StickerStatus $showsSticker = StickerStatus::NOT_CHOSEN;

    /**
     * List of grocerie which are provided by the store.
     *
     * Only visible to store managers or organaisators
     *
     * @var int[] List of grocerie which are provided by the store
     */
    #[OA\Property(nullable: true)]
    #[Type('array<int>')]
    public ?array $groceries = null;

    /**
     * Status of team.
     */
    public TeamSearchStatus $teamStatus = TeamSearchStatus::CLOSED;

    /**
     * Whether a valid hygiene certificate is required for the store.
     */
    public bool $isHygieneRequired = false;

    /**
     * Whether users are required to be verified to apply to the store.
     */
    public bool $isVerifiedRequired = false;

    /**
     * Whether users are required to have a valid phone number to apply to the store.
     */
    public bool $isPhoneRequired = false;

    /**
     * Whether users are required to have a valid apply text to apply to the store.
     */
    public bool $isApplyTextRequired = false;

    /**
     * Configuration option to influence behavior of store.
     *
     * Only visible to store team members
     */
    #[OA\Property(nullable: true)]
    public ?StoreOptionModel $options = null;

    /**
     * Date of store creation in system.
     */
    #[Type("DateTime<'Y-m-d'>")]
    public DateTime $createdAt;

    /**
     * Date of last update of store information.
     *
     * Only visible to store managers or organaisators
     */
    #[OA\Property(nullable: true)]
    #[Type("DateTime<'Y-m-d'>")]
    public ?DateTime $updatedAt = null;

    /**
     * Category type of the store.
     */
    public StoreCategoryType $categoryType;

    public function __construct(GeoLocation $location)
    {
        $this->location = $location;
        $this->address = new Address();
        $this->options = new StoreOptionModel();
        $this->contact = new ContactData();
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();
    }

    public static function createFromArray($queryResult): Store
    {
        $obj = new Store(
            new GeoLocation($queryResult['lat'], $queryResult['lon'])
        );
        $obj->id = $queryResult['id'];
        $obj->name = $queryResult['name'];
        $obj->region = new MinimalRegionIdentifier($queryResult['regionId'], $queryResult['regionName'] ?? null);

        $obj->address->street = $queryResult['street'] ?? '';
        $obj->address->postalCode = $queryResult['zipCode'] ?? '';
        $obj->address->city = $queryResult['city'] ?? '';

        $obj->publicInfo = $queryResult['public_info'] ?? '';
        $obj->publicTime = PublicTimes::tryFrom($queryResult['public_time']);

        $obj->categoryType = StoreCategoryType::tryFrom($queryResult['categoryType']) ?? StoreCategoryType::PICKUP;
        $obj->category = MinimalIdentifier::createFromId($queryResult['categoryId']);
        $obj->chain = StoreChainInformation::createFromId($queryResult['chainId']);

        $obj->cooperationStatus = CooperationStatus::tryFrom($queryResult['cooperationStatus']);
        if ($queryResult['cooperationStart'] && $queryResult['cooperationStart'] != '0000-00-00') {
            $cooperationStart = DateTime::createFromFormat('Y-m-d', $queryResult['cooperationStart']);
            if ($cooperationStart) {
                $obj->cooperationStart = $cooperationStart;
            }
        }

        $obj->description = $queryResult['description'] ?? '';

        $obj->contact = ContactData::createFromArray($queryResult);

        $obj->calendarInterval = $queryResult['calendarInterval'];
        $obj->weight = $queryResult['weight'];
        $obj->effort = ConvinceStatus::tryFrom($queryResult['effort']);

        $obj->publicity = PublicityStatus::tryFrom($queryResult['publicity']);
        $obj->showsSticker = StickerStatus::tryFrom($queryResult['sticker']);

        $obj->teamStatus = TeamSearchStatus::tryFrom($queryResult['teamStatus']);

        $obj->options = StoreOptionModel::createFromArray($queryResult);

        $obj->isHygieneRequired = boolval($queryResult['hygiene_requirement']);

        $obj->isVerifiedRequired = boolval($queryResult['verified_requirement']);
        $obj->isPhoneRequired = boolval($queryResult['phone_requirement']);
        $obj->isApplyTextRequired = boolval($queryResult['apply_text_requirement']);

        $createdAt = DateTime::createFromFormat('Y-m-d', $queryResult['createdAt']);
        if ($createdAt) {
            $obj->createdAt = $createdAt;
        }

        if ($queryResult['updatedAt']) {
            $obj->updatedAt = DateTime::createFromFormat('Y-m-d', $queryResult['updatedAt']);
        } else {
            $obj->updatedAt = $obj->createdAt;
        }

        if (isset($queryResult['groceries']) && is_array($queryResult['groceries'])) {
            $obj->groceries = $queryResult['groceries'];
        }

        return $obj;
    }
}
