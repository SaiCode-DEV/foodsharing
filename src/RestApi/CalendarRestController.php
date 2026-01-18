<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Calendar\CalendarTransactions;
use Foodsharing\Modules\Calendar\DTO\FormattingType;
use Foodsharing\Modules\Calendar\DTO\IncludeEventsType;
use Foodsharing\Modules\Settings\SettingsGateway;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Provides endpoints for exporting pickup dates and other events to iCal and managing access tokens.
 */
#[OA\Tag(name: 'calendar')]
class CalendarRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        protected Session $session,
        private readonly SettingsGateway $settingsGateway,
        private readonly CalendarTransactions $calendarTransactions,
    ) {
        parent::__construct($session);
    }

    #[OA\Get(summary: 'Returns the user\'s current access token')]
    #[Route('calendar/token', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'token', type: 'string', example: 'fbb17c571c69affd1f18')
    ]))]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'No access token created')]
    public function getToken(): Response
    {
        $this->assertLoggedIn();

        $token = $this->settingsGateway->getApiToken($this->session->id());

        if (is_null($token)) {
            throw new NotFoundHttpException('No access token created');
        }

        return $this->respondOK(['token' => $token]);
    }

    #[OA\Put(summary: 'Creates a new random access token for the user, replacing the old one')]
    #[Route('calendar/token', methods: ['PUT'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(
            property: 'token',
            type: 'string',
            example: 'fbb17c571c69affd1f18',
            description: 'the newly created calendar access token'
        )
    ]))]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    public function createToken(): Response
    {
        $this->assertLoggedIn();

        $token = $this->calendarTransactions->createToken($this->session->id());

        return $this->respondOK(['token' => $token]);
    }

    #[OA\Delete(summary: 'Removes the user\'s access token')]
    #[Route('calendar/token', methods: ['DELETE'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success - deleted or nothing to delete')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    public function deleteToken(): Response
    {
        $this->assertLoggedIn();

        $this->settingsGateway->removeApiToken($this->session->id());

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Returns the user\'s foodsharing calendar as iCal')]
    #[Route('calendar/{token}', methods: ['GET'], requirements: ['token' => '[0-9a-f]+'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Access token invalid')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid query parameters')]
    #[OA\QueryParameter(name: 'formatting', description: 'How to format description texts.<br>One of `alt` (default), `html` or `text`')]
    #[OA\QueryParameter(name: 'events', description: 'What events to include in the calendar.<br>One of `every` (default), `invitations`, `maybe`, `accepted` or `none`')]
    #[OA\QueryParameter(name: 'pickups', description: 'Whether to include pickups in the calendar.')]
    #[OA\QueryParameter(name: 'history', description: 'Whether to include past entries (up to 2 weeks back) in the calendar.')]
    #[OA\QueryParameter(name: 'reminders', example: '15,120', description: 'List of reminder times in minutes. These reminders are applied to each calendar event.')]
    public function listAppointments(
        string $token,
        #[MapQueryParameter] ?FormattingType $formatting,
        #[MapQueryParameter('events')] ?IncludeEventsType $includedEvents,
        #[MapQueryParameter('pickups')] ?bool $includePickups,
        #[MapQueryParameter('history')] ?bool $includeHistory,
        #[MapQueryParameter('reminders')] ?string $reminders,
    ): Response {
        // check access token
        $userId = $this->settingsGateway->getUserForToken($token);
        if (!$userId) {
            throw new AccessDeniedHttpException('The calendar token is invalid');
        }

        $reminders = (!is_null($reminders) && strlen($reminders) > 0) ? explode(',', $reminders) : [];
        if (array_any($reminders, fn ($value) => !is_numeric($value) || $value <= 0)) {
            throw new BadRequestHttpException('Invalid reminder value');
        }

        $appointments = $this->calendarTransactions->listAppointments(
            $userId,
            $formatting ?? FormattingType::ALT,
            $includedEvents ?? IncludeEventsType::INVITATIONS,
            $includePickups ?? true,
            $includeHistory ?? true,
            $reminders
        );

        return new Response($appointments, Response::HTTP_OK, [
            'content-type' => 'text/calendar',
            'content-disposition' => 'attachment; filename="calendar.ics"'
        ]);
    }
}
