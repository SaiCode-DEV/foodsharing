<?php

namespace Foodsharing\Modules\Settings;

use Carbon\Carbon;
use Exception;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\BusinessCard\BusinessCardGateway;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\ChangeHistoryKey;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\SleepStatus;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\UserOptionType;
use Foodsharing\Modules\Core\DBConstants\Region\RegionOptionType;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Core\DTO\Address;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Foodsaver\DTO\EditableProfileDTO;
use Foodsharing\Modules\Foodsaver\DTO\ReadableProfileSettings;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Foodsaver\FoodsaverTransactions;
use Foodsharing\Modules\Login\LoginGateway;
use Foodsharing\Modules\Mails\MailsGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Settings\DTO\TOTPProposal;
use Foodsharing\Modules\Unit\UnitGateway;
use Foodsharing\Permissions\SettingsPermissions;
use Foodsharing\RestApi\Models\Settings\EmailChangeRequest;
use Foodsharing\RestApi\Models\Settings\PasswordChangeRequest;
use Foodsharing\RestApi\Models\Settings\SleepStatusRequest;
use Foodsharing\Utility\EmailHelper;
use RobThree\Auth\Providers\Qr\BaconQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SettingsTransactions
{
    final public const string DEFAULT_LOCALE = 'de';
    public const MIN_PASSWORD_LENGTH = 8;
    private const string SUPPORT_URL = 'https://support.foodsharing.network/kb';

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
        private readonly BellGateway $bellGateway,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly BusinessCardGateway $businessCardGateway,
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
     * email. If Orga wants to change someone's email address immediately, changeLoginEmail should be used.
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
            throw new AccessDeniedHttpException('Password is not correct');
        }

        // check that the new address is valid and not in use
        if (!$this->isValidNewEmailAddress($request->email)) {
            throw new BadRequestHttpException('mail address is invalid or already used');
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
            'link' => BASE_URL . '/user/current/settings/email/verifyAbort?token=' . $token,
            'support_link' => self::SUPPORT_URL
        ], false, true);

        // send a confirmation email to the new address
        $this->mailsGateway->removeBounceForMail($request->email);
        $this->emailHelper->tplMail('user/change_email', $request->email, [
            'anrede' => $this->translator->trans('salutation.' . $user['geschlecht']),
            'name' => $user['name'],
            'link' => BASE_URL . '/user/current/settings/email/verify?token=' . $token
        ], false, true);
    }

    /**
     * Immediately changes someone's login email address without sending a confirmation email. If users want to change
     * their own email address, requestEmailChange should be used.
     *
     * @param EmailChangeRequest $request The request containing the new email address. The password is not required.
     * @param int $userId the user whose email address should be changed
     * @throws AccessDeniedHttpException missing permission
     * @throws BadRequestHttpException if the new email address is not valid
     */
    public function changeLoginEmail(EmailChangeRequest $request, int $userId): void
    {
        // check the permissions and that the email is valid and not in use
        if (!$this->settingsPermissions->mayChangeLoginEmail($userId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }
        if (!$this->isValidNewEmailAddress($request->email)) {
            throw new BadRequestHttpException('new email is not valid');
        }

        $this->settingsGateway->changeMail($userId, $request->email);

        // log this change request
        $currentEmail = $this->foodsaverGateway->getEmailAddress($userId);
        $this->settingsGateway->logChangedSetting(
            $userId,
            [ChangeHistoryKey::CHANGE_EMAIL_REQUEST => $currentEmail],
            [ChangeHistoryKey::CHANGE_EMAIL_REQUEST => $request->email],
            [ChangeHistoryKey::CHANGE_EMAIL_REQUEST],
            $this->session->id()
        );

        // send a notification about the change to the old address
        $this->mailsGateway->removeBounceForMail($currentEmail);
        $user = $this->foodsaverGateway->getFoodsaverBasics($userId);
        $this->emailHelper->tplMail('user/change_email_notification_without_confirmation', $currentEmail, [
            'anrede' => $this->translator->trans('salutation.' . $user['geschlecht']),
            'name' => $user['name'],
            'address' => $request->email,
            'support_link' => self::SUPPORT_URL
        ], false, true);
    }

    /**
     * Returns whether an email address can be used as a valid login address.
     */
    private function isValidNewEmailAddress(string $address): bool
    {
        return $this->emailHelper->validEmail($address)
            && !$this->emailHelper->isFoodsharingEmailAddress($address)
            && !$this->foodsaverGateway->emailExists($address)
            && !$this->foodsaverGateway->emailDomainIsBlacklisted($address);
    }

    public function abortEMailChange(string $token)
    {
        $mailChange = $this->settingsGateway->getMailChangeByToken($token);
        $userId = $mailChange['foodsaver_id'];
        $newEmail = $mailChange['newmail'];
        $currentEmail = $this->foodsaverGateway->getEmailAddress($userId);

        $this->settingsGateway->deleteMailChangeByToken($token);
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
        $mailChange = $this->settingsGateway->getMailChangeByToken($token);
        $userId = $mailChange['foodsaver_id'];
        $newEmail = $mailChange['newmail'];

        $inUse = $this->foodsaverGateway->emailExists($newEmail);
        if ($inUse) {
            $this->settingsGateway->deleteMailChangeByToken($token);
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

    public function readProfile(int $userId): ReadableProfileSettings
    {
        if (!$this->settingsPermissions->mayEditProfileSettings($userId)) {
            throw new AccessDeniedHttpException('no permission to view profile settings');
        }
        $isMe = $userId === $this->session->id();

        $data = $this->settingsGateway->getFoodsaverSettings($userId);
        if (empty($data)) {
            throw new NotFoundHttpException('User does not exist.');
        }

        $data->sleepingData = null;
        $data->businessCardData = null;
        $data->isOnTeamPage = false;
        $data->mayChangeVerifiedData = false;
        $data->mayChangeEmailImmediately = false;

        $targetRole = $this->getNextTargetRole();

        if ($this->session->role()->value < $targetRole->value) {
            $data->targetRole = $targetRole->value;
        }

        $data->isOnTeamPage = $this->unitGateway->isUserOnTeamPage($userId);
        $data->mayChangeVerifiedData = $this->settingsPermissions->mayChangeVerifiedData($userId);
        $data->mayChangeEmailImmediately = $this->settingsPermissions->mayChangeLoginEmail($userId);

        if ($isMe) {
            $data->sleepingData = $this->settingsGateway->getSleepData($userId);
            $data->businessCardData = $this->businessCardGateway->getMyData($userId, $this->session->mayRole(Role::STORE_MANAGER));
        }

        return $data;
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

        $addressChanged = $this->isAddressChanged($currentUserProfile, $editableProfileDTO);

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

        $oldData = $this->foodsaverGateway->getFoodsaver($userId);
        $isUpdated = (bool)$this->foodsaverGateway->updateFoodsaver($userId, $editableProfileDTO);
        if ($isUpdated) {
            $this->logProfileSettings($userId, $oldData, $editableProfileDTO);

            if ($addressChanged) {
                $this->notifyAmbassadorsAboutAddressChange($userId, $currentUserProfile, $editableProfileDTO);
            }
            // If role changed, invalidate sessions to pick up the new role
            if ($editableProfileDTO->role !== null) {
                $this->session->invalidateAllSessionsForUser($userId);
            }
        }
        $this->downgradeProfile($userId, $currentUserProfile['rolle'], $editableProfileDTO);

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
        $mayChangeVerifiedData = $this->settingsPermissions->mayChangeVerifiedData($userId);
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

        if (!$mayChangeVerifiedData) {
            $editableProfileDTO->firstName = $currentUserProfile['name'];
            $editableProfileDTO->lastName = $currentUserProfile['nachname'];
            $editableProfileDTO->birthday = $currentUserProfile['geb_datum'];
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
        $trimIfNotNull = fn ($value) => is_null($value) ? null : strip_tags(trim((string)$value));
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
        $userId = $this->session->id();

        // check that the old password is correct
        $currentEmail = $this->foodsaverGateway->getEmailAddress($userId);
        if (!$this->loginGateway->checkClient($currentEmail, $request->oldPassword, $request->totpCode)) {
            throw new AccessDeniedHttpException();
        }

        // check that the new one meets the criteria
        $pwCheck = $this->loginGateway->checkPassword($request->newPassword);
        if ($pwCheck !== null) {
            throw new BadRequestHttpException($pwCheck);
        }

        if ($request->newPassword === $request->oldPassword) {
            throw new BadRequestHttpException('New password must be different from the old password');
        }

        $this->loginGateway->setPassword($userId, $request->newPassword);

        // Invalidate all sessions of this user and log out the current session.
        // This ensures that all active sessions are terminated after a password
        // change and gives users an (indirect) method to force-logout from
        // lost/stolen devices.
        $this->session->invalidateAllSessionsForUser($userId);

        // Revoke OAuth refresh tokens to prevent issuing new access tokens via refresh
        $this->foodsaverGateway->revokeOAuthRefreshTokens($userId);
    }

    /**
     * Generate 2FA secret, QR code and backup codes.
     */
    public function generateTwoFA(): TOTPProposal
    {
        // First check if there is already an ongoing TOTP activation
        $proposal = $this->session->get('totp_proposal');
        if (!empty($proposal)) {
            try {
                return new TOTPProposal($proposal['reused'] + 1, $proposal['secret'], $proposal['qrCode'], $proposal['backupCodes']);
            } catch (Exception|\TypeError) {
                // proposal is not a valid array or continues null values, continue
            }
        }

        // Generate a new TOTP secret
        $qrCodeProvider = new BaconQrCodeProvider();
        $tfa = new TwoFactorAuth($qrCodeProvider, issuer: 'Foodsharing');
        // Generate a new secret
        // RFC 4226, section 4 suggests a 160-bit key for HMAC-SHA1
        $secret = $tfa->createSecret(bits: 160);
        $currentEmail = $this->foodsaverGateway->getEmailAddress($this->session->id());
        $qrCode = $tfa->getQRCodeImageAsDataUri($currentEmail, $secret, size: 400);

        // Generate backup codes. They use 3*2*8 = 48 bits of secure randomness
        // and are represented in uppercase hexadecimals with dashes
        $backupCodes = [];
        for ($i = 0; $i < 12; ++$i) {
            $code = '';
            for ($j = 0; $j < 3; ++$j) {
                $code .= strtoupper(bin2hex(random_bytes(2))) . '-';
            }

            // Strip trailing dash and add to array
            $backupCodes[] = rtrim($code, '-');
        }

        $totpProposal = [
            'reused' => 0,
            'secret' => $secret,
            'qrCode' => $qrCode,
            'backupCodes' => $backupCodes,
        ];

        // Store totp_proposal in SESSION
        $this->session->set('totp_proposal', $totpProposal);

        // Return data array
        return new TOTPProposal(0, $secret, $qrCode, $backupCodes);
    }

    /**
     * Configure Two-Factor Authentication (2FA) for the user.
     *
     * This function configures 2FA by verifying the submitted password and 2FA
     * code, and then setting the TOTP secret and backup codes for the user.
     * When we are disabling 2FA, we set the secret to null.
     *
     * @param string $code The 2FA code
     * @param string $password The user's password
     *
     * @throws BadRequestHttpException If TOTP activation is not running
     * @throws AccessDeniedHttpException If the password or 2FA code is incorrect
     */
    public function enableTwoFA(string $code, string $password): void
    {
        // Retrieve totp_proposal from SESSION
        $totpProposal = $this->session->get('totp_proposal');
        if (!$totpProposal || !is_array($totpProposal)) {
            throw new BadRequestHttpException('No TOTP activation in progress');
        }
        // Ensure TOTP secret is not set at this point
        $this->loginGateway->setTOTPSecret($this->session->id(), null);

        // Check that the submitted password is correct
        $currentEmail = $this->foodsaverGateway->getEmailAddress($this->session->id());
        if (!$this->loginGateway->checkClient($currentEmail, $password)) {
            throw new AccessDeniedHttpException('Password is incorrect');
        }

        // Check that the submitted code is correct
        $qrCodeProvider = new BaconQrCodeProvider();
        $tfa = new TwoFactorAuth($qrCodeProvider);
        $totpProposal = $this->session->get('totp_proposal');
        if (!$tfa->verifyCode($totpProposal['secret'], $code)) {
            throw new AccessDeniedHttpException('2FA code is incorrect');
        }

        // Enable 2FA for this user by setting the secret and backup codes
        $this->loginGateway->setTOTPSecret($this->session->id(), $totpProposal['secret']);
        $this->loginGateway->setBackupCodes($this->session->id(), $totpProposal['backupCodes']);

        // Clear the TOTP proposal from the session
        $this->session->set('totp_proposal', null);
    }

    /**
     * Disable Two-Factor Authentication (2FA) for the user.
     *
     * This function disables 2FA by verifying the submitted password and 2FA
     * code, and then setting the TOTP secret and backup codes to null.
     *
     * @param string $code The 2FA code
     * @param string $password The user's password
     *
     * @throws BadRequestHttpException If TOTP activation is not running
     * @throws AccessDeniedHttpException If the password or 2FA code is incorrect
     */
    public function disableTwoFA(string $code, string $password, int $targetUserId): void
    {
        if ($targetUserId !== $this->session->id() && !$this->settingsPermissions->mayDisable2FA()) {
            throw new AccessDeniedHttpException('No permission to disable 2FA for other users');
        }

        if ($targetUserId === $this->session->id()) {
            // Check that the submitted password and 2FA are correct
            $currentEmail = $this->foodsaverGateway->getEmailAddress($this->session->id());
            if (!$this->loginGateway->checkClient($currentEmail, $password, $code)) {
                throw new AccessDeniedHttpException('Password or Code incorrect');
            }
        }

        // Disable 2FA for this user by setting the secret and backup codes
        $this->loginGateway->setTOTPSecret($targetUserId, null);
        $this->loginGateway->setBackupCodes($targetUserId, []);
    }

    public function updateSleepMode(SleepStatusRequest $sleepRequest)
    {
        if ($sleepRequest->mode == SleepStatus::NONE) {
            return $this->settingsGateway->updateSleepMode($this->session->id(), $sleepRequest->mode);
        }

        $currentSleepData = $this->settingsGateway->getSleepData($this->session->id());
        $isCurrentlySleeping = $currentSleepData['sleep_status'] !== SleepStatus::NONE;

        if ($sleepRequest->mode == SleepStatus::FULL) {
            // If already asleep, extend the duration to infinity, otherwise start sleeping now
            $sleepStartDate = Carbon::today();
            if ($isCurrentlySleeping) {
                $sleepStartDate = Carbon::parse($currentSleepData['sleep_from']);
            }

            return $this->settingsGateway->updateSleepMode(
                $this->session->id(),
                $sleepRequest->mode,
                $sleepStartDate,
                null,
                $sleepRequest->message,
            );
        }

        if ($sleepRequest->from == null || $sleepRequest->to == null) {
            throw new BadRequestHttpException('from and to are required for temporary sleep mode');
        }
        // Convert to Carbon instances and truncate time
        $fromDate = Carbon::createFromInterface($sleepRequest->from)->setTime(0, 0, 0, 0);
        $untilDate = Carbon::createFromInterface($sleepRequest->to)->setTime(0, 0, 0, 0);

        // Check that the start date is either not in the past or is the current sleep start date
        $currentSleepFrom = Carbon::make($currentSleepData['sleep_from']);
        if ($fromDate->endOfDay()->isPast() && !($isCurrentlySleeping && $fromDate->isSameDay($currentSleepFrom))) {
            throw new BadRequestHttpException('start date must stay the same or not be in the past');
        }

        // End date must be in the future and not before the start date
        if ($untilDate->endOfDay()->isPast() || $untilDate->lessThan($fromDate)) {
            throw new BadRequestHttpException('end date must not be in the past');
        }

        return $this->settingsGateway->updateSleepMode(
            $this->session->id(),
            $sleepRequest->mode,
            $fromDate,
            $untilDate,
            $sleepRequest->message,
        );
    }

    /**
     * Checks if the user's address (location or coordinates) has changed.
     *
     * @param array $currentUserProfile the current user profile data
     * @param EditableProfileDTO $editableProfileDTO the new profile data
     * @return bool true if the address has changed
     */
    private function isAddressChanged(array $currentUserProfile, EditableProfileDTO $editableProfileDTO): bool
    {
        $coordsChanged = false;
        if (!is_null($editableProfileDTO->coordinate)) {
            $oldLat = (float)$currentUserProfile['lat'];
            $oldLon = (float)$currentUserProfile['lon'];
            $newLat = (float)$editableProfileDTO->coordinate->lat;
            $newLon = (float)$editableProfileDTO->coordinate->lon;

            $threshold = 0.001; // Approximately 100 meters
            $coordsChanged = (abs($oldLat - $newLat) > $threshold || abs($oldLon - $newLon) > $threshold);
        }

        $locationChanged = false;
        if (!is_null($editableProfileDTO->location)) {
            $oldStreet = $currentUserProfile['anschrift'] ?? '';
            $oldCity = $currentUserProfile['stadt'] ?? '';
            $oldZip = $currentUserProfile['plz'] ?? '';

            $newStreet = $editableProfileDTO->location->street ?? '';
            $newCity = $editableProfileDTO->location->city ?? '';
            $newZip = $editableProfileDTO->location->postalCode ?? '';

            $locationChanged = ($oldStreet !== $newStreet || $oldCity !== $newCity || $oldZip !== $newZip);
        }

        return $coordsChanged || $locationChanged;
    }

    /**
     * Notifies ambassadors about a user's address change.
     *
     * @param int $userId ID of the user whose address changed
     * @param array $currentUserProfile the old user profile data
     * @param EditableProfileDTO $editableProfileDTO the new user profile data
     */
    private function notifyAmbassadorsAboutAddressChange(int $userId, array $currentUserProfile, EditableProfileDTO $editableProfileDTO): void
    {
        $regionId = $editableProfileDTO->regionId ?? $currentUserProfile['bezirk_id'];
        if (empty($regionId)) {
            return;
        }

        // Check if address change notifications are enabled for this region
        if (!boolval($this->regionGateway->getRegionOption($regionId, RegionOptionType::NOTIFY_ADDRESS_CHANGE))) {
            return;
        }

        $ambassadorIds = $this->foodsaverGateway->getRegionAmbassadorIds($regionId);
        if (empty($ambassadorIds)) {
            return;
        }

        $userData = $this->foodsaverGateway->getFoodsaver($userId);

        $url = $this->urlGenerator->generate(
            'user_settings',
            ['userId' => $userId],
            UrlGeneratorInterface::ABSOLUTE_PATH
        );

        $bellData = Bell::create(
            'foodsaver_adress_changed_title',
            'foodsaver_adress_changed',
            'fas fa-map-marker-alt',
            ['href' => $url],
            [
                'name' => $userData['name'] . ' ' . $userData['nachname']
            ],
            BellType::createIdentifier(BellType::ADDRESS_CHANGE, $userId)
        );

        $this->bellGateway->addBellForUsers($ambassadorIds, $bellData);
    }

    private function getNextTargetRole(): Role
    {
        $currentRole = $this->session->role();
        $targetRole = Role::AMBASSADOR;
        if ($currentRole->value < $targetRole->value - 1) {
            $targetRole = Role::from($currentRole->value + 1);
        }
        if (!$this->session->isVerified()) {
            $targetRole = Role::FOODSAVER;
        }

        return $targetRole;
    }
}
