<?php

namespace Foodsharing\Modules\Foodsaver;

use Carbon\Carbon;
use DateTime;
use Foodsharing\Lib\ListmonkClient;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Basket\BasketGateway;
use Foodsharing\Modules\Core\DBConstants\CategoryType;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\SleepStatus;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Core\DBConstants\Uploads\UploadUsage;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Event\EventGateway;
use Foodsharing\Modules\Event\InvitationStatus;
use Foodsharing\Modules\Foodsaver\DTO\AgendaEntry;
use Foodsharing\Modules\Foodsaver\DTO\EventAgendaEntry;
use Foodsharing\Modules\Foodsaver\DTO\ProfileDetails;
use Foodsharing\Modules\PassportGenerator\PassportGeneratorTransaction;
use Foodsharing\Modules\Profile\ProfileGateway;
use Foodsharing\Modules\Quiz\QuizSessionGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Region\RegionTransactions;
use Foodsharing\Modules\Settings\SettingsGateway;
use Foodsharing\Modules\Store\PickupGateway;
use Foodsharing\Modules\Store\StoreTransactions;
use Foodsharing\Modules\Unit\DTO\UserUnit;
use Foodsharing\Modules\Unit\UnitGateway;
use Foodsharing\Modules\Uploads\UploadsGateway;
use Foodsharing\Modules\Uploads\UploadsTransactions;
use Foodsharing\Permissions\BlogPermissions;
use Foodsharing\Permissions\CategoriesPermissions;
use Foodsharing\Permissions\ContentPermissions;
use Foodsharing\Permissions\ProfilePermissions;
use Foodsharing\Permissions\QuizPermissions;
use Foodsharing\Permissions\RegionPermissions;
use Foodsharing\Permissions\ReportPermissions;
use Foodsharing\Permissions\SearchPermissions;
use Foodsharing\Permissions\StorePermissions;
use Foodsharing\RestApi\Models\Group\UserGroupModel;
use Foodsharing\RestApi\Models\Region\UserRegionModel;
use Foodsharing\Utility\TimeHelper;

class FoodsaverTransactions
{
    public function __construct(
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly QuizSessionGateway $quizSessionGateway,
        private readonly BasketGateway $basketGateway,
        private readonly UploadsGateway $uploadsGateway,
        private readonly UploadsTransactions $uploadsTransactions,
        private readonly StoreTransactions $storeTransactions,
        private readonly SettingsGateway $settingsGateway,
        private readonly PickupGateway $pickupGateway,
        private readonly EventGateway $eventGateway,
        private readonly ProfileGateway $profileGateway,
        private readonly ProfilePermissions $profilePermissions,
        private readonly RegionGateway $regionGateway,
        private readonly RegionTransactions $regionTransactions,
        private readonly UnitGateway $unitGateway,
        private readonly PassportGeneratorTransaction $passportGeneratorTransaction,
        private readonly TimeHelper $timeHelper,
        private readonly BlogPermissions $blogPermissions,
        private readonly QuizPermissions $quizPermissions,
        private readonly ReportPermissions $reportPermissions,
        private readonly StorePermissions $storePermissions,
        private readonly ContentPermissions $contentPermissions,
        private readonly RegionPermissions $regionPermissions,
        private readonly SearchPermissions $searchPermissions,
        private readonly CategoriesPermissions $categoriesPermissions,
        private readonly Session $session,
        private readonly ListmonkClient $listmonkClient,
    ) {
    }

    public function downgradeAndBlockForQuizPermanently(int $fsId): int
    {
        $this->quizSessionGateway->blockUserForQuiz($fsId, Role::FOODSAVER->value);

        $this->storeTransactions->leaveAllStoreTeams($fsId);

        return $this->foodsaverGateway->downgradePermanently($fsId);
    }

    public function deleteFoodsaver(int $foodsaverId, ?int $deletingUserId, ?string $reason, bool $unsubscribeNewsletter = false): void
    {
        // set all active baskets of the user to deleted
        $this->basketGateway->removeActiveUserBaskets($foodsaverId);

        $this->storeTransactions->leaveAllStoreTeams($foodsaverId);

        $this->deletePhoto($foodsaverId);

        $this->foodsaverGateway->revokeOAuthRefreshTokens($foodsaverId);

        $this->settingsGateway->updateSleepMode($foodsaverId, SleepStatus::NONE);

        // Unsubscribe from the newsletter, if requested
        if ($unsubscribeNewsletter && $foodsaverId === $this->session->id()) {
            $email = $this->foodsaverGateway->getEmailAddress($foodsaverId);
            $this->listmonkClient->removeSubscriber($email);
        }

        // delete the user
        $this->foodsaverGateway->deleteFoodsaver($foodsaverId, $deletingUserId, $reason);
    }

    /**
     * Sets a previously uploaded photo as the user's new profile photo. Deletes the previous profile photo, if there
     * was any.
     *
     * @param int $foodsaverId the user's id
     * @param string $uuid the UUID of the uploaded file
     */
    public function updatePhoto(int $foodsaverId, string $uuid): void
    {
        // Delete the old photo, if there was one
        $this->deletePhoto($foodsaverId);

        // Set the new profile photo and its upload usage
        $this->foodsaverGateway->updatePhoto($this->session->id(), '/api/uploads/' . $uuid);
        $this->uploadsGateway->setUsage([$uuid], UploadUsage::PROFILE_PHOTO, $foodsaverId);
    }

    /**
     * Removes the photo from a user's profile and deletes all corresponding files.
     *
     * @param int $foodsaverId the user's id
     */
    public function deletePhoto(int $foodsaverId): void
    {
        $photo = $this->foodsaverGateway->getPhotoFileName($foodsaverId);
        if (!empty($photo)) {
            if (str_starts_with($photo, '/api/uploads/')) {
                $oldUUID = substr($photo, 13);
                $this->uploadsTransactions->deleteUploadedFile($oldUUID);
            } else {
                // Delete all resized files of the old picture
                $oldFormats = ['', '130_q_', '50_q_', 'med_q_', 'mini_q_', 'thumb_', 'thumb_crop_', 'q_'];
                foreach ($oldFormats as $format) {
                    @unlink('./images/' . $format . $photo);
                }
            }
        }
    }

    /**
     * @return AgendaEntry[]
     */
    public function getAgenda(int $foodsaverId, DateTime $day): array
    {
        $agenda = $this->pickupGateway->getSameDayPickupsForUser($foodsaverId, $day);

        $events = $this->eventGateway->getEventsByStatus($foodsaverId, [InvitationStatus::INVITED, InvitationStatus::ACCEPTED, InvitationStatus::MAYBE], 0, $day);

        foreach ($events as &$event) {
            $agenda[] = EventAgendaEntry::create(
                $event['id'],
                $event['name'],
                new Carbon($event['start']),
                new Carbon($event['end']),
                strtolower(InvitationStatus::from($event['status'])->name),
            );
        }

        usort($agenda, fn ($a, $b) => $a->date->getTimestamp() <=> $b->date->getTimestamp());

        return $agenda;
    }

    /**
     * Normalizes the detailed profile of a user.
     *
     * Assumes the user to be logged in.
     */
    public function getUserDetails(int $userId): ProfileDetails
    {
        // TODO should be refactored into smaller methods

        $details = new ProfileDetails();
        $details->id = $userId;

        $data = $this->profileGateway->getProfileDetails($userId);
        $details->foodsaver = $this->session->mayRole(Role::FOODSAVER);
        $details->isVerified = $data['verified'] === 1;
        $details->regionId = $data['bezirk_id'];
        $details->isSleeping = boolval($data['is_sleeping']);
        $details->aboutMePublic = $data['about_me_public'];

        $details->mailboxId = $data['mailbox_id'];
        $details->firstname = $data['name'];
        $details->lastname = $data['nachname'];
        $details->gender = intval($data['geschlecht']);
        $details->photo = $data['photo'];

        $details->lastPassDate = isset($data['last_pass']) ? new DateTime($data['last_pass']) : null;
        $details->lastPassUntilValid = isset($data['last_pass']) ? $this->passportGeneratorTransaction->getPassportValidityEnd($details->lastPassDate) : null;
        $details->lastPassUntilValidInDays = isset($data['last_pass']) ? $this->timeHelper->daysInFuture($details->lastPassUntilValid) : null;

        $details->regionName = is_null($details->regionId) ? null : $this->regionGateway->getRegionName($details->regionId);

        $infos = $this->foodsaverGateway->getFoodsaverBasics($userId);

        $details->hasCalendarToken = $this->settingsGateway->getApiToken($userId) !== null;
        $details->stats['weight'] = floatval($infos['stat_fetchweight']);
        $details->stats['count'] = $infos['stat_fetchcount'];

        $details->permissions = [
            'mayEditUserProfile' => $this->profilePermissions->mayEditUserProfile($userId),
            'mayAdministrateUserProfile' => $this->profilePermissions->mayAdministrateUserProfile($userId, $data['bezirk_id']),
            'administrateBlog' => $this->blogPermissions->mayAdministrateBlog(),
            'editQuiz' => $this->quizPermissions->maySeeEditQuizPage(),
            'handleReports' => $this->reportPermissions->mayHandleReports(),
            'addStore' => $this->storePermissions->mayCreateStore(),
            'editContent' => $this->contentPermissions->mayEditContent(),
            'administrateRegions' => $this->regionPermissions->mayAdministrateRegions(),
            'maySearchGlobal' => $this->searchPermissions->maySearchGlobal(),
            'editStoreCategories' => $this->categoriesPermissions->mayEditCategories(CategoryType::STORE),
            'editResourceCategories' => $this->categoriesPermissions->mayEditCategories(CategoryType::RESOURCE),
        ];

        if ($details->permissions['mayEditUserProfile']) {
            $details->coordinates = empty($data['lat']) || empty($data['lon']) ? null : GeoLocation::createFromArray($data);
            $details->address = $data['anschrift'];
            $details->city = $data['stadt'];
            $details->postcode = $data['plz'];
            $details->email = $data['email'];
            $details->landline = $data['telefon'];
            $details->mobile = $data['handy'];
            $details->birthday = new DateTime($data['geb_datum']);
            $details->aboutMeIntern = $data['about_me_intern'];
            $details->role = $data['rolle'];

            // load region
            $regions = $this->regionTransactions->getUserRegions($userId);
            $details->regions = array_map(fn (UserUnit $region): UserRegionModel => UserRegionModel::createFrom($region), $regions);

            // load groups
            $groups = $this->unitGateway->listAllDirectReleatedUnitsAndResponsibilitiesOfFoodsaver($userId, UnitType::getGroupTypes());
            $details->groups = array_map(fn (UserUnit $group): UserGroupModel => UserGroupModel::createFrom($group), $groups);
        }

        if ($details->permissions['mayAdministrateUserProfile']) {
            $details->position = $data['position'];
        }

        return $details;
    }
}
