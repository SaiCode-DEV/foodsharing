<?php

namespace Foodsharing\Dev;

use Carbon\Carbon;
use Codeception\Command\Shared\ConfigTrait;
use Codeception\CustomCommandInterface;
use Codeception\Lib\Di;
use Codeception\Lib\ModuleContainer;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Core\DBConstants\Store\CooperationStatus;
use Foodsharing\Modules\Core\DBConstants\Store\StoreLogAction;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Core\DBConstants\Voting\VotingScope;
use Foodsharing\Modules\Core\DBConstants\Voting\VotingType;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tests\Support\Helper\Foodsharing;

// this is Codeception specific, so we can't use the regular AsCommand attribute
class SeedCommand extends Command implements CustomCommandInterface
{
    use ConfigTrait;
    protected static $defaultDescription = 'Seed the dev db.';

    protected Foodsharing $helper;

    protected OutputInterface $output;

    protected $foodsavers = [];
    protected $reportAdmins = [];
    protected $arbitrationAdmins = [];

    public static function getCommandName(): string
    {
        return 'foodsharing:seed';
    }

    protected function configure(): void
    {
        $this->setHelp('This commands adds seed data to the database. The general rule is that before running this command, you have a working instance of foodsharing without customized data (e.g. missing regions, quizzes, ...) but already including all the data that is directly used in the code (so you will not get any internal server errors). The future goal is, to make the code as much independent of data as possible and move all data you may want to playing around into the seed.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;

        $config = $this->getGlobalConfig();
        $di = new Di();
        $module = new ModuleContainer($di, $config);
        $this->helper = $module->create(Foodsharing::class);
        $this->helper->_initialize();

        // Clear existing data to prevent collisions
        $this->output->writeln('Clearing existing ' . FS_ENV . ' seed data');
        $this->helper->clear();

        $this->output->writeln('Seeding ' . FS_ENV . ' database');

        // Print how long database seeding took
        $start = microtime(true);
        $this->seed();
        $this->output->writeln('Database seeding took ' . round(microtime(true) - $start, 2) . ' seconds');

        return Command::SUCCESS;
    }

    /**
     * Retrieves one or multiple random elements from the given array.
     *
     * @param array $value the array from which to select random elements
     * @param int $number The number of random elements to retrieve. Defaults to 1.
     * @return mixed returns a single random element if $number is 1,
     *               an associative array of random elements if $number > 1
     */
    protected function getRandomIDOfArray(array $value, $number = 1)
    {
        if ($number === 1) {
            return $value[array_rand($value)];
        }

        return array_intersect_key($value, array_flip(array_rand($value, $number)));
    }

    /**
     * Retrieves a random subset of keys and their corresponding values from the given array,
     * removes them from the original array, and returns the subset.
     *
     * @param array $value the input array from which random elements will be selected and removed
     * @param int $number The number of random elements to retrieve. Defaults to 1.
     *
     * @return array an associative array containing the randomly selected keys and their values
     */
    protected function getRandomIDOfArrayAndDelete(array &$value, $number = 1)
    {
        $values = $this->getRandomIDOfArray($value, $number);

        foreach ($values as $i => $_) {
            unset($value[$i]);
        }

        return $values;
    }

    protected function createEngagementsStat(int $region1, $eventid = 0)
    {
        $I = $this->helper;
        $password = 'user';
        $user = $I->createStoreCoordinator($password, ['email' => 'userengagement@example.com', 'bezirk_id' => $region1, 'image' => true]);
        $I->addRegionMember($region1, $user['id']);

        $I->createEvents($region1, $user['id']);

        $store = $I->createStore($region1, null, null, ['betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value, 'betrieb_kategorie_id' => '1']);
        $I->addStoreTeam($store['id'], $user['id'], true);
        $I->addRecurringPickup($store['id']);
        for ($i = 1; $i < 3; ++$i) {
            $this->helper->addCollector($user['id'], $store['id'], ['date' => Carbon::today()->add('minute', 60 * $i)->toDateTimeString()]);
        }

        $storeEinAb = $I->createStore($region1, null, null, ['betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value, 'betrieb_kategorie_id' => '6', 'abholmenge' => '0']);
        $I->addStoreTeam($storeEinAb['id'], $user['id'], false);
        $this->helper->addCollector($user['id'], $storeEinAb['id'], ['date' => Carbon::now()->toDateTimeString()]);

        $storePr = $I->createStore($region1, null, null, ['betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value, 'betrieb_kategorie_id' => '11', 'abholmenge' => '0']);
        $I->addStoreTeam($storePr['id'], $user['id'], false);
        $this->helper->addCollector($user['id'], $storePr['id'], ['date' => Carbon::now()->toDateTimeString()]);

        $storeSuper = $I->createStore($region1, null, null, ['betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value, 'betrieb_kategorie_id' => '14']);
        $I->addStoreTeam($storeSuper['id'], $user['id'], true);
        for ($i = 1; $i < 3; ++$i) {
            $this->helper->addCollector($user['id'], $storeSuper['id'], ['date' => Carbon::today()->add('minute', 50 * $i)->toDateTimeString()]);
        }

        $storeBacery = $I->createStore($region1, null, null, ['betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value, 'betrieb_kategorie_id' => '1', 'abholmenge' => '1']);
        $I->addStoreTeam($storeBacery['id'], $user['id'], false);
        $this->helper->addCollector($user['id'], $storeBacery['id'], ['date' => Carbon::now()->toDateTimeString()]);

        $storeBioBakery = $I->createStore($region1, null, null, ['betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value, 'betrieb_kategorie_id' => '2', 'abholmenge' => '1']);
        $I->addStoreTeam($storeBioBakery['id'], $user['id'], false);
        $this->helper->addCollector($user['id'], $storeBioBakery['id'], ['date' => Carbon::now()->toDateTimeString()]);

        $storeBioGrocery = $I->createStore($region1, null, null, ['betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value, 'betrieb_kategorie_id' => '3', 'abholmenge' => '1']);
        $I->addStoreTeam($storeBioGrocery['id'], $user['id'], false);
        $this->helper->addCollector($user['id'], $storeBioGrocery['id'], ['date' => Carbon::now()->toDateTimeString()]);

        $storeDrink = $I->createStore($region1, null, null, ['betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value, 'betrieb_kategorie_id' => '4', 'abholmenge' => '1']);
        $I->addStoreTeam($storeDrink['id'], $user['id'], false);
        for ($i = 1; $i < 3; ++$i) {
            $this->helper->addCollector($user['id'], $storeDrink['id'], ['date' => Carbon::today()->add('minute', 50 * $i)->toDateTimeString()]);
        }

        for ($i = 0; $i < 3; ++$i) {
            $I->createFoodbasket($user['id'], ['time' => Carbon::now()->toDateString()]);
        }

        for ($i = 0; $i < 5; ++$i) {
            $I->createFoodbasket($user['id'], ['time' => Carbon::now()->addWeek()->toDateString()]);
        }

        for ($i = 0; $i < 1; ++$i) {
            $I->createFoodbasket($user['id'], ['time' => Carbon::now()->addWeek()->addWeek()->toDateString()]);
        }

        for ($i = 0; $i < 2; ++$i) {
            $I->createFoodbasket($user['id'], ['time' => Carbon::now()->subWeek()->toDateString()]);
        }

        for ($i = 0; $i < 2; ++$i) {
            $I->createFoodbasket($user['id'], ['time' => Carbon::now()->subWeek()->subWeek()->toDateString()]);
        }

        if ($eventid > 0) {
            $I->addEventInvitation($eventid, $user['id'], ['status' => 1]);
        }
    }

    protected function createFunctionWorkgroups(int $region1, array $adminOfAll)
    {
        $I = $this->helper;
        $password = 'user';

        $workgroups = [
            ['name' => 'Begrüßung', 'function' => WorkgroupFunction::WELCOME, 'key' => 'welcome'],
            ['name' => 'Abstimmungen', 'function' => WorkgroupFunction::VOTING, 'key' => 'voting', 'addAdminsToRegion' => RegionIDs::VOTING_ADMIN_GROUP],
            ['name' => 'Fairteiler', 'function' => WorkgroupFunction::FSP, 'key' => 'fsp'],
            ['name' => 'Betriebskoordination', 'function' => WorkgroupFunction::STORES_COORDINATION, 'key' => 'stores'],
            ['name' => 'Meldungsbearbeitung', 'function' => WorkgroupFunction::REPORT, 'key' => 'report'],
            ['name' => 'Mediation', 'function' => WorkgroupFunction::MEDIATION, 'key' => 'mediation'],
            ['name' => 'Schiedsstelle', 'function' => WorkgroupFunction::ARBITRATION, 'key' => 'arbitration'],
            ['name' => 'Verwaltung', 'function' => WorkgroupFunction::FSMANAGEMENT, 'key' => 'fsManagement'],
            ['name' => 'Öffentlichkeitsarbeit', 'function' => WorkgroupFunction::PR, 'key' => 'pr'],
            ['name' => 'Moderation', 'function' => WorkgroupFunction::MODERATION, 'key' => 'moderation'],
            ['name' => 'Vorstand', 'function' => WorkgroupFunction::BOARD, 'key' => 'board'],
            ['name' => 'Wahlen', 'function' => WorkgroupFunction::ELECTION, 'key' => 'election', 'addAdminsToRegion' => RegionIDs::ELECTION_ADMIN_GROUP],
            ['name' => 'Ressourcen', 'function' => WorkgroupFunction::RESOURCES, 'key' => 'resources'],
        ];

        foreach ($workgroups as $idx => $wg) {
            $group = $I->createWorkingGroup($wg['name'] . ' Göttingen', [
                'parent_id' => $region1,
                'email' => preg_replace(['/ä/', '/ö/', '/ü/', '/ß/'], ['ae', 'oe', 'ue', 'ss'], strtolower($wg['name'])) . '.goettingen',
                'teaser' => 'Hier ist die AG ' . $wg['name'] . ' für unseren Bezirk',
            ]);
            $I->haveInDatabase('fs_region_function', [
                'region_id' => $group['id'],
                'function_id' => $wg['function'],
                'target_id' => $region1,
            ]);
            for ($i = 1; $i <= 3; ++$i) {
                $user = $I->createStoreCoordinator(
                    $password,
                    [
                        'email' => "user{$wg['key']}{$i}@example.com",
                        'bezirk_id' => $region1,
                        'image' => true,
                    ]
                );
                $I->addRegionMember($group['id'], $user['id']);
                $I->addRegionAdmin($group['id'], $user['id']);

                if (!empty($wg['addAdminsToRegion'])) {
                    $I->addRegionMember($wg['addAdminsToRegion'], $user['id']);
                }
                if (isset($this->{$wg['key'] . 'Admins'})) {
                    $this->{$wg['key'] . 'Admins'}[] = $user['id'];
                }
            }
            $I->addRegionAdmin($group['id'], $adminOfAll['id']);
            $this->progressBar($idx + 1, count($workgroups));
        }
    }

    protected function CreateMorePickups(array $stores)
    {
        for ($m = 0; $m <= 10; ++$m) {
            $store_id = $this->getRandomIDOfArray($stores);
            for ($i = 0; $i <= 10; ++$i) {
                $pickupDate = Carbon::create(2022, 4, random_int(1, 30), random_int(1, 24), random_int(1, 59));
                $maxFoodsavers = count($this->foodsavers) > 2 ? 2 : count($this->foodsavers);
                for ($k = 0; $k <= $maxFoodsavers; ++$k) {
                    $foodSaver_id = $this->getRandomIDOfArray($this->foodsavers);
                    $this->helper->addCollector($foodSaver_id, $store_id, ['date' => $pickupDate->toDateTimeString()]);
                }
                $this->progressBar(11 * $m + $i + 1, 121);
            }
        }
    }

    private function writeUser(Foodsharing $I, $user, $password, $name = 'user')
    {
        // Make sure that the user is a member of all parent regions of the home region
        if (!empty($user['bezirk_id'])) {
            $regionId = $I->grabFromDatabase('fs_bezirk', 'parent_id', ['id' => $user['bezirk_id']]);
            while ($regionId !== 0) {
                $I->addRegionMember($regionId, $user['id']);
                $regionId = $I->grabFromDatabase('fs_bezirk', 'parent_id', ['id' => $regionId]);
            }
        }

        $this->output->writeln('- created ' . $name . ' ' . $user['email'] . ' with password "' . $password . '"');
    }

    private function createStoreAndAddToTeam(
        Foodsharing $I,
        int $regionId,
        int $statusId,
        array $managerIds,
        array $teamMemberIds,
        array $waitingMemberIds,
        array $applicantIds = [],
        bool $addRecurringPickup = false,
    ): array {
        $conv1Id = $I->createConversation([], ['name' => 'betrieb_bla', 'locked' => 1])['id'];
        $conv2Id = $I->createConversation([], ['name' => 'springer_bla', 'locked' => 1])['id'];
        $store = $I->createStore($regionId, $conv1Id, $conv2Id, ['betrieb_status_id' => $statusId]);
        $storeId = $store['id'];

        foreach ($managerIds as $managerId) {
            $I->addStoreTeam($storeId, $managerId, true, false, true);
            $I->addConversationMessage($managerId, $conv1Id);
            $I->addConversationMessage($managerId, $conv2Id);
        }
        foreach ($teamMemberIds as $teamMemberId) {
            $I->addStoreTeam($storeId, $teamMemberId, false, false, true);
            $I->addConversationMessage($teamMemberId, $conv1Id);
        }
        foreach ($waitingMemberIds as $waitingMemberId) {
            $I->addStoreTeam($storeId, $waitingMemberId, false, true, true);
            $I->addConversationMessage($waitingMemberId, $conv2Id);
        }
        foreach ($applicantIds as $applicantId) {
            $I->addStoreTeam($storeId, $applicantId, false, false, false);
        }

        if ($addRecurringPickup) {
            $I->addRecurringPickup($storeId);
        }

        return $store;
    }

    protected function seed()
    {
        $I = $this->helper;
        $I->_getDbh()->beginTransaction();
        $I->_getDriver()->executeQuery('SET FOREIGN_KEY_CHECKS=1;', []);

        // Create base regions
        $this->output->writeln('Create base regions');
        $I->createRegion('Foodsharing auf Festivals', ['id' => RegionIDs::FOODSHARING_ON_FESTIVALS, 'parent_id' => RegionIDs::ROOT, 'type' => UnitType::CITY, 'has_children' => 0]);
        $regionEurope = $I->createRegion('Europa', ['id' => RegionIDs::EUROPE, 'parent_id' => RegionIDs::ROOT, 'type' => UnitType::COUNTRY, 'has_children' => 1]);
        $regionGermany = $I->createRegion('Deutschland', ['id' => RegionIDs::GERMANY, 'parent_id' => $regionEurope['id'], 'type' => UnitType::COUNTRY, 'has_children' => 1]);
        $I->createRegion('Schweiz', ['id' => RegionIDs::SWITZERLAND, 'parent_id' => $regionEurope['id'], 'type' => UnitType::COUNTRY, 'has_children' => 1]);
        $regionLowerSaxony = $I->createRegion('Niedersachsen', ['parent_id' => $regionGermany['id'], 'type' => UnitType::FEDERAL_STATE, 'has_children' => 1]);
        $regionOne = $I->createRegion('Göttingen', [
            'parent_id' => $regionLowerSaxony['id'],
            'type' => UnitType::CITY,
            'has_children' => 1,
            'email' => 'goettingen',
        ]);
        $region1 = $regionOne['id'];
        $regionTwo = $I->createRegion('Entenhausen', ['parent_id' => $regionLowerSaxony['id'], 'type' => UnitType::CITY, 'has_children' => 1]);
        $region2 = $regionTwo['id'];
        $regionOneWorkGroup = $I->createWorkingGroup('Schnippelparty Göttingen', ['parent_id' => $regionOne['id']]);
        $region_vorstand = RegionIDs::TEAM_BOARD_MEMBER;
        $ag_aktive = RegionIDs::TEAM_ADMINISTRATION_MEMBER;
        $ag_testimonials = RegionIDs::TEAM_BOARD_MEMBER;
        $team_alumni = RegionIDs::TEAM_ALUMNI_MEMBER;
        $ag_quiz = RegionIDs::QUIZ_AND_REGISTRATION_WORK_GROUP;
        $ag_new_quizzes = RegionIDs::NEW_QUIZZES_WORK_GROUP;
        $ag_quiz_fr = RegionIDs::QUIZ_GROUP_FR;
        $ag_startpage = RegionIDs::PR_START_PAGE;
        $ag_partnerandteam = RegionIDs::PR_PARTNER_AND_TEAM_WORK_GROUP;

        $this->output->writeln('Create working groups');
        $password = 'user';
        $region1WorkGroup = $regionOneWorkGroup['id']; // workgroup 'Schnippelparty Göttingen' from 'Göttingen'
        $I->createWorkingGroup('AG Anlegen', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::CREATING_WORK_GROUPS_WORK_GROUP]);
        $I->createWorkingGroup('Support', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::IT_SUPPORT_GROUP]);
        $I->createWorkingGroup('Öffentlichkeitsarbeit - Partner + Teamseite', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::PR_PARTNER_AND_TEAM_WORK_GROUP]);
        $I->createWorkingGroup('Öffentlichkeitsarbeit - Startseite', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::PR_START_PAGE]);
        $I->createWorkingGroup('Newsletter', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::NEWSLETTER_WORK_GROUP]);
        $I->createWorkingGroup('Orgarechte-Koordination', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::ORGA_COORDINATION_GROUP]);
        $I->createWorkingGroup('Redaktion', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::EDITORIAL_GROUP]);
        $I->createWorkingGroup('foodsharing Vereins-Vorstände', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::BOARD_ADMIN_GROUP]);
        $I->createWorkingGroup('Begrüßungsteam Praxisaustausch', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::WELCOME_TEAM_ADMIN_GROUP]);
        $I->createWorkingGroup('Abstimmungs-AG Praxisaustausch', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::VOTING_ADMIN_GROUP]);
        $I->createWorkingGroup('Wahlen-AG Praxisaustausch', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::ELECTION_ADMIN_GROUP]);
        $I->createWorkingGroup('Fairteiler-AG Praxisaustausch', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::FSP_TEAM_ADMIN_GROUP]);
        $I->createWorkingGroup('Betriebskoordination-AG Praxisaustausch', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::STORE_COORDINATION_TEAM_ADMIN_GROUP]);
        $I->createWorkingGroup('AG Betriebsketten', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::STORE_CHAIN_GROUP]);
        $I->createWorkingGroup('Betriebsketten Schweiz', ['parent_id' => RegionIDs::SWITZERLAND, 'id' => RegionIDs::STORE_CHAIN_GROUP_SWITZERLAND]);
        $I->createWorkingGroup('Hygiene', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::HYGIENE_GROUP]);
        $I->createWorkingGroup('PolKa', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::POLITICAL_CAMPAIGNS]);
        $I->createWorkingGroup('Quiz FR', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::QUIZ_GROUP_FR]); // actually in france, but for the seed data it's here...
        $I->createWorkingGroup('Meldungen-AG Praxisaustausch', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::REPORT_TEAM_ADMIN_GROUP]);
        $I->createWorkingGroup('Mediation-AG Praxisaustausch', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::MEDIATION_TEAM_ADMIN_GROUP]);
        $I->createWorkingGroup('Schiedsstelle-AG Praxisaustausch', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::ARBITRATION_TEAM_ADMIN_GROUP]);
        $I->createWorkingGroup('Verwaltung-AG Praxisaustausch', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::FSMANAGEMENT_TEAM_ADMIN_GROUP]);
        $I->createWorkingGroup('Öffentlichkeitsarbeit-AG Praxisaustausch', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::PR_TEAM_ADMIN_GROUP]);
        $I->createWorkingGroup('Moderation-AG Praxisaustausch', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::MODERATION_TEAM_ADMIN_GROUP]);
        $I->createWorkingGroup('Produktteam', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::PRODUCT_TEAM]);
        $I->createWorkingGroup('Oauth Client Administration', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::OAUTH_CLIENT_ADMINISTRATION_WORK_GROUP]);
        $I->createWorkingGroup('Quizfragen', ['parent_id' => RegionIDs::QUIZ_AND_REGISTRATION_WORK_GROUP, 'id' => RegionIDs::NEW_QUIZZES_WORK_GROUP]);

        $I->createRegion('Stadtteil von Göttingen', ['type' => UnitType::PART_OF_TOWN, 'parent_id' => $region1]);

        $this->output->writeln('Create achievements');
        $this->createAchievements($I);

        $this->output->writeln('Create store categories:');
        $I->createStoreCategories();
        // Create users
        $this->output->writeln('Create basic users:');
        $user1 = $I->createFoodsharer($password, ['email' => 'user1@example.com', 'name' => 'One']);
        $this->writeUser($I, $user1, $password, 'foodsharer');

        $userData = $this->loadJsonFromFile('userData.json');

        $user2 = $I->createFoodsaver($password,
            [
                'email' => 'user2@example.com',
                'name' => 'Two',
                'bezirk_id' => $region1,
                'about_me_public' => $userData['user2']['about_me_public'],
                'position' => $userData['user2']['position'],
                'image' => true
            ]);
        $this->writeUser($I, $user2, $password, 'foodsaver');

        $userStoreManager = $I->createStoreCoordinator($password, [
            'email' => 'storemanager1@example.com',
            'name' => 'Three',
            'bezirk_id' => $region1,
            'about_me_public' => $userData['userStoreManager']['about_me_public'],
            'position' => $userData['userStoreManager']['position'],
            'image' => true]
        );
        $this->writeUser($I, $userStoreManager, $password, 'store coordinator');

        $userStoreManager2 = $I->createStoreCoordinator($password, [
            'email' => 'storemanager2@example.com',
            'name' => 'Four',
            'bezirk_id' => $region1,
            'about_me_public' => $userData['userStoreManager2']['about_me_public'],
            'position' => $userData['userStoreManager2']['position'],
            'image' => true]
        );
        $this->writeUser($I, $userStoreManager2, $password, 'store coordinator2');

        $userbot = $I->createAmbassador($password, [
            'email' => 'userbot@example.com',
            'name' => 'Bot',
            'bezirk_id' => $region1,
            'about_me_intern' => 'hello!',
            'about_me_public' => $userData['userbot']['about_me_public'],
            'position' => $userData['userbot']['position'],
            'image' => true
        ]);
        $this->writeUser($I, $userbot, $password, 'ambassador');
        $I->addRegionMember($region2, $userbot['id']);

        $userbot2 = $I->createAmbassador($password, [
            'email' => 'userbot2@example.com',
            'name' => 'Bot2',
            'bezirk_id' => $region1,
            'about_me_intern' => 'hello!',
            'about_me_public' => $userData['userbot2']['about_me_public'],
            'position' => $userData['userbot2']['position'],
            'image' => true
        ]);
        $this->writeUser($I, $userbot2, $password, 'ambassador');

        // Create an ambassador whose profile is already deleted and cannot be used but who will show up in verification histories
        $userbotDeleted = $I->createAmbassador($password, [
            'email' => 'userbotdeleted@example.com',
            'name' => 'Bot3',
            'bezirk_id' => $region2,
            'about_me_intern' => 'hello!',
            'deleted_at' => Carbon::now()->subYear()
        ]);
        $this->writeUser($I, $userbotDeleted, $password, 'deleted ambassador');

        $userbotregion2 = $I->createAmbassador($password, [
            'email' => 'userbotreg2@example.com',
            'name' => 'Bot Entenhausen',
            'bezirk_id' => $region2,
            'about_me_intern' => 'hello!',
            'image' => true
        ]);
        $I->addRegionAdmin($region2, $userbotregion2['id']);
        $I->addRegionMember($region1, $userbotregion2['id']);

        $this->writeUser($I, $userbotregion2, $password, 'ambassador');

        $userorga = $I->createOrga($password, false, [
            'email' => 'userorga@example.com',
            'name' => 'Orga',
            'bezirk_id' => $region1,
            'about_me_intern' => 'hello!',
            'about_me_public' => $userData['userorga']['about_me_public'],
            'position' => $userData['userorga']['position'],
            'image' => true
        ]);
        $this->writeUser($I, $userorga, $password, 'orga');

        $userorgaWG = $I->createOrga($password, false, ['email' => 'userorgaWG@example.com', 'name' => 'OrgaWG', 'bezirk_id' => $region1, 'id' => RegionIDs::CREATING_WORK_GROUPS_WORK_GROUP, 'image' => true]);
        $this->writeUser($I, $userorgaWG, $password, 'orga');
        $I->addRegionAdmin(RegionIDs::CREATING_WORK_GROUPS_WORK_GROUP, $userorgaWG['id']);
        $I->addRegionMember(RegionIDs::CREATING_WORK_GROUPS_WORK_GROUP, $userorgaWG['id']);

        $userAuth = $I->createStoreCoordinator($password, ['email' => 'userauth@example.com', 'name' => 'OAuth', 'bezirk_id' => $region1, 'image' => true]);
        $this->writeUser($I, $userAuth, $password, 'store coordinator - OAUTH User');
        $I->addRegionAdmin(RegionIDs::OAUTH_CLIENT_ADMINISTRATION_WORK_GROUP, $userAuth['id']);
        $I->addRegionMember(RegionIDs::OAUTH_CLIENT_ADMINISTRATION_WORK_GROUP, $userAuth['id']);

        $this->output->writeln('- done');

        $this->output->writeln('Create some user interaction:');
        $this->output->writeln('- adding buddies to userbot');
        // Create confirmed buddy userbot <-> userorga
        $I->addBuddy($userbot['id'], $userorga['id']);
        // Create buddy request from userbot to userorgaWG
        $I->addBuddy($userbot['id'], $userorgaWG['id'], false);
        // Create buddy request from userbotregion2 to userbot
        $I->addBuddy($userbotregion2['id'], $userbot['id'], false);

        // Add users to region
        $this->output->writeln('- add users to region');
        $I->addRegionAdmin($region1, $userbot['id']);
        $I->addRegionAdmin($region1, $userbot2['id']);
        $I->addRegionMember($ag_quiz, $userbot['id']);
        $I->addRegionAdmin($ag_quiz, $userbot['id']);
        $I->addRegionMember($ag_new_quizzes, $userbot['id']);
        $I->addRegionMember($ag_new_quizzes, $userStoreManager['id']);
        $I->addRegionAdmin($ag_new_quizzes, $userbot['id']);
        $I->addRegionMember($ag_quiz_fr, $userbot['id']);
        $I->addRegionAdmin($ag_quiz_fr, $userbot['id']);
        $I->addRegionMember($ag_startpage, $userStoreManager['id']);
        $I->addRegionAdmin($ag_startpage, $userStoreManager['id']);
        $I->addRegionMember($ag_startpage, $userbot['id']);
        $I->addRegionAdmin($ag_startpage, $userbot['id']);
        $I->addRegionMember($ag_partnerandteam, $userStoreManager2['id']);
        $I->addRegionAdmin($ag_partnerandteam, $userStoreManager2['id']);
        $I->addRegionMember($ag_partnerandteam, $userbot['id']);
        $I->addRegionAdmin($ag_partnerandteam, $userbot['id']);
        $I->addRegionMember($region_vorstand, $userbot['id']);
        $I->addRegionMember($region_vorstand, $userorga['id']);
        $I->addRegionMember($region_vorstand, $userStoreManager['id']);
        $I->addRegionMember($region_vorstand, $userStoreManager2['id']);
        $I->addRegionMember($ag_aktive, $userbot['id']);
        $I->addRegionMember($ag_aktive, $userorga['id']);
        $I->addRegionMember($ag_aktive, $userStoreManager['id']);
        $I->addRegionMember($ag_aktive, $userStoreManager2['id']);
        $I->addRegionMember($team_alumni, $userbot2['id']);
        $I->addRegionMember($team_alumni, $userorga['id']);
        $I->addRegionMember($team_alumni, $userStoreManager['id']);
        $I->addRegionMember($team_alumni, $userStoreManager2['id']);

        $I->addRegionMember($ag_testimonials, $user2['id']);
        $I->addRegionMember(RegionIDs::STORE_CHAIN_GROUP, $user2['id']);
        $I->addRegionMember(RegionIDs::HYGIENE_GROUP, $user2['id']);
        $I->addRegionMember(RegionIDs::POLITICAL_CAMPAIGNS, $user2['id']);

        $I->addRegionAdmin(RegionIDs::IT_SUPPORT_GROUP, $userStoreManager2['id']);
        $I->addRegionMember(RegionIDs::IT_SUPPORT_GROUP, $userStoreManager2['id']);
        $I->addRegionAdmin(RegionIDs::IT_SUPPORT_GROUP, $userorga['id']);
        $I->addRegionMember(RegionIDs::IT_SUPPORT_GROUP, $userorga['id']);
        $I->addRegionAdmin(RegionIDs::NEWSLETTER_WORK_GROUP, $user2['id']);
        $I->addRegionAdmin(RegionIDs::EDITORIAL_GROUP, $userbot['id']);
        $I->addRegionAdmin(RegionIDs::STORE_CHAIN_GROUP, $userbot['id']);
        $I->addRegionAdmin(RegionIDs::HYGIENE_GROUP, $userbot['id']);
        $I->addRegionAdmin(RegionIDs::POLITICAL_CAMPAIGNS, $userbot['id']);
        $I->addRegionAdmin(RegionIDs::PRODUCT_TEAM, $userbot['id']);
        $I->addRegionAdmin(RegionIDs::PRODUCT_TEAM, $userorga['id']);

        // Make ambassador responsible for all work groups in the region
        $this->output->writeln('- make ambassador responsible for all work groups');
        $workGroupsIds = $I->grabColumnFromDatabase('fs_bezirk', 'id', ['parent_id' => $region1, 'type' => UnitType::WORKING_GROUP]);
        foreach ($workGroupsIds as $id) {
            $I->addRegionMember($id, $userbot['id']);
            $I->addRegionAdmin($id, $userbot['id']);
        }
        // create event
        $this->output->writeln('- create event');
        $event = $I->createEvents($region1, $userbot['id']);

        $this->output->writeln('- create engagement statistik user');
        $this->createEngagementsStat($region1, $event['id']);

        // create Community Pin
        $this->output->writeln('- create community pin');
        $I->createCommunityPin($region1, [
            'lat' => 51.5333,
            'lon' => 9.9354,
            'desc' => 'Willkommen auf der öffentlichen Bezirksseite von **foodsharing Göttingen**! Hier findest du alles, was du über unsere Initiative, Aktivitäten und Möglichkeiten zum Mitmachen wissen musst. Gemeinsam setzen wir uns für mehr Nachhaltigkeit und weniger Lebensmittelverschwendung ein.
---
### 🌟 Unsere Mission: Gemeinsam Lebensmittel retten!
Wir engagieren uns dafür, überschüssige Lebensmittel zu retten und sie vor der Tonne zu bewahren. In Göttingen arbeiten wir mit verschiedenen Betrieben, Initiativen und Ehrenamtlichen zusammen, um ein Umdenken in der Gesellschaft anzustoßen.
---
### 📆 Öffentliche Veranstaltungen
**Komm vorbei und mach mit!**
- **Lebensmittelretter-Treff**: Jeden 1. Mittwoch im Monat um 18:00 Uhr im Umweltzentrum Göttingen
- **Koch-Workshop**: "Rest(e)los genießen" am 15. Januar 2025
- **Infostand auf dem Wochenmarkt**: Jeden Samstag
Gemeinsam können wir einen Unterschied machen – für Göttingen und die Umwelt!',
        ]);

        // Create stores
        $this->output->writeln('- create store and add team members');

        $regions = [
            $region1 => [
                // array structure: [managers, members, waiting, applicants]
                CooperationStatus::COOPERATION_ESTABLISHED->value => [[$userStoreManager['id'], $userbot['id']], [$user2['id']], []],
                CooperationStatus::PERMANENTLY_CLOSED->value => [[$userbot['id']], [], []],
                CooperationStatus::GIVES_TO_OTHER_CHARITY->value => [[$userbot['id']], [], []],
                CooperationStatus::UNCLEAR->value => [[$userbot['id']], [], []],
            ],
            $region2 => [
                CooperationStatus::COOPERATION_ESTABLISHED->value => [[], [$userbot['id']], []],
                CooperationStatus::PERMANENTLY_CLOSED->value => [[$userbot['id']], [], []],
                CooperationStatus::GIVES_TO_OTHER_CHARITY->value => [[$userbot['id']], [], []],
                CooperationStatus::UNCLEAR->value => [[$userbot['id']], [], []],
            ],
        ];

        $possibleMemberStates = [
            ['isWaiting' => true, 'isConfirmed' => false],
            ['isWaiting' => false, 'isConfirmed' => true]
        ];
        foreach ($regions as $regionId => $statuses) {
            foreach ($statuses as $status => $userLists) {
                $addRecurringPickup = $status === CooperationStatus::COOPERATION_ESTABLISHED->value;
                $store = $this->createStoreAndAddToTeam($I, $regionId, $status, $userLists[0], $userLists[1], $userLists[2], addRecurringPickup: $addRecurringPickup);

                $additionalStoreCount = 2;
                for ($i = 0; $i < $additionalStoreCount; ++$i) {
                    $memberState = $possibleMemberStates[random_int(0, 1)];
                    $store = $this->createStoreAndAddToTeam($I, $regionId, $status, $userLists[0], $userLists[1], $userLists[2]);
                }
            }
        }

        $this->output->writeln('- create store chains');
        $chain_ids = [];
        foreach (range(1, 50) as $_) {
            $chain = $I->addStoreChain();
            $chain_ids[] = $chain['id'];
            $this->progressBar($_, 50);
        }
        $I->addKamToStoreChain($chain_ids[0], $userbot['id']);
        $this->output->writeln('');

        $this->output->writeln('- create food types');
        foreach (range(1, 10) as $_) {
            $I->addStoreFoodType();
            $this->progressBar($_, 10);
        }
        $this->output->writeln('');

        // Forum theads and posts
        $this->output->writeln('- create forum threads and posts');
        $thread = $I->addForumThread($region1, $userbot['id']);
        $I->addForumThreadPost($thread['id'], $user2['id']);
        $thread = $I->addForumThread($region1, $user2['id']);
        $I->addForumThreadPost($thread['id'], $user1['id']);
        $thread = $I->addForumThread($region1, $user1['id']);
        $I->addForumThreadPost($thread['id'], $userorga['id']);

        $this->output->writeln('- follow a food share point');
        $foodSharePoint = $I->createFoodSharePoint($userbot['id'], $region1);
        $I->addFoodSharePointFollower($user2['id'], $foodSharePoint['id']);
        $I->addFoodSharePointPost($userbot['id'], $foodSharePoint['id']);

        // create users and collect their ids in a list
        $this->output->writeln('Create some more users');
        $this->foodsavers = array_column([$user2, $userbot, $userorga, $userbot2, $userStoreManager, $userStoreManager2], 'id');
        foreach ($this->foodsavers as $user) {
            $this->addVerificationAndPassHistory($I, $user, $userbotDeleted['id'], 13);
            $this->addVerificationAndPassHistory($I, $user, $userbot['id']);
        }
        foreach (range(1, 50) as $_) {
            $user = $I->createFoodsaver($password, ['bezirk_id' => $region1, 'image' => true]);
            $this->foodsavers[] = $user['id'];
            $I->addStoreTeam($store['id'], $user['id']);
            $I->addCollector($user['id'], $store['id']);
            $I->addStoreNotiz($user['id'], $store['id']);
            $I->addForumThreadPost($thread['id'], $user['id']);
            $this->addVerificationAndPassHistory($I, $user['id'], $userbotDeleted['id'], 13);
            $this->addVerificationAndPassHistory($I, $user['id'], $userbot['id']);
            $I->addEventInvitation($event['id'], $user['id']);
            $this->progressBar($_, 50);
        }
        $this->output->writeln('');
        $this->output->writeln('- Create old users');
        foreach (range(1, 20) as $_) {
            $I->createFoodsaver($password, ['bezirk_id' => $region1, 'last_login' => Carbon::now()->subyears(6)]);
            $this->progressBar($_, 20);
        }
        $this->output->writeln('');
        $this->output->writeln('Create old users with no_automatic_delete flag');
        foreach (range(1, 20) as $_) {
            $I->createFoodsaver($password, ['bezirk_id' => $region1, 'last_login' => Carbon::now()->subyears(6), 'no_automatic_delete' => 1]);
            $this->progressBar($_, 20);
        }
        $this->output->writeln('');

        $this->output->writeln('Creating resources');
        $this->createResources();
        $this->output->writeln('done');

        // give some trust bananas
        $this->output->writeln('Give some trust bananas');
        foreach ($this->foodsavers as $i => $recipient) {
            foreach ($this->getRandomIDOfArray($this->foodsavers, 2) as $sender) {
                $I->giveBanana($sender, $recipient);
            }
            $this->progressBar($i + 1, count($this->foodsavers));
        }
        $this->output->writeln('');

        // create conversations between users
        $this->output->writeln('Create conversations between users');
        foreach ($this->foodsavers as $j => $user) {
            foreach ($this->getRandomIDOfArray($this->foodsavers, 10) as $chatpartner) {
                if ($user !== $chatpartner) {
                    $conv = $I->createConversation([$user, $chatpartner]);
                    for ($i = 1; $i <= random_int(1, 10); ++$i) {
                        $userId = $user;
                        if (random_int(0, 1)) {
                            $userId = $chatpartner;
                        }
                        $I->addConversationMessage($userId, $conv['id']);
                    }
                }
            }
            $this->progressBar($j + 1, count($this->foodsavers));
        }
        $this->output->writeln('');

        // Create more Forum Threads
        $count = 20;
        $this->output->writeln('- Create more forum Threads');
        $randomFsList = $this->getRandomIDOfArray($this->foodsavers, $count);
        $i = 0;
        foreach ($randomFsList as $random_user) {
            foreach (range(1, 5) as $_) {
                $I->addForumThread($region1, $random_user);
            }
            $this->progressBar(++$i, $count);
        }
        $this->output->writeln('');

        // add some users to a workgroup
        $this->output->writeln('Add users to workgroup');
        // but only the ones we generated above
        $i = 0;
        foreach ($randomFsList as $random_user) {
            $I->addRegionMember($region1WorkGroup, $random_user);
            $this->progressBar(++$i, $count);
        }
        $this->output->writeln('');

        $this->output->writeln('- creating special working groups');
        $this->createFunctionWorkgroups($region1, $userbot2);
        $this->output->writeln('');

        // create more stores and collect their ids in a list
        $this->output->writeln('Create some stores');
        $stores = [$store['id']];
        foreach (range(1, 40) as $_) {
            // TODO conversations are missing the other store members
            $extra_params = [];
            if (random_int(0, 1) == 1) {
                $extra_params['kette_id'] = $chain_ids[random_int(0, 10)];
            }

            $store = $I->createStore($region1, null, null, $extra_params);
            $I->addUserToConversation($userbot['id'], $store['team_conversation_id']);
            $I->addUserToConversation($userbot['id'], $store['springer_conversation_id']);

            foreach (range(0, 5) as $__) {
                $I->addRecurringPickup($store['id']);
            }
            $stores[] = $store['id'];
            $this->progressBar($_, 40);
        }
        $this->output->writeln('');

        // Create a special store for screenshots for onboarding
        $this->output->writeln('Create a special store for screenshots for onboarding');

        $foodsavers = $this->foodsavers;
        $managers = [$userStoreManager['id'], $userbot['id']];
        unset($foodsavers[array_search($userStoreManager['id'], $foodsavers)]);
        unset($foodsavers[array_search($userbot['id'], $foodsavers)]);

        $members = $this->getRandomIDOfArrayAndDelete($foodsavers, 20);
        $jumpers = $this->getRandomIDOfArrayAndDelete($foodsavers, 2);
        $applied = $this->getRandomIDOfArrayAndDelete($foodsavers, 2);

        $I->awardAchievement($this->getRandomIDOfArray($members, 15), 4, null);

        $extra_params = [];
        $extra_params['kette_id'] = $chain_ids[0];
        $extra_params['name'] = 'Schulungsbetrieb Onboarding';
        $extra_params['betrieb_status_id'] = CooperationStatus::COOPERATION_ESTABLISHED->value;

        $store = $this->createStoreAndAddToTeam($I, $region1, CooperationStatus::COOPERATION_ESTABLISHED->value, $managers, $members, $jumpers, $applied);

        $appliedDate = Carbon::now()->addDays(-3);
        foreach ($applied as $applicant) {
            $I->addStoreLog($store['id'], $applicant, $applicant, StoreLogAction::REQUEST_TO_JOIN, ['content' => $I->faker->realText(100), 'date_reference' => $appliedDate, 'date_activity' => $appliedDate]);
        }

        // add regular pickups in fs_abholzeiten
        $extra_params = [];
        $extra_params['betrieb_id'] = $store['id'];
        $extra_params['time'] = sprintf('%02d:%ss:00', 20, 0);
        $extra_params['fetcher'] = 2;

        foreach (range(0, 6) as $__) {
            $extra_params['dow'] = $__;
            $I->addRecurringPickup($store['id'], $extra_params);
        }

        // add confirmed pickups
        $pickup = Carbon::now()->settime(20, 0);
        $pickup = $pickup->addDay(-6);
        foreach (range(0, 8) as $__) {
            $pickup = $pickup->addDay(+1);
            $this->helper->addCollector($this->getRandomIDOfArray($this->foodsavers), $store['id'], ['date' => $pickup->toDayDateTimeString(), 'confirmed' => 1]);
            $this->helper->addCollector($this->getRandomIDOfArray($this->foodsavers), $store['id'], ['date' => $pickup->toDayDateTimeString(), 'confirmed' => 1]);
        }
        // add some unconfirmed pickups
        $pickup = $pickup->addDay(+1);
        $this->helper->addCollector($this->getRandomIDOfArray($this->foodsavers), $store['id'], ['date' => $pickup->toDayDateTimeString(), 'confirmed' => 0]);
        $this->helper->addCollector($this->getRandomIDOfArray($this->foodsavers), $store['id'], ['date' => $pickup->toDayDateTimeString(), 'confirmed' => 0]);
        $pickup = $pickup->addDay(+1);
        $this->helper->addCollector($this->getRandomIDOfArray($this->foodsavers), $store['id'], ['date' => $pickup->toDayDateTimeString(), 'confirmed' => 0]);

        $stores[] = $store['id'];
        $this->output->writeln('created Schulungsbetrieb Onboarding with id ' . $store['id']);

        // create pickups
        $this->output->writeln('Create more pickups');
        $this->CreateMorePickups($stores);
        $this->output->writeln('');

        // create foodbaskets
        $this->output->writeln('Create foodbaskets');
        $count = 50;
        foreach (range(1, $count) as $_) {
            $user = $this->getRandomIDOfArray($this->foodsavers);
            $I->createFoodbasket($user);
            $this->progressBar($_, 2 * $count);
        }

        // Create food baskets around userbot
        foreach (range(1, $count) as $_) {
            // Calculate a random location with exact distance but random
            // direction from userbot
            [$lat, $lon] = $I->getPointAtDistance($userbot['lat'], $userbot['lon'], $_ + 1, -1);

            $user = $this->getRandomIDOfArray($this->foodsavers);
            $I->createFoodbasket($user, ['lat' => $lat, 'lon' => $lon]);
            $this->progressBar($count + $_, 2 * $count);
        }
        $this->output->writeln('');

        // create food share point
        $i = 0;
        $count = 50;
        $this->output->writeln('Create food share points');
        foreach ($this->getRandomIDOfArray($this->foodsavers, $count) as $user) {
            $foodSharePoint = $I->createFoodSharePoint($user, $region1);
            foreach ($this->getRandomIDOfArray($this->foodsavers, 10) as $follower) {
                if ($user !== $follower) {
                    $I->addFoodSharePointFollower($follower, $foodSharePoint['id']);
                }
                $I->addFoodSharePointPost($follower, $foodSharePoint['id']);
            }
            $this->progressBar(++$i, $count);
        }
        $this->output->writeln('');

        $this->output->writeln('Create blog posts');
        foreach (range(1, 20) as $_) {
            $I->addBlogPost($userbot['id'], $region1);
            $this->progressBar($_, 20);
        }
        $this->output->writeln('');

        $this->output->writeln('Create reports');
        $I->addReport($this->getRandomIDOfArray($this->reportAdmins), $this->getRandomIDOfArray($this->foodsavers), 0, 0);
        $this->progressBar(1, 7);
        $I->addReport($this->getRandomIDOfArray($this->foodsavers), $this->getRandomIDOfArray($this->reportAdmins), 0, 0);
        $this->progressBar(2, 7);
        $I->addReport($this->getRandomIDOfArray($this->arbitrationAdmins), $this->getRandomIDOfArray($this->foodsavers), 0, 0);
        $this->progressBar(3, 7);
        $I->addReport($this->getRandomIDOfArray($this->foodsavers), $this->getRandomIDOfArray($this->arbitrationAdmins), 0, 0);
        $this->progressBar(4, 7);
        $I->addReport($this->getRandomIDOfArray($this->reportAdmins), $this->getRandomIDOfArray($this->arbitrationAdmins), 0, 0);
        $this->progressBar(5, 7);
        $I->addReport($this->getRandomIDOfArray($this->arbitrationAdmins), $this->getRandomIDOfArray($this->reportAdmins), 0, 0);
        $this->progressBar(6, 7);
        $I->addReport($this->getRandomIDOfArray($this->foodsavers), $this->getRandomIDOfArray($this->foodsavers), 0, 0);
        $this->progressBar(7, 7);
        $this->output->writeln('');

        $this->output->writeln('Create polls');
        $pollTypes = [
            VotingType::SELECT_ONE_CHOICE, VotingType::SELECT_MULTIPLE, VotingType::THUMB_VOTING,
            VotingType::SCORE_VOTING
        ];
        foreach ($pollTypes as $i => $type) {
            $this->createPoll(
                $region1,
                $userbot['id'],
                $type,
                [$user2['id'], $userStoreManager['id'], $userStoreManager2['id'], $userbot['id'], $userorga['id']]
            );
            $this->progressBar($i + 1, count($pollTypes));
        }
        $this->output->writeln('');

        $this->output->writeln('Create more one choice polls');
        foreach (range(1, 30) as $_) {
            $startDate = Carbon::now()->subDays(random_int(7, 3 * 365));
            $type = random_int(VotingType::SELECT_ONE_CHOICE, VotingType::SCORE_VOTING);
            $this->createPoll($region1, $userbot['id'], $type,
                [$user2['id'], $userStoreManager['id'], $userStoreManager2['id'], $userbot['id'], $userorga['id']],
                $startDate, $startDate->addDays(6)
            );
            $this->progressBar($_, 30);
        }
        $this->output->writeln('');

        $this->output->write('Create blacklisted emails');
        $I->createBlacklistedEmailAddress();
        $this->output->writeln(' - done');

        $this->output->writeln('Enable feature toggles');
        $this->activeFeatureToggles();
        $this->output->writeln('done');

        $this->output->writeln('Fix missing bits');
        // Add mailboxes with the correct mailbox IDs (as defined in initial_migration.php line 6727 onwards)
        $I->createMailbox('arbeitsgruppen.ueberregional', true, false, 32678);
        $I->createMailbox('orgateam.archiv', true, false, 528);
        $I->createMailbox('europa', true, false, 25467);
        $I->createMailbox('vereinsvorstand', true, false, 26644);
        $I->createMailbox('ehemalige', true, false, 30177);
        $I->createMailbox('aktive', true, false, 30176);
        $I->createMailbox('anmeldevorgang.quiz', true, false, 19708);

        $this->output->writeln(' - mailbox added');

        $I->_getDbh()->commit();
    }

    /**
     * Activates a predefined list of feature toggles.
     */
    private function activeFeatureToggles(): void
    {
        $features = ['hygieneQuiz'];
        foreach ($features as $feature) {
            $this->output->writeln(' - ' . $feature);
            $this->helper->activateFeatureToggle($feature);
        }
    }

    private function createAchievements(Foodsharing $I): void
    {
        $achievementsData = $this->loadJsonFromFile('achievements.json');

        foreach ($achievementsData as $achievement) {
            $I->addAchievement($achievement);
        }
    }

    private function createPoll(int $regionId, int $authorId, int $type, array $voterIds,
        ?Carbon $startDate = null, ?Carbon $endDate = null)
    {
        $possibleValues = [];
        switch ($type) {
            case VotingType::SELECT_ONE_CHOICE:
            case VotingType::SELECT_MULTIPLE:
                $possibleValues = [1];
                break;
            case VotingType::THUMB_VOTING:
                $possibleValues = [1, 0, -1];
                break;
            case VotingType::SCORE_VOTING:
                $possibleValues = [3, 2, 1, 0, -1, -2, -3];
                break;
        }

        $params = ['type' => $type, 'scope' => VotingScope::FOODSAVERS];
        if (!is_null($startDate)) {
            $params['start'] = $startDate->format('Y-m-d H:i:s');
        }
        if (!is_null($endDate)) {
            $params['end'] = $endDate->format('Y-m-d H:i:s');
        }

        $poll = $this->helper->createPoll($regionId, $authorId, $params);
        foreach (range(0, 3) as $_) {
            $this->helper->createPollOption($poll['id'], $possibleValues);
        }
        $this->helper->addVoters($poll['id'], $voterIds);
    }

    /**
     * Adds some entries to the verification and pass history of a user. The verification history will be filled with
     * three entries at 1 month, 6 months, and 1 year. The offset parameter can be used to shift these entries by
     * some months into the past.
     *
     * @param int $userId the user to be verified
     * @param int $verifierId the ambassador who verified the user
     * @param int $monthsInPast number of months by which the verification entries will be shifted into the past
     */
    private function addVerificationAndPassHistory(Foodsharing $I, int $userId, int $verifierId, int $monthsInPast = 0)
    {
        $offset = Carbon::today()->subMonths($monthsInPast);
        $I->addVerificationHistory($userId, $verifierId, true, $offset->sub('1 year'));
        $I->addVerificationHistory($userId, $verifierId, false, $offset->sub('6 months'));
        $I->addVerificationHistory($userId, $verifierId, true, $offset->sub('1 month'));

        foreach (range(0, 3) as $_) {
            $I->addPassHistory($userId, $verifierId);
        }
    }

    /**
     * Writes a progress bar with a percentage text to the output.
     */
    private function progressBar(int $steps, int $total): void
    {
        static $lastUpdate = 0;
        $currentTime = microtime(true);

        // Update progress bar only every 0.1 seconds to reduce I/O overhead
        if ($currentTime - $lastUpdate >= 0.1 || $steps === $total) {
            $percentage = floor(($steps / $total) * 100);
            $left = 100 - $percentage;
            $write = sprintf("\033[0G\033[2K[%'={$percentage}s>%-{$left}s] {$steps} / {$total} ($percentage%%)", '', '');
            $this->output->write($write);
            $lastUpdate = $currentTime;
        }
    }

    private function createResources(): void
    {
        $resourcesData = $this->loadJsonFromFile('resources.json');
        $resourceCategoriesData = $this->loadJsonFromFile('resourceCategories.json');

        foreach ($resourceCategoriesData as $id => $name) {
            $categoryId = $this->helper->addResourceCategory($name);
            if ($id === 0) {
                $offset = $categoryId;
            }
        }

        $this->output->writeln('Offset: ' . $offset);

        shuffle($resourcesData);
        while (count($resourcesData) > 0) {
            $userId = $this->getRandomIDOfArray($this->foodsavers);
            $numToAssign = min(rand(1, 3), count($resourcesData));
            $resourcesToAssign = array_splice($resourcesData, 0, $numToAssign);
            foreach ($resourcesToAssign as $resource) {
                $this->helper->addResource(
                    $userId,
                    $resource['name'],
                    $resource['description'],
                    array_map(fn ($x) => $x + $offset, $resource['categories']),
                    rand(0, 9) === 0,
                    rand(1, 5),
                );
            }
        }
    }

    /**
     * Loads JSON data from the file and returns it as an array.
     *
     * @param string $filename the file path relative to this file
     * @param bool $isAssociative if the data is supposed to be loaded as an associative array
     * @throws RuntimeException if the file does not exist or if the JSON data is invalid
     */
    private function loadJsonFromFile(string $filename, bool $isAssociative = true): array
    {
        $file = 'src/Dev/' . $filename;
        if (!file_exists($file)) {
            throw new RuntimeException("File {$file} not found");
        }

        $data = json_decode(file_get_contents($file), $isAssociative);
        if (is_null($data)) {
            throw new RuntimeException("JSON data from file {$file} could not be decoded");
        }

        return $data;
    }
}
