<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;

class PassportPermissions
{
    private readonly Session $session;
    private readonly FoodsaverGateway $foodsaverGateway;
    private readonly CommonPermissions $commonPermissions;

    public function __construct(Session $session, FoodsaverGateway $foodsaverGateway, CommonPermissions $commonPermissions)
    {
        $this->session = $session;
        $this->foodsaverGateway = $foodsaverGateway;
        $this->commonPermissions = $commonPermissions;
    }

    public function mayCreatePassportAsAmbassador(int $userId, int $regionId): bool
    {
        return $this->commonPermissions->mayAdministrateRegion($userId, $regionId);
    }

    public function mayCreatePassportAsUser(int $userId): bool
    {
        $foodsaverDetails = $this->foodsaverGateway->getFoodsaverDetails($userId);
        if (!$foodsaverDetails['verified']) {
            return false;
        }

        return $userId === $this->session->id();
    }
}
