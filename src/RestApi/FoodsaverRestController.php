<?php

namespace Foodsharing\RestApi;

use DateTime;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Foodsaver\DTO\EventAgendaEntry;
use Foodsharing\Modules\Foodsaver\DTO\PickupAgendaEntry;
use Foodsharing\Modules\Foodsaver\FoodsaverTransactions;
use Foodsharing\Permissions\ProfilePermissions;
use Foodsharing\Utility\Requirement as FsRequirement;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'foodsaver')]
final class FoodsaverRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        private readonly ProfilePermissions $profilePermissions,
        private readonly FoodsaverTransactions $foodsaverTransactions,
        protected Session $session,
    ) {
        parent::__construct($this->session);
    }

    #[OA\Get(summary: 'Lists a users agenda on a specific day, including pickups and events')]
    #[Route('users/{foodsaverId}/agenda/{date}', methods: ['GET'], requirements: [
        'foodsaverId' => Requirement::POSITIVE_INT,
        'date' => FsRequirement::ISO_DATE_TIME
    ])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array',
        items: new OA\Items(oneOf: [
            new OA\Property(ref: new Model(type: PickupAgendaEntry::class)),
            new OA\Property(ref: new Model(type: EventAgendaEntry::class)),
        ])
    ))]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid date')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function listSameDayAgenda(int $foodsaverId, DateTime $date): Response
    {
        $this->assertLoggedIn();

        if (!$this->profilePermissions->maySeePickups($foodsaverId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $day = $this->normalizeDateToServerTimezone($date);
        $agenda = $this->foodsaverTransactions->getAgenda($foodsaverId, $day);

        return $this->respondOK($agenda);
    }
}
