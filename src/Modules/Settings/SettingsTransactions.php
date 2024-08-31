<?php

namespace Foodsharing\Modules\Settings;

use Exception;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\UserOptionType;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Core\DTO\Address;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Foodsaver\DTO\EditableProfileDTO;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Foodsaver\FoodsaverTransactions;
use Foodsharing\Modules\Login\LoginGateway;
use Foodsharing\Modules\Mails\MailsGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Unit\UnitGateway;
use Foodsharing\Permissions\SettingsPermissions;
use Foodsharing\RestApi\Models\Settings\EmailChangeRequest;
use Foodsharing\RestApi\Models\Settings\PasswordChangeRequest;
use Foodsharing\Utility\EmailHelper;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class SettingsTransactions
{
    final public const DEFAULT_LOCALE = 'de';
    public const MIN_PASSWORD_LENGTH = 8;

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
        private readonly UnitGateway $unitGateway,
        private readonly RegionGateway $regionGateway,
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
            'link' => BASE_URL . '/user/current/settings/email/verifyAbort?token=' . $token
        ], false, true);

        // send a confirmation email to the new address
        $this->mailsGateway->removeBounceForMail($request->email);
        $this->emailHelper->tplMail('user/change_email', $request->email, [
            'anrede' => $this->translator->trans('salutation.' . $user['geschlecht']),
            'name' => $user['name'],
            'link' => BASE_URL . '/user/current/settings/email/verify?token=' . $token
        ], false, true);
    }

    public function abortEMailChange(string $token)
    {
        $userId = $this->session->id();
        $newEmail = $this->settingsGateway->getNewMail($userId, $token);
        $currentEmail = $this->foodsaverGateway->getEmailAddress($userId);

        $this->settingsGateway->abortChangemail($userId);
        $this->settingsGateway->logChangedSetting(
            $userId,
            ['emailAbort' => $currentEmail],
            ['emailAbort' => $newEmail],
            ['emailAbort']
        );
    }

    /**
     * Changes the E-Mail address when token is valid.
     *
     * @throws DatabaseNoValueFoundException
     * @throws \ValueError E-Mail address is in use
     */
    public function verifyAndCompleteEMailChange(string $token)
    {
        $userId = $this->session->id();

        $newEmail = $this->settingsGateway->getNewMail($userId, $token);
        $inUse = $this->foodsaverGateway->emailExists($newEmail);
        if ($inUse) {
            $this->settingsGateway->abortChangemail($userId);
            throw new \ValueError('Email address is already in use.');
        }
        $currentEmail = $this->foodsaverGateway->getEmailAddress($userId);

        $this->settingsGateway->changeMail($userId, $newEmail);
        $this->settingsGateway->logChangedSetting(
            $userId,
            ['email' => $currentEmail],
            ['email' => $newEmail],
            ['email']
        );
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

        // If the home region was changed, it needs to be an existing region with a type that is allowed for home regions
        if (!is_null($editableProfileDTO->regionId) && $editableProfileDTO->regionId != $currentUserProfile['bezirk_id']) {
            try {
                $type = $this->regionGateway->getType($editableProfileDTO->regionId);
                if (!UnitType::isAccessibleRegion($type)) {
                    throw new BadRequestHttpException('new home region has an invalid type');
                }
            } catch (DatabaseNoValueFoundException) {
                throw new BadRequestHttpException('new home region does not exist');
            }
        }

        $this->downgradeProfile($userId, $currentUserProfile['rolle'], $editableProfileDTO);

        $oldData = $this->foodsaverGateway->getFoodsaver($userId);
        $isUpdated = (bool)$this->foodsaverGateway->updateFoodsaver($userId, $editableProfileDTO);
        if ($isUpdated) {
            $this->logProfileSettings($userId, $oldData, $editableProfileDTO);
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
     * @param array $oldData the profile data before it was changed
     * @param EditableProfileDTO $editableProfileDTO the new profile data
     */
    private function logProfileSettings(int $userId, array $oldData, EditableProfileDTO $editableProfileDTO): void
    {
        // Map the DTO fields to the database column names
        $trimIfNotNull = fn ($value) => is_null($value) ? null : strip_tags(trim($value));
        $newDataAsArray = array_filter([
            'name' => $trimIfNotNull($editableProfileDTO->firstName),
            'nachname' => $trimIfNotNull($editableProfileDTO->lastName),
            'stadt' => $trimIfNotNull($editableProfileDTO->location?->city),
            'plz' => $trimIfNotNull($editableProfileDTO->location?->postalCode),
            'anschrift' => $trimIfNotNull($editableProfileDTO->location?->street),
            'telefon' => $trimIfNotNull($editableProfileDTO->phone),
            'handy' => $trimIfNotNull($editableProfileDTO->mobile),
            'geschlecht' => $editableProfileDTO->gender,
            'geb_datum' => $trimIfNotNull($editableProfileDTO->birthday),
            'rolle' => $editableProfileDTO->role,
            'bezirk_id' => $editableProfileDTO->regionId,
            'no_automatic_delete' => $editableProfileDTO->noAutoDelete,
        ], fn ($var) => $var !== null);

        $currentUser = $this->session->id();
        $this->settingsGateway->logChangedSetting(
            $userId,
            $oldData,
            $newDataAsArray,
            array_keys($newDataAsArray),
            $currentUser
        );
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

    /**
     * Updates the user's password if the request is valid.
     *
     * @param PasswordChangeRequest $request the request containing the old and new password
     *
     * @throws BadRequestHttpException if the new password is too short
     * @throws AccessDeniedHttpException if the old password is wrong
     */
    public function requestPasswordChange(PasswordChangeRequest $request): void
    {
        // check that the old password is correct
        $currentEmail = $this->foodsaverGateway->getEmailAddress($this->session->id());
        if (!$this->loginGateway->checkClient($currentEmail, $request->oldPassword)) {
            throw new AccessDeniedHttpException();
        }

        // check that the new one meets the criteria
        $request->newPassword = trim($request->newPassword);
        if (strlen($request->newPassword) < self::MIN_PASSWORD_LENGTH) {
            throw new BadRequestHttpException('password is too short');
        }

        $this->loginGateway->setPassword($this->session->id(), $request->newPassword);
    }
}
