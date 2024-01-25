<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Permissions\NewsletterEmailPermissions;
use Foodsharing\Utility\EmailHelper;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Rest controller for newsletter functions.
 */
final class NewsletterRestController extends AbstractFOSRestController
{
    private NewsletterEmailPermissions $newsletterEmailPermissions;
    private Session $session;
    private EmailHelper $emailHelper;

    private const NOT_ALLOWED = 'not allowed';
    private const INVALID_ADDRESS = 'invalid address';

    public function __construct(
        NewsletterEmailPermissions $newsletterEmailPermissions,
        Session $session,
        EmailHelper $emailHelper
    ) {
        $this->newsletterEmailPermissions = $newsletterEmailPermissions;
        $this->session = $session;
        $this->emailHelper = $emailHelper;
    }

    /**
     * Sends a test newsletter email to the given address. Returns 200 on success, 401 if the current user may not
     * send newsletters, or 400 if the email address is invalid.
     *
     * @OA\Tag(name="newsletter")
     */
    #[Rest\Post('newsletter/test')]
    #[Rest\RequestParam(name: 'address')]
    #[Rest\RequestParam(name: 'subject')]
    #[Rest\RequestParam(name: 'message')]
    public function sendTestEmail(ParamFetcher $paramFetcher): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('', self::NOT_ALLOWED);
        }
        if (!$this->newsletterEmailPermissions->mayAdministrateNewsletterEmail()) {
            throw new AccessDeniedHttpException(self::NOT_ALLOWED);
        }

        $address = $paramFetcher->get('address');
        if (!$this->emailHelper->validEmail($address)) {
            throw new BadRequestHttpException(self::INVALID_ADDRESS);
        }

        $this->emailHelper->libmail(false, $address, $paramFetcher->get('subject'), $paramFetcher->get('message'));

        return $this->handleView($this->view([], 200));
    }
}
