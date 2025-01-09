<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Permissions\NewsletterEmailPermissions;
use Foodsharing\RestApi\Models\Newsletter\NewsletterTestEmail;
use Foodsharing\Utility\EmailHelper;
use FOS\RestBundle\Controller\Annotations as Rest;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Rest controller for newsletter functions.
 */
#[OA\Tag('newsletter')]
final class NewsletterRestController extends AbstractFoodsharingRestController
{
    private const string NOT_ALLOWED = 'not allowed';
    private const string INVALID_ADDRESS = 'invalid address';

    public function __construct(
        private readonly NewsletterEmailPermissions $newsletterEmailPermissions,
        private readonly EmailHelper $emailHelper,
        protected Session $session
    ) {
        parent::__construct($this->session);
    }

    #[OA\Post(summary: 'Sends a test newsletter email to the given address. Returns 200 on success, 401 if the current
     user may not send newsletters, or 400 if the email address is invalid.')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid recipient email address')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    #[Rest\Post('newsletter/test')]
    public function sendTestEmail(#[MapRequestPayload] NewsletterTestEmail $testEmail): Response
    {
        $this->assertLoggedIn();
        if (!$this->newsletterEmailPermissions->mayAdministrateNewsletterEmail()) {
            throw new AccessDeniedHttpException(self::NOT_ALLOWED);
        }

        if (!$this->emailHelper->validEmail($testEmail->address)) {
            throw new BadRequestHttpException(self::INVALID_ADDRESS);
        }

        $this->emailHelper->libmail(false, $testEmail->address, $testEmail->subject, $testEmail->message);

        return $this->respondOK();
    }
}
