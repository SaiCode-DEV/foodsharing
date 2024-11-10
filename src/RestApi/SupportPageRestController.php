<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\SupportPage\SupportPageTransactions;
use Foodsharing\RestApi\Models\SupportPage\TicketModel;
use FOS\RestBundle\Controller\Annotations as Rest;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Tag;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Tag('support')]
final class SupportPageRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        private readonly SupportPageTransactions $supportPageTransactions,
        protected Session $session
    ) {
        parent::__construct($this->session);
    }

    #[Post(summary: 'Creates a new support ticket')]
    #[Rest\Post(path: 'support/ticket')]
    #[Response(response: HttpResponse::HTTP_OK, description: 'Successful')]
    #[Response(response: HttpResponse::HTTP_BAD_REQUEST, description: 'Invalid data')]
    #[Response(response: HttpResponse::HTTP_SERVICE_UNAVAILABLE, description: 'Support API is not available')]
    #[ParamConverter('ticketModel', class: TicketModel::class, converter: 'fos_rest.request_body')]
    public function createTicket(TicketModel $ticketModel, ValidatorInterface $validator, Request $request,
        RateLimiterFactory $supportTicketLimiter): HttpResponse
    {
        $this->checkRateLimit($request, $supportTicketLimiter);
        $this->assertThereAreNoValidationErrors($validator, $ticketModel);

        $ticketId = $this->supportPageTransactions->createTicket($ticketModel);

        return $this->respondOK([
            'ticketId' => $ticketId
        ]);
    }
}
