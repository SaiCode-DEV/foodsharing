<?php

//declare(strict_types=1); ToDo: somehow, addForumThread is fed with a bool instead of string for dateTime

namespace Tests\Support\Helper;

use Carbon\Carbon;
use Codeception\Module\Db;
use DateTime;
use DateTimeZone;
use Exception;
use Faker\Factory;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\FoodSharePoint\FollowerType;
use Foodsharing\Modules\Core\DBConstants\Info\InfoType;
use Foodsharing\Modules\Core\DBConstants\Mailbox\MailboxFolder;
use Foodsharing\Modules\Core\DBConstants\Quiz\AnswerRating;
use Foodsharing\Modules\Core\DBConstants\Quiz\QuizID;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Region\RegionOptionType;
use Foodsharing\Modules\Core\DBConstants\Region\RegionPinStatus;
use Foodsharing\Modules\Core\DBConstants\Report\ReportType;
use Foodsharing\Modules\Core\DBConstants\Store\CooperationStatus;
use Foodsharing\Modules\Core\DBConstants\StoreTeam\MembershipStatus as STATUS;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Core\DBConstants\Uploads\UploadUsage;
use Foodsharing\Modules\Core\DBConstants\Voting\VotingNotificationType;
use Foodsharing\Modules\Core\DBConstants\Voting\VotingScope;
use Foodsharing\Modules\Core\DBConstants\Voting\VotingType;
use Foodsharing\Modules\Uploads\DTO\UploadedFile;
use PDO;

class Foodsharing extends Db
{
    public $faker;
    private $email_counter = 1;
    private $generated_emails = [];

    public function __construct($moduleContainer, $config = null)
    {
        parent::__construct($moduleContainer, $config);
        $this->faker = Factory::create('de_DE');
    }

    public function clear(): void
    {
        /* This method should clear the database back to a state that seed data can be inserted again without fucking something up.
        It is okay to destroy user generated data and it is okay to fail when the user changed things. Actually, the suggested way is
        to reseed the database in case any modification to static data could have happened */
        $regionsToKeep = implode(',', [
            RegionIDs::ROOT,
            258, // Orgateam Archiv, apparently was a parent of some stuff
            RegionIDs::QUIZ_AND_REGISTRATION_WORK_GROUP,
            RegionIDs::GLOBAL_WORKING_GROUPS,
            RegionIDs::TEAM_BOARD_MEMBER,
            RegionIDs::TEAM_ALUMNI_MEMBER,
            RegionIDs::TEAM_ADMINISTRATION_MEMBER,
        ]);

        $tablesToSkip = implode(',', [
            "'fs_bezirk'",
            "'fs_content'",
            "'fs_fetchweight'",
            "'fs_bezirk_closure'",
            "'phinxlog'"
        ]);

        $tablesToTruncate = $this->_getDriver()->executeQuery("
				SELECT `table_name`
				FROM `information_schema`.`tables`
				WHERE `table_type` = 'BASE TABLE'
					AND `table_schema` = DATABASE()
					AND `table_name` NOT IN (" . $tablesToSkip . ');
		', []);

        // itereate over all tables returned from information_schema
        foreach ($tablesToTruncate->fetchAll(PDO::FETCH_COLUMN) as $table) {
            // and truncate them
            $this->_getDriver()->executeQuery('DELETE FROM ' . $table, []);
        }

        // delete from fs_bezirk but keep certain regions
        $this->_getDriver()->executeQuery('
			DELETE FROM fs_bezirk WHERE id NOT IN(' . $regionsToKeep . ') and type = 7;
			DELETE FROM fs_bezirk WHERE id NOT IN(' . $regionsToKeep . ') and id in (SELECT bez.id as id FROM `fs_bezirk` bez
						left outer join fs_bezirk par on  bez.id = par.parent_id
						where par.parent_id is null
						order by bez.id);
			DELETE FROM fs_bezirk WHERE id NOT IN(' . $regionsToKeep . ');
		', []);
    }

    public function clearTable($table): void
    {
        $this->_getDriver()->deleteQueryByCriteria($table, []);
    }

    /**
     * Simplified and adjusted version of what happens in UploadTransactions::uploadFile.
     *
     * Can be used to "upload" profile pictures for generated users.
     */
    private function uploadFile(UploadedFile $file)
    {
        $uuid = $this->faker->uuid();
        $this->haveInDatabase('uploads', [
            'uuid' => $uuid,
            'user_id' => $file->uploaderId,
            'sha256hash' => $file->hashedBody,
            'mimetype' => $file->mimeType,
            'uploaded_at' => new Carbon(),
            'filesize' => $file->fileSize,
        ]);

        $pathForPersistentFile = implode('/', [ROOT_DIR, 'data/uploads', $uuid[0], $uuid[1] . $uuid[2], $uuid]);
        $dir = dirname($pathForPersistentFile);
        if (!file_exists($dir)) {
            mkdir($dir, 0775, true);
        }
        copy($file->filePath, $pathForPersistentFile);

        return $uuid;
    }

    public function activateFeatureToggle(string $featureToggle): void
    {
        $params = [
            'identifier' => $featureToggle,
            'is_active' => 1,
            'site_environment' => constant('SITE_ENVIRONMENT'),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
        $this->haveInDatabase('fs_feature_toggles', $params);
    }

    /**
     * Insert a new foodsharer into the database.
     *
     * @param string pass to set as foodsharer password
     * @param array extra_params override params
     *
     * @return array with all the foodsaver fields
     */
    public function createFoodsharer($pass = null, $extra_params = []): array
    {
        if (!isset($pass)) {
            $pass = 'password';
        }
        $pictureUrl = null;
        if (random_int(0, 10) === 0) {
            $gender = random_int(2, 3);
        } else {
            $gender = random_int(0, 1);
        }

        if (isset($extra_params['image']) && ($gender == 0 || $gender == 1)) {
            $path = './img/seed-data/profile/' . ['men', 'women'][$gender] . '/' . random_int(0, 99) . '.jpg';
            $profilePicture = new UploadedFile($path, filesize($path), hash_file('sha256', $path), 'image/jpg', 1, null, null);
            $uuid = $this->uploadFile($profilePicture);
            $pictureUrl = '/api/uploads/' . $uuid;
        }

        // Ensure the quizes actually exist. This avoids spurious 404 errors
        // during API tests
        if (!isset($extra_params['skip_quiz_creation']) || !$extra_params['skip_quiz_creation']) {
            $this->createQuizes();
        }
        // remove the param so it doesn't interfere with haveInDatabase (if set)
        if (isset($extra_params['skip_quiz_creation'])) {
            unset($extra_params['skip_quiz_creation']);
        }

        $params = array_merge([
            'email' => $this->faker->unique()->email(),
            'bezirk_id' => 0,
            'name' => $this->faker->firstName(['male', 'female'][$gender] ?? null),
            'nachname' => $this->faker->lastName(),
            'deleted_at' => null,
            'verified' => 0,
            'rolle' => 0,
            'plz' => $this->faker->postcode(),
            'stadt' => $this->faker->city(),
            'lat' => $this->faker->latitude(55, 46),
            'lon' => $this->faker->longitude(4, 16),
            'anmeldedatum' => $this->faker->dateTimeBetween('-5 years', '-5 days'),
            'geb_datum' => $this->faker->dateTimeBetween('-80 years', '-18 years'),
            'last_login' => $this->faker->dateTimeBetween('-1 years', '-1 hours'),
            'anschrift' => $this->faker->streetName(),
            'handy' => $this->faker->e164PhoneNumber(),
            'active' => 1,
            'privacy_policy_accepted_date' => '2020-05-16 00:09:33',
            'privacy_notice_accepted_date' => '2018-05-24 18:25:28',
            'token' => uniqid('', true),
            'photo' => $pictureUrl,
            'geschlecht' => $gender,
            'newsletter' => random_int(0, 1),
        ], $extra_params);
        unset($params['image']);
        $params['password'] = password_hash((string)$pass, PASSWORD_ARGON2I, [
            'time_cost' => 1
        ]);
        $params['geb_datum'] = $this->toDateTime($params['geb_datum']);
        $params['last_login'] = $this->toDateTime($params['last_login']);
        $params['anmeldedatum'] = $this->toDateTime($params['anmeldedatum']);
        $id = $this->haveInDatabase('fs_foodsaver', $params);
        if ($params['bezirk_id']) {
            $this->addRegionMember($params['bezirk_id'], $id);
        }
        $params['id'] = $id;

        if (isset($extra_params['image']) && isset($uuid)) {
            $this->updateInDatabase('uploads', [
                'used_in' => UploadUsage::PROFILE_PHOTO->value,
                'usage_id' => $id,
            ], ['uuid' => $uuid]);
        }

        return $params;
    }

    public function createStoreCategories(): void
    {
        $Categories = [
                             'Bäckerei',
                             'Bio-Bäckerei',
                             'Bio-Supermarkt',
                             'Getränkemarkt',
                             'Metzgerei',
                             'Organisation - Einführungsabholungen',
                             'Organisation - Botschaftertätigkeit',
                             'Organisation - Fairteiler',
                             'Organisation - Vorstandsarbeit',
                             'Organisation - Meldebearbeitung',
                             'Öffentlichkeitsarbeit',
                             'Restaurant',
                             'Schnellimbiss',
                             'Supermarkt',
                             'Wochenmarkt'
                            ];
        $entryId = 1;
        foreach ($Categories as $catEntry) {
            $this->haveInDatabase('fs_betrieb_kategorie', ['id' => $entryId, 'name' => $catEntry]);
            ++$entryId;
        }
    }

    public function createQuiz(int $quizId, ?int $questionCount = null): array
    {
        $questionCount ??= random_int(3, 6);
        $questionCountUntimed = $quizId === 1 ? 2 + $questionCount : null;
        $params = [
            'id' => $quizId,
            'maxfp' => 2,
            'questcount' => $questionCount,
            'questcount_untimed' => $questionCountUntimed,
        ];
        if ($quizId <= QuizID::AMBASSADOR->value) {
            $roles = [
                Role::FOODSAVER->value => 'Foodsaver:in',
                Role::STORE_MANAGER->value => 'Betriebsverantwortliche:r',
                Role::AMBASSADOR->value => 'Botschafter:in'
            ];
            $params['name'] = 'Quiz für ' . $roles[$quizId];
            $params['desc'] = 'Werde ' . $roles[$quizId] . ' mit diesem Quiz!';
        } elseif ($quizId === QuizID::HYGIENE->value) {
            $params['name'] = 'Hygieneschulung';
            $params['desc'] = 'Mit diesem Quiz qualifizierst du dich für Abholungen in bestimmten Betrieben...';
        } elseif ($quizId === QuizID::FOODSAVER_FR->value) {
            $params['name'] = 'Quiz für Foodsaver (FR)';
            $params['desc'] = 'Werde foodsaver in Frankreich mit diesem Quiz!';
        }
        $params['desc'] .= ' ' . $this->faker->realTextBetween(200, 500);
        $params['id'] = $this->haveInDatabase('fs_quiz', $params);

        $params['questions'] = [];
        for ($i = 1; $i <= $questionCount * 2; ++$i) {
            $params['questions'][] = $this->createQuestion($params['id']);
        }

        return $params;
    }

    /**
     * Ensure a set of predefined quizzes exist in the test database.
     *
     * This method verifies that the quizzes identified by the QuizID enum
     * values are present in the "fs_quiz" table and creates any that are
     * missing. It is safe to call multiple times (idempotent) and is intended
     * to be used as test setup.
     */
    private function createQuizes(): void
    {
        $want_quizes = QuizID::cases();
        $have_quizes = $this->grabColumnFromDatabase('fs_quiz', 'id');
        foreach ($want_quizes as $quizId) {
            if (!in_array($quizId->value, $have_quizes, true)) {
                $this->createQuiz($quizId->value);
            }
        }
    }

    private function createQuestion(int $quizId): array
    {
        $params = [
            'text' => $this->faker->realTextBetween(100, 300),
            'duration' => random_int(3, 6) * 10,
            'wikilink' => 'https://wiki.foodsharing.de/' . $this->faker->slug(),
        ];
        $questionId = $this->haveInDatabase('fs_question', $params);
        $params['id'] = $questionId;
        $failurePoints = random_int(1, 3);

        $this->haveInDatabase('fs_question_has_quiz', [
            'question_id' => $questionId,
            'quiz_id' => $quizId,
            'fp' => $failurePoints
        ]);

        $params['answers'] = [];
        $numAnswers = random_int(2, 5);
        for ($i = 0; $i < $numAnswers; ++$i) {
            $params['answers'][] = $this->createAnswer($questionId, AnswerRating::from(random_int(0, 2)));
        }

        return $params;
    }

    private function createAnswer(int $questionId, AnswerRating $right): array
    {
        $params = [
            'question_id' => $questionId,
            'text' => $this->faker->realTextBetween() . ' (' . $right->name . ')',
            'explanation' => 'Diese Antwort ist ' . $right->name . '. ' . $this->faker->realTextBetween(),
            'right' => $right->value
        ];
        $params['id'] = $this->haveInDatabase('fs_answer', $params);

        return $params;
    }

    public function createQuizTry(int $fsId, int $level, int $status, int $daysAgo = 0): void
    {
        $startTime = Carbon::now()->subDays($daysAgo);
        $v = [
            'quiz_id' => $level,
            'status' => $status,
            'foodsaver_id' => $fsId,
            'time_start' => $this->toDateTime($startTime)
        ];
        $this->haveInDatabase('fs_quiz_session', $v);
    }

    public function createFoodsaver($pass = null, $extra_params = []): array
    {
        $params = array_merge([
            'verified' => 1,
            'rolle' => 1,
            'quiz_rolle' => 1,
            'last_pass' => Carbon::now()->subYears(1)->toDateTimeString()
        ], $extra_params);
        $params = $this->createFoodsharer($pass, $params);
        $this->createQuizTry($params['id'], 1, 1);

        return $params;
    }

    public function addVerificationHistory(int $userId, int $ambassadorId, bool $verified, ?DateTime $date = null): void
    {
        if (is_null($date)) {
            $date = $this->faker->dateTimeThisDecade();
        }
        $this->haveInDatabase('fs_verify_history', [
            'fs_id' => $userId,
            'date' => $date->format('Y-m-d H:i:s'),
            'bot_id' => $ambassadorId,
            'change_status' => intval($verified),
        ]);
    }

    public function addPassHistory(int $userId, int $ambassadorId, ?DateTime $date = null): void
    {
        if (is_null($date)) {
            $date = $this->faker->dateTimeThisDecade();
        }
        $this->haveInDatabase('fs_pass_gen', [
            'foodsaver_id' => $userId,
            'date' => $date->format('Y-m-d H:i:s'),
            'bot_id' => $ambassadorId,
        ]);
    }

    public function createStoreCoordinator($pass = null, $extra_params = []): array
    {
        if (!array_key_exists('bezirk_id', $extra_params)) {
            $region = $this->createRegion();
            $extra_params['bezirk_id'] = $region['id'];
        }
        $params = array_merge([
            'rolle' => 2,
            'quiz_rolle' => 2,
        ], $extra_params);
        $params = $this->createFoodsaver($pass, $params);
        $this->createQuizTry($params['id'], 2, 1);

        // create a mailbox and assign this user to it
        $mailbox = $this->createMailbox(strtolower($params['name'][0] . '.' . $params['nachname']), need_unique: true);
        $this->updateInDatabase('fs_foodsaver', ['mailbox_id' => $mailbox['id']], ['id' => $params['id']]);

        return $params;
    }

    public function createAmbassador($pass = null, $extra_params = []): array
    {
        $params = array_merge([
            'rolle' => 3,
            'quiz_rolle' => 3,
        ], $extra_params);
        $params = $this->createStoreCoordinator($pass, $params);
        $this->createQuizTry($params['id'], 3, 1);

        return $params;
    }

    public function createOrga($pass = null, $is_admin = false, $extra_params = []): array
    {
        $params = array_merge([
            'rolle' => ($is_admin ? 5 : 4),
        ], $extra_params);

        $params = $this->createAmbassador($pass, $params);

        return $params;
    }

    public function createStore($bezirk_id, $team_conversation = null, $springer_conversation = null, $extra_params = []): array
    {
        // one third of the stores are assigned to an existing store category
        $storeCategoryId = null;
        if (random_int(0, 2) > 1) {
            $categories = $this->grabColumnFromDatabase('fs_betrieb_kategorie', 'id');
            $storeCategoryId = $this->faker->randomElement($categories);
        }

        $params = array_merge([
            'betrieb_status_id' => $this->faker->randomElement(array_slice(CooperationStatus::cases(), 0, -1))->value,
            'status' => 1,
            'added' => $this->toDate($this->faker->dateTime()),
            'betrieb_kategorie_id' => $storeCategoryId,
            'plz' => $this->faker->postcode(),
            'stadt' => $this->faker->city(),
            'str' => $this->faker->streetAddress(),
            'lat' => $this->faker->latitude(55, 46),
            'lon' => $this->faker->longitude(4, 16),
            'name' => 'betrieb_' . $this->faker->company(),
            'status_date' => $this->toDate($this->faker->dateTime()),
            'ansprechpartner' => $this->faker->name(),
            'telefon' => $this->faker->phoneNumber(),
            'fax' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->email(),
            'begin' => $this->toDate($this->faker->dateTime()),
            'besonderheiten' => '',
            'public_info' => '',
            'public_time' => 0,
            'ueberzeugungsarbeit' => 0,
            'presse' => 0,
            'sticker' => 0,
            'abholmenge' => $this->faker->numberBetween(0, 8),
            'team_status' => 1,
            'prefetchtime' => 1_209_600,

            // relations
            'bezirk_id' => $bezirk_id,
            'team_conversation_id' => $team_conversation,
            'springer_conversation_id' => $springer_conversation,
            'kette_id' => 0,
        ], $extra_params);
        $params['status_date'] = $this->toDate($params['status_date']);
        $params['added'] = $this->toDate($params['added']);

        $params['id'] = $this->haveInDatabase('fs_betrieb', $params);

        return $params;
    }

    /** Adds a user or an array of users to the store team.
     * If the user is not confirmed yet, the waiter status is ignored.
     * Care: This method does not care about store conversations!
     * Care: This method does not care about adding the user to the matching bezirk!
     */
    public function addStoreTeam($store_id, $fs_id, $is_coordinator = false, $is_waiting = false, $is_confirmed = true): void
    {
        if (is_array($fs_id)) {
            foreach ($fs_id as $fs) {
                $this->addStoreTeam($store_id, $fs, $is_coordinator, $is_waiting, $is_confirmed);
            }
        } else {
            $v = [
                'betrieb_id' => $store_id,
                'foodsaver_id' => $fs_id,
                'active' => $is_confirmed ? ($is_waiting ? STATUS::JUMPER : STATUS::MEMBER) : STATUS::APPLIED_FOR_TEAM,
                'verantwortlich' => $is_coordinator ? 1 : 0,
                'stat_add_date' => $this->faker->dateTimeThisCentury()->format('Y-m-d H:i:s'),
            ];

            $conditions = [
                'betrieb_id' => $store_id,
                'foodsaver_id' => $fs_id
            ];
            $res = $this->countInDatabase('fs_betrieb_team', $conditions);
            if ($res < 1) {
                $this->haveInDatabase('fs_betrieb_team', $v);
            }
        }
    }

    public function addCollector($user, $store, $extra_params = [])
    {
        $params = array_merge([
            'foodsaver_id' => $user,
            'betrieb_id' => $store,
            'date' => $this->faker->dateTime(),
            'confirmed' => 1
        ], $extra_params);
        $params['date'] = $this->toDateTime($params['date']);
        $res = $this->countInDatabase('fs_abholer', $params);
        if ($res < 1) {
            $id = $this->haveInDatabase('fs_abholer', $params);
            $params['id'] = $id;
        }

        return $params;
    }

    public function addStoreNotiz($user, $store, $extra_params = []): array
    {
        $params = array_merge([
            'foodsaver_id' => $user,
            'betrieb_id' => $store,
            'milestone' => 0,
            'text' => $this->faker->realText(100),
            'zeit' => $this->faker->dateTime(),
            'last' => 0, // should be 1 for newest entry, can't do that here though
        ], $extra_params);
        $params['zeit'] = $this->toDateTime($params['zeit']);

        $id = $this->haveInDatabase('fs_betrieb_notiz', $params);
        $params['id'] = $id;

        return $params;
    }

    public function addPickup($store, $extra_params = []): array
    {
        $date = $this->faker->date('Y-m-d H:i:s');
        $params = array_merge([
            'betrieb_id' => $store,
            'time' => $date,
            'fetchercount' => $this->faker->numberBetween(1, 8)
        ], $extra_params);

        $params['time'] = $this->toDateTime($params['time']);

        $id = $this->haveInDatabase('fs_fetchdate', $params);
        $params['id'] = $id;

        return $params;
    }

    public function addRecurringPickup($store, $extra_params = [])
    {
        $hours = $this->faker->numberBetween(0, 23);
        $minutes = $this->faker->randomElement($array = ['00', '05', '10', '15', '20', '25', '30', '35', '40', '45', '50', '55']);

        $params = array_merge([
            'betrieb_id' => $store,
            'dow' => $this->faker->numberBetween(0, 6),
            'time' => sprintf('%02d:%s:00', $hours, $minutes),
            'fetcher' => $this->faker->numberBetween(1, 8),
        ], $extra_params);

        try {
            /* ToDo: Easy to generate a collision with the chosen randoms on big number of stores */
            $id = $this->haveInDatabase('fs_abholzeiten', $params);
            $params['id'] = $id;
        } catch (Exception $e) {
            if (!$extra_params) {
                return $this->addRecurringPickup($store, $extra_params);
            }

            throw $e;
        }

        return $params;
    }

    public function addPicker($store, $foodsaverId, $extra_params = []): array
    {
        $date = $this->faker->date('Y-m-d H:i:s');
        $params = array_merge([
            'foodsaver_id' => $foodsaverId,
            'betrieb_id' => $store,
            'date' => $date,
            'confirmed' => '1'
        ], $extra_params);

        $params['date'] = $this->toDateTime($params['date']);

        $id = $this->haveInDatabase('fs_abholer', $params);
        $params['id'] = $id;

        return $params;
    }

    public function addStoreLog($store_id, $foodsaverId_a, $foodsaverId_p, $action, $extra_params = []): array
    {
        $date = $this->faker->date('Y-m-d H:i:s');

        $params = array_merge([
            'store_id' => $store_id,
            'fs_id_a' => $foodsaverId_a,
            'fs_id_p' => $foodsaverId_p,
            'action' => $action,
            'date_activity' => $date
        ], $extra_params);

        $params['date_reference'] = $this->toDateTime($params['date_reference']);
        $params['date_activity'] = $this->toDateTime($params['date_activity']);

        $id = $this->haveInDatabase('fs_store_log', $params);
        $params['id'] = $id;

        return $params;
    }

    public function addStoreFoodType($extra_params = [])
    {
        $params = array_merge([
            'name' => 'food_' . $this->faker->word()
        ], $extra_params);

        $id = $this->haveInDatabase('fs_lebensmittel', $params);
        $params['id'] = $id;

        return $params;
    }

    public function addStoreChain($extra_params = [])
    {
        $params = array_merge([
            'name' => 'chain_' . $this->faker->company(),
            'headquarters_zip' => $this->faker->postcode(),
            'headquarters_city' => $this->faker->city(),
            'status' => random_int(0, 2),
            'modification_date' => $this->faker->dateTimeThisDecade()->format('Y-m-d H:i:s'),
            'allow_press' => random_int(0, 1),
            'notes' => $this->faker->realTextBetween(5, 80),
            'common_store_information' => $this->faker->realTextBetween(100, 500),
        ], $extra_params);

        $id = $this->haveInDatabase('fs_chain', $params);
        $params['id'] = $id;

        return $params;
    }

    public function createWorkingGroup($name, $extra_params = [], bool $fillMailbox = true)
    {
        $extra_params = array_merge([
            'parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS,
            'type' => UnitType::WORKING_GROUP,
            'teaser' => 'an autogenerated working group without a description',
        ], $extra_params);

        return $this->createRegion($name, $extra_params, fillMailbox: $fillMailbox);
    }

    public function createBlacklistedEmailAddress(): void
    {
        $since = (new DateTime('2010-10-14 12:00:00'))->format('Y-m-d H:i:s');
        $this->haveInDatabase('fs_email_blacklist', ['email' => 'bad.com', 'since' => $since, 'reason' => 'Disposable email addresses should not be used for registration.']);
    }

    public function createMailbox($name = null, bool $fillMailbox = true, bool $need_unique = false, int $forced_id = -1)
    {
        if ($name == null) {
            $name = $this->faker->unique()->userName();
        }

        // Convert mailbox name to lowercase and replace Umlauts
        $name = $this->replaceUmlauts(strtolower($name));

        // In case the mailbox name is already in use, start appending numbers
        // until we find a free one
        $originalName = $name;
        $i = 1;
        while ($need_unique && array_key_exists($name, $this->generated_emails)) {
            $name = $originalName . $i;
            ++$i;
        }
        $this->generated_emails[$name] = true;

        $mb['name'] = $name;
        if ($forced_id > -1) {
            $mb['id'] = $forced_id;
        }
        $mb['id'] = $this->haveInDatabase('fs_mailbox', $mb);

        // add up to 10 emails to each folder
        if ($fillMailbox) {
            foreach ([MailboxFolder::FOLDER_INBOX, MailboxFolder::FOLDER_SENT, MailboxFolder::FOLDER_TRASH] as $folder) {
                $numMails = $this->faker->numberBetween(10, 20);
                for ($i = 0; $i < $numMails; ++$i) {
                    $this->createEmail($mb, $folder);
                }
            }
        }

        return $mb;
    }

    public function createEmail(array $mailbox, int $folder, array $extra_params = []): int
    {
        $body = $this->faker->realText(200);
        $extra_params = array_merge([
            'mailbox_id' => $mailbox['id'],
            'folder' => $folder,
            'subject' => $this->faker->text(30),
            'body' => $body,
            'body_html' => $body,
            'time' => $this->faker->dateTimeThisDecade()->format('Y-m-d H:i:s'),
            'attach' => '[]',
            'read' => ($folder == MailboxFolder::FOLDER_INBOX) ? $this->faker->boolean() : true,
            'answer' => false
        ], $extra_params);

        // add sender and receiver based on the mailbox folder
        if ($folder == MailboxFolder::FOLDER_INBOX) {
            $extra_params['sender'] = $this->createRandomEmailAddress($this->faker->boolean());
            $extra_params['to'] = [$this->createFoodsharingEmailAddress($mailbox)];
            for ($i = 0; $i < $this->faker->numberBetween(0, 3); ++$i) {
                $extra_params['to'][] = $this->createRandomEmailAddress(false);
            }
        } else {
            $extra_params['to'] = [];
            for ($i = 0; $i < $this->faker->numberBetween(1, 5); ++$i) {
                $extra_params['to'][] = $this->createRandomEmailAddress(false);
            }
            $extra_params['sender'] = $this->createFoodsharingEmailAddress($mailbox);
        }

        $extra_params['sender'] = json_encode($extra_params['sender']);
        $extra_params['to'] = json_encode($extra_params['to']);

        return $this->haveInDatabase('fs_mailbox_message', $extra_params);
    }

    private function createFoodsharingEmailAddress(array $mailbox): array
    {
        return [
            'host' => 'foodsharing.network',
            'mailbox' => $mailbox['name'],
            'personal' => $mailbox['name'] . '@foodsharing.network'
        ];
    }

    private function createRandomEmailAddress(bool $includePersonal = true): array
    {
        return [
            'host' => $this->faker->safeEmailDomain(),
            'mailbox' => $this->faker->userName(),
            'personal' => $includePersonal ? $this->faker->name() : null
        ];
    }

    public function addBuddy(int $user1, int $user2, bool $confirmed = true): void
    {
        $confirmedInt = $confirmed ? 1 : 0;
        $this->haveInDatabase('fs_buddy', ['foodsaver_id' => $user1, 'buddy_id' => $user2, 'confirmed' => $confirmedInt]);
    }

    public function createRegion($name = null, $extra_params = [], bool $fillMailbox = true)
    {
        if ($name == null) {
            $name = $this->faker->lastName() . '-region';
        }

        $v = array_merge(
            [
                'parent_id' => RegionIDs::EUROPE,
                'name' => $name,
                'type' => UnitType::PART_OF_TOWN
            ],
            $extra_params
        );
        $email = $v['email'] ?? null;
        unset($v['email']);
        $v['id'] = $this->haveInDatabase('fs_bezirk', $v);
        if (!$email) {
            $mailbox = $this->createMailbox('region-' . $v['id'], $fillMailbox);
        } else {
            $mailbox = $this->createMailbox($email, $fillMailbox);
        }

        $this->updateInDatabase('fs_bezirk', ['mailbox_id' => $mailbox['id']], ['id' => $v['id']]);
        /* Add to closure table for hierarchies */
        $this->_getDriver()->executeQuery('INSERT INTO `fs_bezirk_closure`
		(ancestor_id, bezirk_id, depth)
		SELECT t.ancestor_id, ?, t.depth+1 FROM `fs_bezirk_closure` AS t WHERE t.bezirk_id = ?
		UNION ALL SELECT ?, ?, 0', [$v['id'], $v['parent_id'], $v['id'], $v['id']]);

        return $v;
    }

    public function addRegionAdmin($region_id, $fs_id): void
    {
        $v = [
            'bezirk_id' => $region_id,
            'foodsaver_id' => $fs_id,
        ];
        $result = $this->countInDatabase('fs_botschafter', $v);
        if ($result <= 0) {
            $this->haveInDatabase('fs_botschafter', $v);
        }
    }

    public function addAchievement($achievementData): void
    {
        $this->haveInDatabase('fs_achievement', $achievementData);
    }

    public function awardAchievement($fs_id, $achievement_id, $reviewer_id, array $extra_params = []): void
    {
        if (is_array($fs_id)) {
            foreach ($fs_id as $fs) {
                $this->awardAchievement($fs, $achievement_id, $reviewer_id, $extra_params);
            }
        } else {
            $achievementData = array_merge([
                'foodsaver_id' => $fs_id,
                'achievement_id' => $achievement_id,
                'reviewer_id' => $reviewer_id,
                'valid_until' => Carbon::now()->addYear(1)->format('Y-m-d H:i:s'),
                'created_at' => $this->faker->dateTimeThisDecade()->format('Y-m-d H:i:s'),
            ], $extra_params);

            $this->haveInDatabase('fs_foodsaver_has_achievement', $achievementData);
        }
    }

    public function addRegionMember($region_id, $fs_id, $is_active = true): void
    {
        if (is_array($fs_id)) {
            array_map(function ($x) use ($region_id, $is_active) {
                $this->addRegionMember($region_id, $x, $is_active);
            }, $fs_id);
        } else {
            $v = [
                'bezirk_id' => $region_id,
                'foodsaver_id' => $fs_id,
                'active' => $is_active ? 1 : 0,
            ];
            $result = $this->countInDatabase('fs_foodsaver_has_bezirk', $v);
            if ($result <= 0) {
                $this->haveInDatabase('fs_foodsaver_has_bezirk', $v);
            }
        }
    }

    public function addForumThread($region_id, $fs_id, bool $isAmbassadorThread = false, array $extra_params = []): array
    {
        $params = array_merge([
            'foodsaver_id' => $fs_id,
            'name' => $this->faker->sentence(),
            'time' => $this->faker->dateTime(),
            'active' => '1',
            'status' => 0
        ], $extra_params);
        $params['time'] = $this->toDateTime($params['time']);

        $threadId = $this->haveInDatabase('fs_theme', $params);

        $this->haveInDatabase('fs_bezirk_has_theme', [
            'theme_id' => $threadId,
            'bezirk_id' => $region_id,
            'bot_theme' => ($isAmbassadorThread ? 1 : 0),
        ]);

        $post_params = [
            'body' => $this->faker->realText(500),
            'time' => $params['time'],
        ];
        $params['post'] = $this->addForumThreadPost($threadId, $fs_id, $post_params);
        $params['id'] = $threadId;

        return $params;
    }

    public function addForumThreadPost($threadId, $fs_id, array $extra_params = []): array
    {
        $params = array_merge([
            'theme_id' => $threadId,
            'foodsaver_id' => $fs_id,
            'body' => $this->faker->realText(200),
            'time' => $this->faker->dateTime(),
        ], $extra_params);
        $params['time'] = $this->toDateTime($params['time']);

        $params['id'] = $this->haveInDatabase('fs_theme_post', $params);

        $this->updateForumThreadWithPost($threadId, $params);

        return $params;
    }

    public function createConversation($users, array $extra_params = [])
    {
        $params = array_merge([
            'locked' => 0,
            'name' => null,
            'last' => $this->faker->dateTime(),
            'last_foodsaver_id' => $users ? $users[0] : null,
            'last_message_id' => null,
            'last_message' => null,
        ], $extra_params);
        $params['last'] = $this->toDateTime($params['last']);
        $id = $this->haveInDatabase('fs_conversation', $params);

        foreach ($users as $user) {
            $this->addUserToConversation($user, $id);
        }

        $params['id'] = $id;

        return $params;
    }

    public function addUserToConversation($user, $conversation, $extra_params = [])
    {
        $params = array_merge([
            'foodsaver_id' => $user,
            'conversation_id' => $conversation,
            'unread' => 0,
        ], $extra_params);

        $id = $this->haveInDatabase('fs_foodsaver_has_conversation', $params);

        $params['id'] = $id;

        return $params;
    }

    public function addConversationMessage($user, $conversation, $extra_params = []): array
    {
        $params = array_merge([
            'foodsaver_id' => $user,
            'conversation_id' => $conversation,
            'body' => $this->faker->realText(100),
            'time' => $this->faker->dateTime()
        ], $extra_params);
        $params['time'] = $this->toDateTime($params['time']);

        $id = $this->haveInDatabase('fs_msg', $params);

        $this->updateInDatabase('fs_conversation', [
            'last_message' => $params['body'],
            'last_message_id' => $id,
            'last_foodsaver_id' => $user,
            'last' => $params['time']
        ], ['id' => $conversation]);

        $params['id'] = $id;

        return $params;
    }

    public function createFoodSharePoint($user, $bezirk = null, $extra_params = []): array
    {
        if ($bezirk === null) {
            $bezirk = $this->createRegion()['id'];
        }
        $params = array_merge([
            'bezirk_id' => $bezirk,
            'name' => $this->faker->city(),
            'desc' => $this->faker->text(200),
            'status' => 1,
            'anschrift' => $this->faker->address(),
            'plz' => $this->faker->postcode(),
            'ort' => $this->faker->city(),
            'lat' => $this->faker->latitude(55, 46),
            'lon' => $this->faker->longitude(4, 16),
            'add_date' => $this->faker->dateTime(),
            'add_foodsaver' => $user,
        ], $extra_params);
        $params['add_date'] = $this->toDateTime($params['add_date']);

        $id = $this->haveInDatabase('fs_fairteiler', $params);

        $this->addFoodSharePointAdmin($user, $id);

        $params['id'] = $id;

        return $params;
    }

    public function addFoodSharePointFollower($user, $foodSharePoint, $extra_params = []): array
    {
        $params = array_merge([
            'fairteiler_id' => $foodSharePoint,
            'foodsaver_id' => $user,
            'type' => FollowerType::FOLLOWER,
            'infotype' => InfoType::EMAIL,
        ], $extra_params);
        $this->haveInDatabase('fs_fairteiler_follower', $params);

        return $params;
    }

    public function addFoodSharePointAdmin($user, $foodSharePoint, $extra_params = []): array
    {
        return $this->addFoodSharePointFollower($user, $foodSharePoint, array_merge($extra_params, ['type' => FollowerType::FOOD_SHARE_POINT_MANAGER]));
    }

    public function addFoodSharePointPost($user, $foodSharePoint, $extra_params = []): array
    {
        $post = $this->createWallpost($user, $extra_params);
        $this->haveInDatabase('fs_fairteiler_has_wallpost', [
            'fairteiler_id' => $foodSharePoint,
            'wallpost_id' => $post['id'],
        ]);

        return $post;
    }

    public function createWallpost($user, $extra_params = []): array
    {
        $params = array_merge([
            'foodsaver_id' => $user,
            'body' => $this->faker->realText(200),
            'time' => $this->faker->dateTime(),
        ], $extra_params);
        $params['time'] = $this->toDateTime($params['time']);

        $id = $this->haveInDatabase('fs_wallpost', $params);

        $params['id'] = $id;

        return $params;
    }

    public function createCommunityPin($region_id, $extra_params = []): array
    {
        $params = array_merge([
            'region_id' => $region_id,
            'lat' => $this->faker->latitude(55, 46),
            'lon' => $this->faker->longitude(4, 16),
            'desc' => $this->faker->realText(200),
            'status' => RegionPinStatus::ACTIVE
        ], $extra_params);

        $id = $this->haveInDatabase('fs_region_pin', $params);

        $params['id'] = $id;

        return $params;
    }

    public function createEvents($region_id, $foodsaver_id, $extra_params = []): array
    {
        $paramsLocation = [
            'name' => $this->faker->text(30),
            'lat' => $this->faker->latitude(55, 46),
            'lon' => $this->faker->longitude(4, 16),
        ];

        $location_id = $this->haveInDatabase('fs_location', $paramsLocation);

        $params = array_merge([
            'bezirk_id' => $region_id,
            'foodsaver_id' => $foodsaver_id,
            'public' => $this->faker->numberBetween(0, 2),
            'location_id' => $location_id,
            'name' => $this->faker->text(100),
            'start' => $this->faker->dateTimeBetween('-1 hour', 'now')->format('Y-m-d H:i:s'),
            'end' => $this->faker->dateTimeBetween('now', '+2 hour')->format('Y-m-d H:i:s'),
            'description' => $this->faker->realText(200),
        ], $extra_params);

        $id = $this->haveInDatabase('fs_event', $params);

        $params['id'] = $id;

        $paramsFsEvent = [
            'foodsaver_id' => $foodsaver_id,
            'event_id' => $id,
        ];

        $this->haveInDatabase('fs_foodsaver_has_event', $paramsFsEvent);

        return $params;
    }

    public function addEventInvitation($event_id, $foodsaver_id, $extra_params = []): array
    {
        $params = array_merge([
            'foodsaver_id' => $foodsaver_id,
            'event_id' => $event_id,
        ], $extra_params);

        // Update invitation if it already exists (e.g., status changes),
        // otherwise create a new one
        $conditions = [
            'foodsaver_id' => $foodsaver_id,
            'event_id' => $event_id
        ];
        $res = $this->countInDatabase('fs_foodsaver_has_event', $conditions);
        if ($res > 0) {
            $id = $this->updateInDatabase('fs_foodsaver_has_event', $params, $conditions);
        } else {
            $id = $this->haveInDatabase('fs_foodsaver_has_event', $params);
        }

        $params['id'] = $id;

        return $params;
    }

    /**
     * Calculate the destination point reached by travelling a given distance
     * from an initial geographic point along a specified bearing (great-circle
     * / spherical Earth solution).
     *
     * Uses the spherical Earth formula:
     *   φ2 = asin( sin φ1 * cos(d/R) + cos φ1 * sin(d/R) * cos θ )
     *   λ2 = λ1 + atan2( sin θ * sin(d/R) * cos φ1, cos(d/R) - sin φ1 * sin φ2 )
     *
     * Notes:
     * - The function assumes a spherical Earth with mean radius R = 6371 km.
     * - Inputs are in degrees (latitude, longitude, bearing) except distance
     *   which is in kilometres.
     * - Output is an array [latitude, longitude] in degrees.
     * - Longitude is not explicitly normalized to the [-180, 180] range;
     *   callers may normalize if required.
     * - Behavior near the poles or for very large distances (antipodal points)
     *   can be numerically sensitive.
     *
     * @param float $lat           initial latitude in degrees
     * @param float $lon           initial longitude in degrees
     * @param float $distanceKm    Distance to travel from the initial point, in
     * kilometres. If negative, a random distance between 0 and 20 km is used.
     * @param float $bearingDegrees Bearing (true heading) in degrees, clockwise
     * from north. If negative, a random bearing between 0 and 360 degrees is used.
     * @return float[]             array with two floats:
     * [destinationLatitudeInDegrees, destinationLongitudeInDegrees]
     */
    public function getPointAtDistance($lat, $lon, $distanceKm, $bearingDegrees): array
    {
        $earthRadiusKm = 6371;

        if ($distanceKm < 0) {
            $distanceKm = $this->faker->numberBetween(0, 20);
        }
        if ($bearingDegrees < 0) {
            $bearingDegrees = $this->faker->numberBetween(0, 360);
        }

        // Convert from degrees to radians
        $bearingRad = deg2rad($bearingDegrees);
        $latRad = deg2rad($lat);
        $lonRad = deg2rad($lon);

        $newLatRad = asin(sin($latRad) * cos($distanceKm / $earthRadiusKm) +
            cos($latRad) * sin($distanceKm / $earthRadiusKm) * cos($bearingRad));
        $newLonRad = $lonRad + atan2(sin($bearingRad) * sin($distanceKm / $earthRadiusKm) * cos($latRad),
            cos($distanceKm / $earthRadiusKm) - sin($latRad) * sin($newLatRad));

        return [rad2deg($newLatRad), rad2deg($newLonRad)];
    }

    public function createFoodbasket($user, $extra_params = []): array
    {
        $params = array_merge([
            'foodsaver_id' => $user,
            'status' => 1,
            'time' => $this->faker->dateTime($max = 'now'),
            'until' => $this->faker->dateTimeBetween('+1 std', '+14 days'),
            'fetchtime' => null,
            'description' => $this->faker->realText(200),
            'picture' => null,
            'tel' => $this->faker->phoneNumber(),
            'handy' => $this->faker->phoneNumber(),
            'contact_type' => 1,
            'location_type' => 0,
            'weight' => $this->faker->numberBetween(1, 100),
            'lat' => $this->faker->latitude(55, 46),
            'lon' => $this->faker->longitude(4, 16),
            'bezirk_id' => 0,
        ], $extra_params);
        $params['time'] = $this->toDateTime($params['time']);
        $params['until'] = $this->toDateTime($params['until']);

        $id = $this->haveInDatabase('fs_basket', $params);

        $params['id'] = $id;

        return $params;
    }

    public function addBells($users, $extra_params = []): int
    {
        $params = array_merge([
            'name' => 'title',
            'body' => $this->faker->text(50),
            'vars' => '',
            'attr' => serialize(['href' => '/']),
            'icon' => 'icon',
            'identifier' => '',
            'time' => $this->faker->dateTime($max = 'now'),
            'closeable' => 1
        ], $extra_params);
        $params['time'] = $this->toDateTime($params['time']);

        $bell_id = $this->haveInDatabase('fs_bell', $params);

        foreach ($users as $user) {
            $this->haveInDatabase('fs_foodsaver_has_bell', [
                'foodsaver_id' => $user['id'],
                'bell_id' => $bell_id,
                'seen' => 0
            ]);
        }

        return $bell_id;
    }

    public function addBlogPost($authorId, $regionId, $extra_params = []): array
    {
        $params = array_merge([
            'bezirk_id' => $regionId,
            'foodsaver_id' => $authorId,
            'name' => $this->faker->text(40),
            'body' => $this->faker->text(2000),
            'teaser' => $this->faker->text(50),
            'time' => $this->faker->dateTime($max = 'now'),
            'active' => 1,
            'picture' => ''
        ], $extra_params);
        $params['time'] = $this->toDateTime($params['time']);
        $params['id'] = $this->haveInDatabase('fs_blog_entry', $params);

        return $params;
    }

    public function addReport($reporterId, $reporteeId, $storeId = 0, $confirmed = 0, $reason = null, $msg = null): array
    {
        $params = [
            'reporter_id' => $reporterId,
            'foodsaver_id' => $reporteeId,
            'betrieb_id' => $storeId,
            'reporttype' => ReportType::LOCAL->value,
            'report_reason_id' => 2,
            'time' => $this->toDateTime($this->faker->dateTimeBetween('first day of january this year', $max = 'now')),
            'msg' => $msg ?? $this->faker->text(500),
            'tvalue' => $reason ?? $this->faker->text(50),
            'committed' => $confirmed
        ];
        $params['id'] = $this->haveInDatabase('fs_report', $params);

        return $params;
    }

    public function updateThePrivacyNoticeDate()
    {
        $lastModified = $this->grabFromDatabase('fs_content', 'last_mod', ['name' => 'datenschutzbelehrung']);
        $beforeLastModified = date('Y-m-d H:i:s', strtotime('+1 day', strtotime((string)$lastModified)));
        $this->updateInDatabase('fs_content', ['last_mod' => $beforeLastModified], ['name' => 'datenschutzbelehrung']);

        return $lastModified;
    }

    public function resetThePrivacyNoticeDate($lastModified): void
    {
        $this->updateInDatabase('fs_content', ['last_mod' => $lastModified], ['name' => 'datenschutzbelehrung']);
    }

    public function updateThePrivacyPolicyDate()
    {
        $lastModified = $this->grabFromDatabase('fs_content', 'last_mod', ['name' => 'datenschutz']);
        $beforeLastModified = date('Y-m-d H:i:s', strtotime('+1 day', strtotime((string)$lastModified)));
        $this->updateInDatabase('fs_content', ['last_mod' => $beforeLastModified], ['name' => 'datenschutz']);

        return $lastModified;
    }

    public function resetThePrivacyPolicyDate($lastModified): void
    {
        $this->updateInDatabase('fs_content', ['last_mod' => $lastModified], ['name' => 'datenschutz']);
    }

    public function createPoll(int $regionId, int $authorId, array $extraParams = []): array
    {
        $params = array_merge([
            'name' => $this->faker->text(30),
            'description' => $this->faker->realText(500),
            'scope' => VotingScope::FOODSAVERS,
            'type' => $this->faker->randomElement(range(VotingType::SELECT_ONE_CHOICE, VotingType::THUMB_VOTING)),
            'start' => $this->faker->dateTimeBetween('-7 days', 'now')->format('Y-m-d H:i:s'),
            'end' => $this->faker->dateTimeBetween('now', '+7 days')->format('Y-m-d H:i:s'),
            'votes' => $this->faker->numberBetween(0, 1000),
            'eligible_votes_count' => 0,
            'creation_timestamp' => $this->faker->dateTimeBetween('-7 days', 'now')->format('Y-m-d H:i:s'),
            'notifications_sent' => VotingNotificationType::NONE,
        ], $extraParams);
        $params['author'] = $authorId;
        $params['region_id'] = $regionId;
        $params['id'] = $this->haveInDatabase('fs_poll', $params);

        return $params;
    }

    public function createPollOption(int $pollId, array $values, array $extraParams = [])
    {
        $params = array_merge([
            'option_text' => $this->faker->text(30)
        ], $extraParams);
        $params['poll_id'] = $pollId;
        $params['option'] = $this->countInDatabase('fs_poll_has_options', ['poll_id' => $pollId]);

        $this->haveInDatabase('fs_poll_has_options', $params);

        foreach ($values as $value) {
            $this->haveInDatabase('fs_poll_option_has_value', [
                'poll_id' => $pollId,
                'option' => $params['option'],
                'value' => (int)$value,
                'votes' => $this->faker->numberBetween(0, 100)
            ]);
        }

        return $params;
    }

    public function addVoters(int $pollId, array $userIds): void
    {
        foreach ($userIds as $id) {
            $this->haveInDatabase('fs_foodsaver_has_poll', [
                'poll_id' => $pollId,
                'foodsaver_id' => $id,
                'time' => null
            ]);
        }

        $previousValue = $this->grabFromDatabase('fs_poll', 'eligible_votes_count', ['id' => $pollId]);
        $this->updateInDatabase('fs_poll', [
            'eligible_votes_count' => $previousValue + count($userIds)
        ]);
    }

    public function giveBanana(int $senderId, int $recipientId, string $message = null): void
    {
        if (empty($message)) {
            $message = $this->createRandomText(100, 300);
        }

        $this->haveInDatabase('fs_rating', [
            'foodsaver_id' => $recipientId,
            'rater_id' => $senderId,
            'msg' => $message,
            'time' => $this->faker->dateTime($max = 'now')->format('Y-m-d H:i:s')
        ]);
    }

    public function createDistrictPickupRule(int $region, $timeframe, $maxPickup, $maxPickupDay, $ignoreHours): void
    {
        $this->haveInDatabase('fs_region_options', ['region_id' => $region, 'option_type' => RegionOptionType::REGION_PICKUP_RULE_ACTIVE, 'option_value' => '1']);
        $this->haveInDatabase('fs_region_options', ['region_id' => $region, 'option_type' => RegionOptionType::REGION_PICKUP_RULE_TIMESPAN_DAYS, 'option_value' => $timeframe]);
        $this->haveInDatabase('fs_region_options', ['region_id' => $region, 'option_type' => RegionOptionType::REGION_PICKUP_RULE_LIMIT_NUMBER, 'option_value' => $maxPickup]);
        $this->haveInDatabase('fs_region_options', ['region_id' => $region, 'option_type' => RegionOptionType::REGION_PICKUP_RULE_LIMIT_DAY_NUMBER, 'option_value' => $maxPickupDay]);
        $this->haveInDatabase('fs_region_options', ['region_id' => $region, 'option_type' => RegionOptionType::REGION_PICKUP_RULE_INACTIVE_HOURS, 'option_value' => $ignoreHours]);
    }

    public function createContent(): array
    {
        $title = $this->faker->title;
        $content = [
            'name' => strtolower(str_replace(' ', '_', $title)),
            'title' => $title,
            'body' => $this->faker->text,
            'last_mod' => $this->faker->dateTimeBetween('-5 years', '-5 days')->format('Y-m-d H:i:s'),
        ];

        $id = $this->haveInDatabase('fs_content', $content);
        $content['id'] = $id;

        return $content;
    }

    public function createUpload(int $userId, int $usageId = null, int $usageType = null, array $extraParams = []): array
    {
        $params = array_merge([
            'uuid' => $this->uploadUUID(),
            'user_id' => $userId,
            'sha256hash' => $this->faker->sha256(),
            'mimetype' => $this->faker->mimeType(),
            'uploaded_at' => $this->faker->dateTimeBetween('-5 years', '-1 week')->format('Y-m-d H:i:s'),
            'filesize' => $this->faker->numberBetween(0, 1_500_000),
            'used_in' => $usageId,
            'usage_id' => $usageType,
        ], $extraParams);

        $this->haveInDatabase('uploads', $params);

        return $this->grabEntryFromDatabase('uploads', ['uuid' => $params['uuid']]);
    }

    // =================================================================================================================
    // private methods
    // =================================================================================================================

    private function updateForumThreadWithPost($threadId, $post): void
    {
        $last_post_id = $this->grabFromDatabase('fs_theme', 'last_post_id', ['id' => $threadId]);
        $last_post_date = new DateTime($this->grabFromDatabase('fs_theme_post', 'time', ['id' => $last_post_id]));
        $this_post_date = new DateTime($post['time']);
        if ($last_post_date >= $this_post_date) {
            $this->_getDriver()->executeQuery('UPDATE fs_theme SET last_post_id = ? WHERE id = ?', [$post['id'], $threadId]);
        }
    }

    // copied from elsewhere....
    private function encryptMd5($email, $pass)
    {
        $email = strtolower((string)$email);

        return md5($email . '-lz%&lk4-' . $pass);
    }

    /**
     * Converts a DateTime to an date with time as string "Y-m-d H:i:s" with timezone 'Europe/Berlin'.
     */
    private function toDateTime($date = null): ?string
    {
        if ($date === null) {
            return null;
        }
        if ($date instanceof DateTime) {
            $dt = $date;
        } else {
            $dt = new DateTime($date, new DateTimeZone('Europe/Berlin'));
        }

        return $dt->format('Y-m-d H:i:s');
    }

    /**
     * Converts a DateTime to an date as string "Y-m-d" with timezone 'Europe/Berlin'.
     */
    private function toDate($date = null): ?string
    {
        if ($date === null) {
            return null;
        }
        if ($date instanceof DateTime) {
            $dt = $date;
        } else {
            $dt = new DateTime($date, new DateTimeZone('Europe/Berlin'));
        }

        return $dt->format('Y-m-d');
    }

    private function createRandomText(int $minLength, int $maxLength): string
    {
        $text = $this->faker->realText($maxLength);
        while (strlen((string)$text) < $minLength) {
            $text .= ' ' . $this->faker->realText(($maxLength + $minLength) / 2 - strlen((string)$text));
        }

        return $text;
    }

    /**
     * Replaces German umlauts and the Eszett (ß) in a given string with their
     * respective ASCII representations.
     *
     * @param string $text the input string containing umlauts or Eszett
     *
     * @return string the modified string with umlauts and Eszett replaced
     */
    private function replaceUmlauts(string $text): string
    {
        return str_replace(
            ['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß', 'ẞ'],
            ['ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue', 'ss', 'SS'],
            $text
        );
    }

    /**
     * Generates a random UUID (Universally Unique Identifier) version 4.
     *
     * This method creates a UUID using random numbers to conform to the
     * version 4 UUID specification. The UUID is formatted as a string
     * in the standard 8-4-4-4-12 hexadecimal representation.
     *
     * @return string a randomly generated UUID version 4
     */
    private function uploadUUID(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF),

            // 16 bits for "time_mid"
            mt_rand(0, 0xFFFF),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0FFF) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3FFF) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF)
        );
    }

    public function addResourceCategory(string $name): int
    {
        return $this->haveInDatabase('fs_resource_category', ['name' => $name]);
    }

    public function addResource(int $foodsaverId, string $name, ?string $description, array $categories, bool $isPrivate, int $openness): int
    {
        $id = $this->haveInDatabase('fs_resource', [
            'foodsaver_id' => $foodsaverId,
            'name' => $name,
            'description' => $description,
            'is_private' => $isPrivate,
            'openness' => $openness,
        ]);
        foreach ($categories as $category) {
            $this->haveInDatabase('fs_resource_has_category', ['resource_id' => $id, 'category_id' => $category]);
        }

        return $id;
    }
}
