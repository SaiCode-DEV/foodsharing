<?php

namespace Foodsharing\Modules\Donation;

use Foodsharing\Modules\Configuration\ConfigurationGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DBConstants\Configuration\ConfigurationCategory;
use Foodsharing\RestApi\Models\Donation\DonationDataResponse;
use ReflectionClass;
use ReflectionNamedType;

class DonationGateway extends ConfigurationGateway
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    /**
     * Reads the current admin data from the database.
     */
    public function getDonationData(): DonationDataResponse
    {
        $rows = $this->getEntries(ConfigurationCategory::DONATION->value);

        if (empty($rows)) {
            return new DonationDataResponse();
        }
        $mappedKeyValue = new DonationDataResponse();
        foreach ($rows as $key => $value) {
            $reflection = new ReflectionClass(DonationData::class);
            if ($reflection->hasProperty($key) && ($type = $reflection->getProperty($key)->getType())) {
                $typeName = $type instanceof ReflectionNamedType ? $type->getName() : (string)$type;
                $mappedKeyValue->$key = match ($typeName) {
                    'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                    'string' => (string)$value,
                    'int' => (int)$value,
                    default => null,
                };
            }
        }

        return $mappedKeyValue;
    }

    /**
     * Writes the admin data to the database (insert or update).
     */
    public function saveDonation(DonationData $donationData): void
    {
        $this->addOrUpdateEntries((array)$donationData, ConfigurationCategory::DONATION->value);
    }
}
