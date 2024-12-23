<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Store\PickupGateway;
use Foodsharing\Permissions\ProfilePermissions;
use Foodsharing\Utility\TimeHelper;
use FOS\RestBundle\Controller\Annotations as Rest;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class FoodsaverRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        private readonly PickupGateway $pickupGateway,
        private readonly ProfilePermissions $profilePermissions,
        protected Session $session
    ) {
        parent::__construct($this->session);
    }

    #[OA\Tag(name: 'foodsaver')]
    #[Rest\Get('foodsaver/{fsId}/pickups/{onDate}', requirements: ['fsId' => '\d+', 'onDate' => '[^/]+'])]
    #[OA\Get(summary: 'Lists all pickups into which a user is signed in on a specific day, including unconfirmed ones. This only works for future pickups.')]
    public function listSameDayPickups(int $fsId, string $onDate): Response
    {
        $this->assertLoggedIn();

        if (!$this->profilePermissions->maySeePickups($fsId)) {
            throw new AccessDeniedHttpException();
        }

        // convert date string into datetime object
        $day = TimeHelper::parsePickupDate($onDate);
        $pickups = $this->pickupGateway->getSameDayPickupsForUser($fsId, $day);

        return $this->respondOK($pickups);
    }
}
