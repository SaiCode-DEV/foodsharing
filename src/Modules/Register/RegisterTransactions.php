<?php

namespace Foodsharing\Modules\Register;

use Carbon\Carbon;
use Exception;
use Foodsharing\Lib\ListmonkClient;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Legal\LegalGateway;
use Foodsharing\Modules\Login\LoginGateway;
use Foodsharing\Modules\Register\DTO\RegisterData;
use Foodsharing\Modules\Register\DTO\RegisterResult;
use Foodsharing\Utility\EmailHelper;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegisterTransactions
{
    private const REGISTRATION_TOKEN_LENGTH = 30;

    public function __construct(
        private readonly LoginGateway $loginGateway,
        private readonly EmailHelper $emailHelper,
        private readonly TranslatorInterface $translator,
        private readonly LegalGateway $legalGateway,
        private readonly RegisterGateway $registerGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly ListmonkClient $listmonkClient,
    ) {
    }

    /**
     * Starts a registration attempt. Checks if the email address is already registered or is in the process of being
     * registered. Creates the necessary token and database entry and sends out emails. This function returns
     * successfully if the registration was started or if the email is already registered.
     *
     * @throws AccessDeniedHttpException if a registration process with that email is already ongoing
     */
    public function addRegistrationAttempt(string $email): void
    {
        /* Block the registration if an attempt with the address exists. This is effectively a rate limit because the
          attempts are being deleted regularly. */
        if ($this->registerGateway->doesRegistrationAttemptExist($email)) {
            throw new AccessDeniedHttpException('Registration attempt in progress');
        }

        $user = $this->foodsaverGateway->getFoodsaverByEmail($email);
        if (is_null($user)) {
            $token = bin2hex(random_bytes(self::REGISTRATION_TOKEN_LENGTH));
            $this->registerGateway->addRegistrationAttempt($email, $token);

            $this->emailHelper->tplMail('user/registration_started', $email, [
                'link' => BASE_URL . '/register-continue?token=' . $token,
                'date' => Carbon::now()->addHours(REGISTRATION_ATTEMPT_VALIDITY_HOURS)->isoFormat('DD.MM.YYYY HH:mm'),
                'link2' => BASE_URL . '/register'
            ], highPriority: true);
        } else {
            /* The user is already registered, we email that address to notify them about the new attempt and save the
               attempt for the rate limiting. */
            $this->registerGateway->addRegistrationAttempt($email, null);

            $this->emailHelper->tplMail('user/registration_attempt', $email, [
                'anrede' => $this->translator->trans('salutation.' . $user['geschlecht']),
                'name' => $user['name'],
                'link' => BASE_URL . '/password-reset',
            ], highPriority: true);
        }
    }

    /**
     * Registers a user, sends out the registration email, and optionally subscribes them to the newsletter.
     *
     * @throws Exception if the database insert fails
     */
    public function registerUser(RegisterData $data): RegisterResult
    {
        // Validate the token
        $email = $this->registerGateway->getEmailForToken(trim($data->token));
        if (is_null($email)) {
            throw new BadRequestHttpException('Invalid token');
        }

        // Validate password
        $pwCheck = $this->loginGateway->checkPassword($data->password);
        if ($pwCheck !== null) {
            throw new BadRequestHttpException($pwCheck);
        }

        $result = new RegisterResult();

        $id = $this->loginGateway->insertNewUser($data, $email);
        if (!$id) {
            throw new Exception('could not register user');
        }

        // send activation email
        $this->emailHelper->tplMail('user/join_without_activation', $email, [
            'name' => $data->firstName,
            'anrede' => $this->translator->trans('salutation.' . $data->gender),
        ], false, true);

        $this->legalGateway->agreeToPrivacyPolicy($id);

        /*
         * Add the email address to Listmonk if the user wants to subscribe to the newsletter. The subscription is not
         * yet active. It will be activated after the email address was verified.
         */
        if ($data->subscribeNewsletter) {
            try {
                $this->listmonkClient->addSubscriber($email, $data->firstName);
            } catch (Exception) {
                $result->hasNewsletterSubscriptionFailed = true;
            }
        }

        // Delete the token so that the registration can not be reattempted with the now useless token
        $this->registerGateway->deleteRegistrationAttempt($data->token);

        return $result;
    }
}
