<?php

namespace Foodsharing\Modules\Settings;

use Exception;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\UserOptionType;
use Foodsharing\Modules\Core\DTO\Address;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Foodsaver\DTO\EditableProfileDTO;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Foodsaver\FoodsaverTransactions;
use Foodsharing\Modules\Login\LoginGateway;
use Foodsharing\Modules\Mails\MailsGateway;
use Foodsharing\Modules\Unit\UnitGateway;
use Foodsharing\Permissions\SettingsPermissions;
use Foodsharing\RestApi\Models\Settings\EmailChangeRequest;
use Foodsharing\Utility\EmailHelper;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
        private readonly SettingsPermissions $settingsPermissions,
        private readonly FoodsaverTransactions $foodsaverTransactions,
        private readonly UnitGateway $unitGateway
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
            'link' => BASE_URL . '/user/current/settings?sub=general&newmail=' . $token
        ], false, true);
    }

    /**
     * Updates a user's profile based on the provided data.
     *
     * @param int $userId the ID of the user whose profile should be updated
     * @param EditableProfileDTO $editableProfileDTO the new profile data
     * @return bool Returns true if the profile was successfully updated,
     * @throws NotFoundHttpException|Exception if the user profile is not found
     */
    public function patchProfile(int $userId, EditableProfileDTO $editableProfileDTO): bool
    {
        $currentUserProfile = $this->foodsaverGateway->getFoodsaverDetails($editableProfileDTO->id);
        if (!$currentUserProfile) {
            throw new NotFoundHttpException('user does not exist');
        }

        $editableProfileDTO = $this->filterProfile($userId, $currentUserProfile, $editableProfileDTO);

        $this->downgradeProfile($userId, $currentUserProfile['rolle'], $editableProfileDTO);

        $isUpdated = (bool)$this->foodsaverGateway->updateFoodsaver($userId, $editableProfileDTO);
        if ($isUpdated) {
            $this->logProfileSettings($userId, $editableProfileDTO);
        }

        return $isUpdated;
    }

    /**
     * Filters the user profile data based on the user's permissions.
     *
     * @param int $userId the ID of the user whose profile is being filtered
     * @param array $currentUserProfile the current user profile data
     * @param EditableProfileDTO $editableProfileDTO the new profile data
     * @return EditableProfileDTO the filtered profile data
     * @throws Exception
     */
    private function filterProfile(int $userId, array $currentUserProfile, EditableProfileDTO $editableProfileDTO): EditableProfileDTO
    {
        $mayChangeName = $this->settingsPermissions->mayChangeName($userId);
        $mayEditProfileSettings = $this->settingsPermissions->mayEditProfileSettings($userId);
        $mayEditTeamSettings = $this->settingsPermissions->mayChangeTeamPageData($userId);
        $isOnTeamPage = $this->unitGateway->isUserOnTeamPage($userId);
        $mayChangeRole = $this->settingsPermissions->mayChangeRole();
        $mayChangeHomeRegion = $this->settingsPermissions->mayChangeHomeRegion($userId);
        $isMe = $this->session->id() === $userId;
        $isOrga = $this->session->mayRole(Role::ORGA);

        if (!$mayChangeRole) {
            $editableProfileDTO->role = $currentUserProfile['rolle'];
        }

        if (!$mayChangeName) {
            $editableProfileDTO->firstName = $currentUserProfile['name'];
            $editableProfileDTO->lastName = $currentUserProfile['nachname'];
        }

        if (!$mayChangeHomeRegion) {
            $editableProfileDTO->regionId = $currentUserProfile['bezirk_id'];
        }

        if (!$mayEditProfileSettings) {
            $editableProfileDTO->gender = $currentUserProfile['geschlecht'];
            $editableProfileDTO->birthday = $currentUserProfile['geb_datum'];
            $editableProfileDTO->mobile = $currentUserProfile['mobile'];
            $editableProfileDTO->phone = $currentUserProfile['phone'];
            $editableProfileDTO->location = Address::createFromArray($currentUserProfile);
            $editableProfileDTO->coordinate = GeoLocation::createFromArray($currentUserProfile);
            $editableProfileDTO->noAutoDelete = $currentUserProfile['no_automatic_delete'];
        }

        if (!$isOnTeamPage || !$mayEditTeamSettings) {
            $editableProfileDTO->position = $currentUserProfile['position'];
            $editableProfileDTO->aboutMePublic = $currentUserProfile['about_me_public'];
        }

        if (!$isMe && !$isOrga) {
            $editableProfileDTO->aboutMeInternal = $currentUserProfile['about_me_intern'];
        }

        return $editableProfileDTO;
    }

    /**
     * Logs the changes made to a user's profile.
     *
     * @param int $userId the ID of the user whose profile changes are being logged
     * @param EditableProfileDTO $editableProfileDTO the new profile data
     */
    private function logProfileSettings(int $userId, EditableProfileDTO $editableProfileDTO): void
    {
        if (isset($editableProfileDTO->id) && $userId === $editableProfileDTO->id) {
            if ($oldData = $this->foodsaverGateway->getFoodsaver($userId)) {
                $changedFields = [
                    'name',
                    'nachname',
                    'stadt',
                    'plz',
                    'anschrift',
                    'telefon',
                    'handy',
                    'geschlecht',
                    'geb_datum',
                    'rolle',
                    'orgateam',
                    'bezirk_id',
                    'no_automatic_delete'
                ];
                $currentUser = $this->session->id();
                $newDataAsArray = get_object_vars($editableProfileDTO);
                $this->settingsGateway->logChangedSetting(
                    $userId,
                    $oldData,
                    $newDataAsArray,
                    $changedFields,
                    $currentUser
                );
            }
        }
    }

    /**
     * Downgrades a user's profile if they are assigned a lower role.
     * The downgrade is only performed if an Orga, Ambassador, or Foodsaver is demoted to a Foodsharer.
     *
     * @param int $userId the ID of the user whose profile may be downgraded
     * @param int $currentRole the current role of the user
     * @param EditableProfileDTO $editableProfileDTO the new profile data
     */
    private function downgradeProfile(int $userId, int $currentRole, EditableProfileDTO $editableProfileDTO): void
    {
        if (isset($editableProfileDTO->role) && $editableProfileDTO->role === Role::FOODSHARER->value && $editableProfileDTO->role < $currentRole) {
            $this->foodsaverTransactions->downgradeAndBlockForQuizPermanently($userId);
        }
    }
}
