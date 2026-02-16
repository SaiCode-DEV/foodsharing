<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\SupportPage\SupportPageTransactions;
use Foodsharing\RestApi\Models\SupportPage\TicketModel;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag('support')]
final class SupportPageRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        private readonly SupportPageTransactions $supportPageTransactions,
        protected Session $session
    ) {
        parent::__construct($this->session);
    }

    #[OA\Post(summary: 'Creates a new support ticket')]
    #[Route('support/ticket', methods: ['POST'])]
    #[OA\Response(response: HttpResponse::HTTP_OK, description: 'Successful')]
    #[OA\Response(response: HttpResponse::HTTP_SERVICE_UNAVAILABLE, description: 'Support API is not available')]
    public function createTicket(
        #[MapRequestPayload] TicketModel $ticketModel,
        Request $request,
        RateLimiterFactory $supportTicketLimiter
    ): HttpResponse {
        $this->checkRateLimit($request, $supportTicketLimiter);

        $ticketId = $this->supportPageTransactions->createTicket($ticketModel);

        return $this->respondOK([
            'ticketId' => $ticketId
        ]);
    }
}
