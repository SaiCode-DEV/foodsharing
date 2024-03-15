<?php

namespace Foodsharing\Modules\Settings;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\UserOptionType;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Login\LoginGateway;
use Foodsharing\Modules\Mails\MailsGateway;
use Foodsharing\RestApi\Models\Settings\EmailChangeRequest;
use Foodsharing\Utility\EmailHelper;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class SettingsTransactions
{
    final public const DEFAULT_LOCALE = 'de';

    public function __construct(
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly LoginGateway $loginGateway,
        private readonly SettingsGateway $settingsGateway,
        private readonly MailsGateway $mailsGateway,
        private readonly EmailHelper $emailHelper,
        private readonly TranslatorInterface $translator,
        private readonly Session $session,
    ) {
    }

    /**
     * gets a user specific option and will be available after next login.
     *
     * @param UserOptionType $key Identifier of the Setting in fs_foodsaver_has_options
     * @return mixed Any value of user
     */
    public function getOption(UserOptionType $key): mixed
    {
        $keyValue = $key->value;
        if (!$this->session->has('useroption_' . $keyValue)) {
            if ($this->session->has($key->toString()) && !$this->session->has($key->toString() . '_replaced')) {
                $this->setOption($key, $this->session->get($key->toString())); // Convert to new format
                $this->session->set($key->toString() . '_replaced', true);
            } else {
                $userId = $this->session->id();
                if ($userId) {
                    $this->session->set('useroption_' . $keyValue, $this->settingsGateway->getUserOption($userId, $key));
                }
            }
        }

        return $this->session->get('useroption_' . $keyValue);
    }

    public function setOption(UserOptionType $key, mixed $val): void
    {
        $this->settingsGateway->setUserOption($this->session->id(), $key, $val);
        $this->session->set('useroption_' . $key->value, $val);
    }

    public function getLocale(): string
    {
        $lang = $this->getOption(UserOptionType::LOCALE);
        if (empty($lang) || $lang == false) {
            $lang = SettingsTransactions::DEFAULT_LOCALE;
        }

        return $lang;
    }

    /**
     * Stores the request for changing the user's email address in the database and sends confirmation emails to the
     * old and the new address. After this, the change still needs to be confirmed by the link in the confirmation
     * email.
     *
     * @param EmailChangeRequest $request the request containing the new email address and the user's password
     *
     * @throws AccessDeniedHttpException if the password is wrong
     * @throws BadRequestHttpException if the new email address is not valid
     */
    public function requestEmailChange(EmailChangeRequest $request): void
    {
        // check that the password is correct
        $currentEmail = $this->foodsaverGateway->getEmailAddress($this->session->id());
        if (!$this->loginGateway->checkClient($currentEmail, $request->password)) {
            throw new AccessDeniedHttpException();
        }

        // check that the new address is valid and not in use
        if (!$this->emailHelper->validEmail($request->email)
            || $this->emailHelper->isFoodsharingEmailAddress($request->email)
            || $this->foodsaverGateway->emailExists($request->email)) {
            throw new BadRequestHttpException();
        }

        // store a random token in the database
        $token = bin2hex(random_bytes(16));
        $this->settingsGateway->addNewMail($this->session->id(), $request->email, $token);

        // send a notification about the change to the old address
        $user = $this->foodsaverGateway->getFoodsaverBasics($this->session->id());
        $this->mailsGateway->removeBounceForMail($currentEmail);
        $this->emailHelper->tplMail('user/change_email_notification', $currentEmail, [
            'anrede' => $this->translator->trans('salutation.' . $user['geschlecht']),
            'name' => $user['name'],
            'address' => $request->email,
            'link' => BASE_URL . '/content?sub=contact'
        ], false, true);

        // send a confirmation email to the new address
        $this->mailsGateway->removeBounceForMail($request->email);
        $this->emailHelper->tplMail('user/change_email', $request->email, [
            'anrede' => $this->translator->trans('salutation.' . $user['geschlecht']),
            'name' => $user['name'],
            'link' => BASE_URL . '/?page=settings&sub=general&newmail=' . $token
        ], false, true);
    }
}
