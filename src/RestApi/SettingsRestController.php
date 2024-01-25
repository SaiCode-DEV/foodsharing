<?php

namespace Foodsharing\RestApi;

use Carbon\Carbon;
use Exception;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\SleepStatus;
use Foodsharing\Modules\Settings\SettingsGateway;
use Foodsharing\Modules\Settings\SettingsTransactions;
use Foodsharing\RestApi\Models\Settings\EmailChangeRequest;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use OpenApi\Attributes as OA2;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SettingsRestController extends AbstractFOSRestController
{
    public function __construct(
        private readonly SettingsGateway $settingsGateway,
        private readonly SettingsTransactions $settingsTransactions,
        private readonly Session $session
    ) {
    }

    /**
     * Sets the current user's sleep mode. For the temporary mode, both 'from' and 'to' need to be given. Both are
     * assumed to be in the format 'd.m.Y'. For other modes the two fields will be ignored. Optionally, a message
     * can be added.
     *
     * @OA\Tag(name="user")
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="400", description="Invalid mode or parameters")
     * @OA\Response(response="401", description="Not logged in")
     */
    #[Rest\Patch('user/sleepmode')]
    #[Rest\RequestParam(name: 'mode', nullable: false, allowBlank: false, requirements: '\d+', description: 'sleep mode as an integer')]
    #[Rest\RequestParam(name: 'from', nullable: true, description: 'start date of the sleep interval')]
    #[Rest\RequestParam(name: 'to', nullable: true, description: 'end date of the sleep interval')]
    #[Rest\RequestParam(name: 'message', nullable: true, description: 'optional sleep mode message')]
    public function setSleepStatus(ParamFetcher $paramFetcher): Response
    {
        $userId = $this->session->id();
        if (!$userId) {
            throw new UnauthorizedHttpException('');
        }

        $mode = $paramFetcher->get('mode');
        if (!SleepStatus::isValid($mode)) {
            throw new BadRequestHttpException('invalid sleep status');
        }

        // parse from and to date, if they are needed
        $from = null;
        $to = null;
        if ($mode == SleepStatus::TEMP) {
            try {
                $from = Carbon::createFromFormat('Y-m-d', $paramFetcher->get('from'));
                $to = Carbon::createFromFormat('Y-m-d', $paramFetcher->get('to'));
            } catch (Exception $e) {
                throw new BadRequestHttpException($e->getMessage());
            }

            if ($from == null || $to == null) {
                throw new BadRequestHttpException('invalid dates');
            }
        }

        $message = $paramFetcher->get('message');
        $this->settingsGateway->updateSleepMode($this->session->id(), $mode, $from, $to, $message);

        return $this->handleView($this->view([], 204));
    }

    /**
     * Requests that the user's login email address will be changed. This does not permanently change the address yet,
     * but sends out the confirmation email.
     */
    #[OA2\Tag(name: 'user')]
    #[Rest\Patch('user/current/email')]
    #[ParamConverter('request', converter: 'fos_rest.request_body')]
    #[OA2\RequestBody(content: new Model(type: EmailChangeRequest::class))]
    #[OA2\Response(response: '200', description: 'Success')]
    #[OA2\Response(response: '400', description: 'Empty or invalid parameters')]
    #[OA2\Response(response: '401', description: 'Not logged in')]
    #[OA2\Response(response: '403', description: 'Wrong password')]
    public function requestEmailChange(EmailChangeRequest $request, ValidatorInterface $validator): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('');
        }

        $errors = $validator->validate($request);
        if ($errors->count() > 0) {
            $firstError = $errors->get(0);
            throw new BadRequestHttpException(json_encode(['field' => $firstError->getPropertyPath(), 'message' => $firstError->getMessage()]));
        }

        $this->settingsTransactions->requestEmailChange($request);

        return $this->handleView($this->view([], 200));
    }
}
