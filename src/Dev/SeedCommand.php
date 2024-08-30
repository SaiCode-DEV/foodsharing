<?php

namespace Foodsharing\Dev;

use Carbon\Carbon;
use Codeception\Command\Shared\ConfigTrait;
use Codeception\CustomCommandInterface;
use Codeception\Lib\Di;
use Codeception\Lib\ModuleContainer;
use Foodsharing\Modules\Core\DBConstants\Quiz\QuizID;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Core\DBConstants\Store\CooperationStatus;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Core\DBConstants\Voting\VotingScope;
use Foodsharing\Modules\Core\DBConstants\Voting\VotingType;
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
    protected $welcomeAdmins = [];
    protected $votingAdmins = [];
    protected $fspAdmins = [];
    protected $reportAdmins = [];
    protected $arbitrationAdmins = [];
    protected $mediationAdmins = [];
    protected $fsManagementAdmins = [];
    protected $prAdmins = [];
    protected $moderationAdmins = [];
    protected $boardAdmins = [];
    protected $electionAdmins = [];

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
        $this->seed();

        return Command::SUCCESS;
    }

    protected function getRandomIDOfArray(array $value, $number = 1)
    {
        $rand = array_rand($value, $number);
        if ($number === 1) {
            return $value[$rand];
        }
        if (count($rand) > 0) {
            return array_intersect_key($value, $rand);
        }

        return [];
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

    protected function createFunctionWorkgroups(int $region1)
    {
        $I = $this->helper;
        $password = 'user';
        // Create a welcome Group
        $this->output->writeln('- create welcome group');
        $welcomeGroup = $I->createWorkingGroup('Begrüßung Göttingen', ['parent_id' => $region1, 'email_name' => 'Begruessung.Göttingen', 'teaser' => 'Hier sind die Begrüßer für unseren Bezirk']);
        $I->haveInDatabase('fs_region_function', ['region_id' => $welcomeGroup['id'], 'function_id' => WorkgroupFunction::WELCOME, 'target_id' => $region1]);
        foreach (range(1, 4) as $i) {
            $user = $I->createStoreCoordinator($password, ['email' => 'userwelcome' . $i . '@example.com', 'bezirk_id' => $region1, 'image' => true]);
            $I->addRegionMember($welcomeGroup['id'], $user['id']);
            $I->addRegionAdmin($welcomeGroup['id'], $user['id']);
            $this->welcomeAdmins[] = $user['id'];
        }
        $this->output->writeln(' done');

        // Create voting Group
        $this->output->writeln('- create voting group');
        $votingGroup = $I->createWorkingGroup('Abstimmungen Göttingen', ['parent_id' => $region1, 'email_name' => 'Abstimmung.Goettingen', 'teaser' => 'Hier sind die Abstimmungen für unseren Bezirk']);
        $I->haveInDatabase('fs_region_function', ['region_id' => $votingGroup['id'], 'function_id' => WorkgroupFunction::VOTING, 'target_id' => $region1]);
        foreach (range(1, 4) as $i) {
            $user = $I->createStoreCoordinator($password, ['email' => 'uservoting' . $i . '@example.com', 'bezirk_id' => $region1, 'image' => true]);
            $I->addRegionMember($votingGroup['id'], $user['id']);
            $I->addRegionAdmin($votingGroup['id'], $user['id']);
            $I->addRegionMember(RegionIDs::VOTING_ADMIN_GROUP, $user['id']);
            $this->votingAdmins[] = $user['id'];
        }
        $this->output->writeln(' done');

        // Create fsp Group
        $this->output->writeln('- create fsp group');
        $fspGroup = $I->createWorkingGroup('Fairteiler Göttingen', ['parent_id' => $region1, 'email_name' => 'Fairteiler.Goettingen', 'teaser' => 'Hier sind die Fairteileransprechpartner für unseren Bezirk']);
        $I->haveInDatabase('fs_region_function', ['region_id' => $fspGroup['id'], 'function_id' => WorkgroupFunction::FSP, 'target_id' => $region1]);
        foreach (range(1, 2) as $i) {
            $user = $I->createStoreCoordinator($password, ['email' => 'userfsp' . $i . '@example.com', 'bezirk_id' => $region1, 'image' => true]);
            $I->addRegionMember($fspGroup['id'], $user['id']);
            $I->addRegionAdmin($fspGroup['id'], $user['id']);
            $this->fspAdmins[] = $user['id'];
        }
        $this->output->writeln(' done');

        // Create STORESAdmins Group
        $this->output->writeln('- create store coordination group');
        $storesGroup = $I->createWorkingGroup('Betriebskoordination Göttingen', ['parent_id' => $region1, 'email_name' => 'betriebskoordination.Goettingen', 'teaser' => 'Hier sind die Betriebskoordinationsansprechpartner für unseren Bezirk']);
        $I->haveInDatabase('fs_region_function', ['region_id' => $storesGroup['id'], 'function_id' => WorkgroupFunction::STORES_COORDINATION, 'target_id' => $region1]);
        foreach (range(1, 3) as $i) {
            $user = $I->createStoreCoordinator($password, ['email' => 'userstorecoordination' . $i . '@example.com', 'bezirk_id' => $region1, 'image' => true]);
            $I->addRegionMember($storesGroup['id'], $user['id']);
            $I->addRegionAdmin($storesGroup['id'], $user['id']);
            $this->storesGroupAdmin[] = $user['id'];
        }
        $this->output->writeln(' done');

        // Create REPORTAdmins Group
        $this->output->writeln('- create report group');
        $reportGroup = $I->createWorkingGroup('Meldungsbearbeitung Göttingen', ['parent_id' => $region1, 'email_name' => 'meldungsbearbeitung.Goettingen', 'teaser' => 'Hier sind die Meldungsbearbeiter für unseren Bezirk']);
        $I->haveInDatabase('fs_region_function', ['region_id' => $reportGroup['id'], 'function_id' => WorkgroupFunction::REPORT, 'target_id' => $region1]);
        foreach (range(1, 4) as $i) {
            $user = $I->createStoreCoordinator($password, ['email' => 'userreport' . $i . '@example.com', 'bezirk_id' => $region1, 'image' => true]);
            $I->addRegionMember($reportGroup['id'], $user['id']);
            $I->addRegionAdmin($reportGroup['id'], $user['id']);
            $this->reportAdmins[] = $user['id'];
        }
        $this->output->writeln(' done');

        // Create MediationAdmins Group
        $this->output->writeln('- create mediation group');
        $mediationGroup = $I->createWorkingGroup('Mediation Göttingen', ['parent_id' => $region1, 'email_name' => 'Mediation Göttingen', 'email' => 'mediation.goettingen', 'teaser' => 'Hier sind die Meldungsbearbeiter für unseren Bezirk']);
        $I->haveInDatabase('fs_region_function', ['region_id' => $mediationGroup['id'], 'function_id' => WorkgroupFunction::MEDIATION, 'target_id' => $region1]);
        foreach (range(1, 3) as $i) {
            $user = $I->createStoreCoordinator($password, ['email' => 'usermediation' . $i . '@example.com', 'bezirk_id' => $region1, 'image' => true]);
            $I->addRegionMember($mediationGroup['id'], $user['id']);
            $I->addRegionAdmin($mediationGroup['id'], $user['id']);
            $this->mediationAdmins[] = $user['id'];
        }
        $this->output->writeln(' done');

        // Create ArbitrationAdmins Group
        $this->output->writeln('- create arbitration group');
        $arbitrationGroup = $I->createWorkingGroup('Schiedsstelle Göttingen', ['parent_id' => $region1, 'email_name' => 'schiedstelle.Goettingen', 'teaser' => 'Hier ist das Schiedsstellenteam für unseren Bezirk']);
        $I->haveInDatabase('fs_region_function', ['region_id' => $arbitrationGroup['id'], 'function_id' => WorkgroupFunction::ARBITRATION, 'target_id' => $region1]);
        foreach (range(1, 4) as $i) {
            $user = $I->createStoreCoordinator($password, ['email' => 'userarbitration' . $i . '@example.com', 'bezirk_id' => $region1, 'image' => true]);
            $I->addRegionMember($arbitrationGroup['id'], $user['id']);
            $I->addRegionAdmin($arbitrationGroup['id'], $user['id']);
            $this->arbitrationAdmins[] = $user['id'];
        }
        $this->output->writeln(' done');

        // Create FSMANAGEMENT Group
        $this->output->writeln('- create fsmanagement group');
        $fsmanagementGroup = $I->createWorkingGroup('Verwaltung Göttingen', ['parent_id' => $region1, 'email_name' => 'verwaltung.Goettingen', 'teaser' => 'Hier ist das Verwaltungsteam für unseren Bezirk']);
        $I->haveInDatabase('fs_region_function', ['region_id' => $fsmanagementGroup['id'], 'function_id' => WorkgroupFunction::FSMANAGEMENT, 'target_id' => $region1]);
        foreach (range(1, 3) as $i) {
            $user = $I->createStoreCoordinator($password, ['email' => 'userfsmanagement' . $i . '@example.com', 'bezirk_id' => $region1, 'image' => true]);
            $I->addRegionMember($fsmanagementGroup['id'], $user['id']);
            $I->addRegionAdmin($fsmanagementGroup['id'], $user['id']);
            $this->fsManagementAdmins[] = $user['id'];
        }
        $this->output->writeln(' done');

        // Create PR Group
        $this->output->writeln('- create pr group');
        $prGroup = $I->createWorkingGroup('Öffentlichkeitsarbeit Göttingen', ['parent_id' => $region1, 'email_name' => 'oeffentlichkeitsarbeit.Goettingen', 'teaser' => 'Hier ist das Öffentlichkeitsarbeitsteam für unseren Bezirk']);
        $I->haveInDatabase('fs_region_function', ['region_id' => $prGroup['id'], 'function_id' => WorkgroupFunction::PR, 'target_id' => $region1]);
        foreach (range(1, 5) as $i) {
            $user = $I->createStoreCoordinator($password, ['email' => 'userpr' . $i . '@example.com', 'bezirk_id' => $region1, 'image' => true]);
            $I->addRegionMember($prGroup['id'], $user['id']);
            $I->addRegionAdmin($prGroup['id'], $user['id']);
            $this->prGroup[] = $user['id'];
        }
        $this->output->writeln(' done');

        // Create MODERATION Group
        $this->output->writeln('- create moderation group');
        $moderationGroup = $I->createWorkingGroup('Moderation Göttingen', ['parent_id' => $region1, 'email_name' => 'moderation.Goettingen', 'teaser' => 'Hier ist das Moderationsteam für unseren Bezirk']);
        $I->haveInDatabase('fs_region_function', ['region_id' => $moderationGroup['id'], 'function_id' => WorkgroupFunction::MODERATION, 'target_id' => $region1]);
        foreach (range(1, 4) as $i) {
            $user = $I->createStoreCoordinator($password, ['email' => 'usermoderation' . $i . '@example.com', 'bezirk_id' => $region1, 'image' => true]);
            $I->addRegionMember($moderationGroup['id'], $user['id']);
            $I->addRegionAdmin($moderationGroup['id'], $user['id']);
            $this->moderationAdmins[] = $user['id'];
        }
        $this->output->writeln(' done');

        // Create BOARD Group
        $this->output->writeln('- create board group');
        $boardGroup = $I->createWorkingGroup('Vorstand Göttingen', ['parent_id' => $region1, 'email_name' => 'vorstand.Goettingen', 'teaser' => 'Hier ist der Vorstand für unseren Bezirk']);
        $I->haveInDatabase('fs_region_function', ['region_id' => $boardGroup['id'], 'function_id' => WorkgroupFunction::BOARD, 'target_id' => $region1]);
        foreach (range(1, 4) as $i) {
            $user = $I->createStoreCoordinator($password, ['email' => 'userboard' . $i . '@example.com', 'bezirk_id' => $region1, 'image' => true]);
            $I->addRegionMember($boardGroup['id'], $user['id']);
            $I->addRegionAdmin($boardGroup['id'], $user['id']);
            $this->boardAdmins[] = $user['id'];
        }
        $this->output->writeln(' done');

        $this->output->writeln('- create election group');
        $electionGroup = $I->createWorkingGroup('Wahlen Göttingen', ['parent_id' => $region1, 'email_name' => 'wahlen.Goettingen', 'teaser' => 'Hier ist die Wahlen AG für unseren Bezirk']);
        $I->haveInDatabase('fs_region_function', ['region_id' => $electionGroup['id'], 'function_id' => WorkgroupFunction::ELECTION, 'target_id' => $region1]);
        foreach (range(1, 4) as $i) {
            $user = $I->createStoreCoordinator($password, ['email' => 'userelection' . $i . '@example.com', 'bezirk_id' => $region1, 'image' => true]);
            $I->addRegionMember($electionGroup['id'], $user['id']);
            $I->addRegionAdmin($electionGroup['id'], $user['id']);
            $I->addRegionMember(RegionIDs::ELECTION_ADMIN_GROUP, $user['id']);
            $this->electionAdmins[] = $user['id'];
        }
        $this->output->writeln(' done');
    }

    protected function CreateMorePickups()
    {
        for ($m = 0; $m <= 10; ++$m) {
            $store_id = $this->getRandomIDOfArray($this->stores);
            for ($i = 0; $i <= 10; ++$i) {
                $pickupDate = Carbon::create(2022, 4, random_int(1, 30), random_int(1, 24), random_int(1, 59));
                $maxFoodsavers = count($this->foodsavers) > 2 ? 2 : count($this->foodsavers);
                for ($k = 0; $k <= $maxFoodsavers; ++$k) {
                    $foodSaver_id = $this->getRandomIDOfArray($this->foodsavers);
                    $this->helper->addCollector($foodSaver_id, $store_id, ['date' => $pickupDate->toDateTimeString()]);
                    $this->helper->addStoreTeam($store_id, $foodSaver_id);
                }
            }
            $this->output->write('.');
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

    private function createStoreAndAddToTeam($I, $region, $conv1Id, $conv2Id, $statusId, $teamMembers, $addRecurringPickup = false, $is_waiting = false, $is_confirmed = true): mixed
    {
        $store = $I->createStore($region, $conv1Id, $conv2Id, ['betrieb_status_id' => $statusId]);

        foreach ($teamMembers as $teamMember) {
            $I->addStoreTeam($store['id'], $teamMember['id'], $teamMember['manager'] ?? false, $is_waiting, $is_confirmed);
        }

        if ($addRecurringPickup) {
            $I->addRecurringPickup($store['id']);
        }

        return $store;
    }

    protected function seed()
    {
        $I = $this->helper;
        $I->_getDbh()->beginTransaction();
        $I->_getDriver()->executeQuery('SET FOREIGN_KEY_CHECKS=0;', []);
        $I->createRegion('Foodsharing auf Festivals', ['id' => RegionIDs::FOODSHARING_ON_FESTIVALS, 'parent_id' => RegionIDs::ROOT, 'type' => UnitType::CITY, 'has_children' => 0]);
        $regionEurope = $I->createRegion('Europa', ['id' => RegionIDs::EUROPE, 'parent_id' => RegionIDs::ROOT, 'type' => UnitType::COUNTRY, 'has_children' => 1]);
        $regionGermany = $I->createRegion('Deutschland', ['id' => RegionIDs::GERMANY, 'parent_id' => $regionEurope['id'], 'type' => UnitType::COUNTRY, 'has_children' => 1]);
        $regionLowerSaxony = $I->createRegion('Niedersachsen', ['parent_id' => $regionGermany['id'], 'type' => UnitType::FEDERAL_STATE, 'has_children' => 1]);
        $regionOne = $I->createRegion('Göttingen', ['parent_id' => $regionLowerSaxony['id'], 'type' => UnitType::CITY, 'has_children' => 1]);
        $region1 = $regionOne['id'];
        $regionTwo = $I->createRegion('Entenhausen', ['parent_id' => $regionLowerSaxony['id'], 'type' => UnitType::CITY, 'has_children' => 1]);
        $region2 = $regionTwo['id'];
        $regionOneWorkGroup = $I->createWorkingGroup('Schnippelparty Göttingen', ['parent_id' => $regionOne['id']]);
        $region_vorstand = RegionIDs::TEAM_BOARD_MEMBER;
        $ag_aktive = RegionIDs::TEAM_ADMINISTRATION_MEMBER;
        $ag_testimonials = RegionIDs::TEAM_BOARD_MEMBER;
        $team_alumni = RegionIDs::TEAM_ALUMNI_MEMBER;
        $ag_quiz = RegionIDs::QUIZ_AND_REGISTRATION_WORK_GROUP;
        $ag_startpage = RegionIDs::PR_START_PAGE;
        $ag_partnerandteam = RegionIDs::PR_PARTNER_AND_TEAM_WORK_GROUP;

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
        $I->createWorkingGroup('Hygiene', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::HYGIENE_GROUP]);
        $I->createWorkingGroup('Meldungen-AG Praxisaustausch', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::REPORT_TEAM_ADMIN_GROUP]);
        $I->createWorkingGroup('Mediation-AG Praxisaustausch', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::MEDIATION_TEAM_ADMIN_GROUP]);
        $I->createWorkingGroup('Schiedsstelle-AG Praxisaustausch', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::ARBITRATION_TEAM_ADMIN_GROUP]);
        $I->createWorkingGroup('Verwaltung-AG Praxisaustausch', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::FSMANAGEMENT_TEAM_ADMIN_GROUP]);
        $I->createWorkingGroup('Öffentlichkeitsarbeit-AG Praxisaustausch', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::PR_TEAM_ADMIN_GROUP]);
        $I->createWorkingGroup('Moderation-AG Praxisaustausch', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::MODERATION_TEAM_ADMIN_GROUP]);

        $region1Subregion = $I->createRegion('Stadtteil von Göttingen', ['type' => UnitType::PART_OF_TOWN, 'parent_id' => $region1]);

        $this->output->writeln('Create store categories:');
        $I->createStoreCategories();
        // Create users
        $this->output->writeln('Create basic users:');
        $user1 = $I->createFoodsharer($password, ['email' => 'user1@example.com', 'name' => 'One']);
        $this->writeUser($I, $user1, $password, 'foodsharer');

        $userDataFile = 'src/Dev/userData.json';
        if (!file_exists($userDataFile)) {
            $this->output->write($userDataFile . 'not found');
            exit(1);
        }
        $userData = json_decode(file_get_contents($userDataFile), true);

        if ($userData === null) {
            $this->output->write('userData is NULL');
            exit(1);
        }

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

        $this->output->writeln('- done');

        $this->output->writeln('Create some user interaction:');
        // Create buddyset
        $I->addBuddy($userbot['id'], $userorga['id']);

        // Add users to region
        $this->output->writeln('- add users to region');
        $I->addRegionAdmin($region1, $userbot['id']);
        $I->addRegionAdmin($region1, $userbot2['id']);
        $I->addRegionMember($ag_quiz, $userbot['id']);
        $I->addRegionAdmin($ag_quiz, $userbot['id']);
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

        $I->addRegionAdmin(RegionIDs::IT_SUPPORT_GROUP, $userStoreManager2['id']);
        $I->addRegionMember(RegionIDs::IT_SUPPORT_GROUP, $userStoreManager2['id']);
        $I->addRegionAdmin(RegionIDs::NEWSLETTER_WORK_GROUP, $user2['id']);
        $I->addRegionAdmin(RegionIDs::EDITORIAL_GROUP, $userbot['id']);
        $I->addRegionAdmin(RegionIDs::STORE_CHAIN_GROUP, $userbot['id']);
        $I->addRegionAdmin(RegionIDs::HYGIENE_GROUP, $userbot['id']);

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
        $I->createCommunityPin($region1);

        // Create store team conversation
        $this->output->writeln('- create store team conversations');
        $conv1 = $I->createConversation([$userbot['id'], $user2['id'], $userStoreManager['id']], ['name' => 'betrieb_bla', 'locked' => 1]);
        $conv2 = $I->createConversation([$userbot['id']], ['name' => 'springer_bla', 'locked' => 1]);
        $I->addConversationMessage($userStoreManager['id'], $conv1['id']);
        $I->addConversationMessage($userbot['id'], $conv1['id']);
        $I->addConversationMessage($userbot['id'], $conv2['id']);

        $this->output->writeln('- create store and add team members');

        $teamMembers = [
            ['id' => $user2['id']],
            ['id' => $userStoreManager['id'], 'manager' => true],
            ['id' => $userbot['id'], 'manager' => true]
        ];

        $regions = [
            $region1 => [
                CooperationStatus::COOPERATION_ESTABLISHED->value => $teamMembers,
                CooperationStatus::PERMANENTLY_CLOSED->value => [['id' => $userbot['id'], 'manager' => true]],
                CooperationStatus::GIVES_TO_OTHER_CHARITY->value => [['id' => $userbot['id'], 'manager' => true]],
                CooperationStatus::UNCLEAR->value => [['id' => $userbot['id'], 'manager' => true]],
            ],
            $region2 => [
                CooperationStatus::COOPERATION_ESTABLISHED->value => [['id' => $userbot['id']]],
                CooperationStatus::PERMANENTLY_CLOSED->value => [['id' => $userbot['id'], 'manager' => true]],
                CooperationStatus::GIVES_TO_OTHER_CHARITY->value => [['id' => $userbot['id'], 'manager' => true]],
                CooperationStatus::UNCLEAR->value => [['id' => $userbot['id'], 'manager' => true]],
            ],
        ];

        $possibleMemberStates = [
            ['isWaiting' => true, 'isConfirmed' => false],
            ['isWaiting' => false, 'isConfirmed' => true]
        ];
        foreach ($regions as $region => $statuses) {
            foreach ($statuses as $status => $teamMembers) {
                $addRecurringPickup = $status === CooperationStatus::COOPERATION_ESTABLISHED->value;
                $store = $this->createStoreAndAddToTeam($I, $region, $conv1['id'], $conv2['id'], $status, $teamMembers, $addRecurringPickup);

                $additionalStoreCount = 2;
                for ($i = 0; $i < $additionalStoreCount; ++$i) {
                    $memberState = $possibleMemberStates[random_int(0, 1)];
                    $store = $this->createStoreAndAddToTeam($I, $region, $conv1['id'], $conv2['id'], $status, $teamMembers, $addRecurringPickup, $memberState['isWaiting'], $memberState['isConfirmed']);
                }
            }
        }

        $this->output->writeln('- create store chains');
        $this->chain_ids = [];
        foreach (range(0, 50) as $_) {
            $chain = $I->addStoreChain();
            $this->chain_ids[] = $chain['id'];
            $this->output->write('.');
        }
        $this->output->writeln(' done');

        $this->output->writeln('- create food types');
        foreach (range(0, 10) as $_) {
            $I->addStoreFoodType();
            $this->output->write('.');
        }
        $this->output->writeln(' done');

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
        $this->output->writeln('- done');

        // create users and collect their ids in a list
        $this->output->writeln('Create some more users');
        $this->foodsavers = array_column([$user2, $userbot, $userorga, $userbot2, $userStoreManager, $userStoreManager2], 'id');
        foreach ($this->foodsavers as $user) {
            $this->addVerificationAndPassHistory($I, $user, $userbotDeleted['id'], 13);
            $this->addVerificationAndPassHistory($I, $user, $userbot['id']);
        }
        foreach (range(0, 100) as $_) {
            $user = $I->createFoodsaver($password, ['bezirk_id' => $region1, 'image' => true]);
            $this->foodsavers[] = $user['id'];
            $I->addStoreTeam($store['id'], $user['id']);
            $I->addCollector($user['id'], $store['id']);
            $I->addStoreNotiz($user['id'], $store['id']);
            $I->addForumThreadPost($thread['id'], $user['id']);
            $this->addVerificationAndPassHistory($I, $user['id'], $userbotDeleted['id'], 13);
            $this->addVerificationAndPassHistory($I, $user['id'], $userbot['id']);
            $I->addEventInvitation($event['id'], $user['id']);
            $this->output->write('.');
        }
        $this->output->writeln(' done');
        $this->output->writeln(' Create old users');
        foreach (range(0, 20) as $_) {
            $I->createFoodsaver($password, ['bezirk_id' => $region1, 'last_login' => Carbon::now()->subyears(6)]);
            $this->output->write('.');
        }
        $this->output->writeln(' done');
        $this->output->writeln('Create old users with no_automatic_delete flag');
        foreach (range(0, 20) as $_) {
            $I->createFoodsaver($password, ['bezirk_id' => $region1, 'last_login' => Carbon::now()->subyears(6), 'no_automatic_delete' => 1]);
            $this->output->write('.');
        }
        $this->output->writeln(' done');

        // give some trust bananas
        $this->output->writeln('Give some trust bananas');
        foreach ($this->foodsavers as $recipient) {
            foreach ($this->getRandomIDOfArray($this->foodsavers, 2) as $sender) {
                $I->giveBanana($sender, $recipient);
            }
            $this->output->write('.');
        }
        $this->output->writeln(' done');

        // create conversations between users
        $this->output->writeln('Create conversations between users');
        foreach ($this->foodsavers as $user) {
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
            $this->output->write('.');
        }
        $this->output->writeln(' done');

        // Create more Forum Threads
        $this->output->writeln('- Create more forum Threads');
        $randomFsList = array_slice($this->foodsavers, -100, 100, true);
        foreach ($this->getRandomIDOfArray($randomFsList, 100) as $random_user) {
            foreach (range(0, 5) as $_) {
                $I->addForumThread($region1, $random_user);
            }
            $this->output->write('.');
        }
        $this->output->writeln(' done');

        // add some users to a workgroup
        $this->output->writeln('Add users to workgroup');
        // but only the ones we generated above
        $randomFsList = array_slice($this->foodsavers, -100, 100, true);
        foreach ($this->getRandomIDOfArray($randomFsList, 10) as $random_user) {
            $I->addRegionMember($region1WorkGroup, $random_user);
            $this->output->write('.');
        }
        $this->output->writeln(' done');

        $this->createFunctionWorkgroups($region1);

        // create more stores and collect their ids in a list
        $this->output->writeln('Create some stores');
        $this->stores = [$store['id']];
        foreach (range(0, 40) as $_) {
            // TODO conversations are missing the other store members
            $conv1 = $I->createConversation([$userbot['id']], ['name' => 'team', 'locked' => 1]);
            $conv2 = $I->createConversation([$userbot['id']], ['name' => 'springer', 'locked' => 1]);

            $extra_params = [];
            if (random_int(0, 1) == 1) {
                $extra_params['kette_id'] = $this->chain_ids[random_int(0, 10)];
            }

            $store = $I->createStore($region1, $conv1['id'], $conv2['id'], $extra_params);
            foreach (range(0, 5) as $_) {
                $I->addRecurringPickup($store['id']);
            }
            $this->stores[] = $store['id'];
            $this->output->write('.');
        }
        $this->output->writeln(' done');

        $this->output->writeln('Create more pickups');
        $this->CreateMorePickups();
        $this->output->writeln(' done');

        // create foodbaskets
        $this->output->writeln('Create foodbaskets');
        foreach (range(0, 500) as $_) {
            $user = $this->getRandomIDOfArray($this->foodsavers);
            $I->createFoodbasket($user);
            $this->output->write('.');
        }
        $this->output->writeln(' done');

        // create food share point
        $this->output->writeln('Create food share points');
        foreach ($this->getRandomIDOfArray($this->foodsavers, 50) as $user) {
            $foodSharePoint = $I->createFoodSharePoint($user, $region1);
            foreach ($this->getRandomIDOfArray($this->foodsavers, 10) as $follower) {
                if ($user !== $follower) {
                    $I->addFoodSharePointFollower($follower, $foodSharePoint['id']);
                }
                $I->addFoodSharePointPost($follower, $foodSharePoint['id']);
            }
            $this->output->write('.');
        }
        $this->output->writeln(' done');

        $this->output->writeln('Create blog posts');
        foreach (range(0, 20) as $_) {
            $I->addBlogPost($userbot['id'], $region1);
            $this->output->write('.');
        }
        $this->output->writeln(' done');

        $this->output->writeln('Create reports');

        $I->addReport($this->getRandomIDOfArray($this->reportAdmins), $this->getRandomIDOfArray($this->foodsavers), 0, 0);
        $I->addReport($this->getRandomIDOfArray($this->foodsavers), $this->getRandomIDOfArray($this->reportAdmins), 0, 0);
        $I->addReport($this->getRandomIDOfArray($this->arbitrationAdmins), $this->getRandomIDOfArray($this->foodsavers), 0, 0);
        $I->addReport($this->getRandomIDOfArray($this->foodsavers), $this->getRandomIDOfArray($this->arbitrationAdmins), 0, 0);
        $I->addReport($this->getRandomIDOfArray($this->reportAdmins), $this->getRandomIDOfArray($this->arbitrationAdmins), 0, 0);
        $I->addReport($this->getRandomIDOfArray($this->arbitrationAdmins), $this->getRandomIDOfArray($this->reportAdmins), 0, 0);
        $I->addReport($this->getRandomIDOfArray($this->foodsavers), $this->getRandomIDOfArray($this->foodsavers), 0, 0);

        $this->output->writeln(' done');

        $this->output->writeln('Create quizzes');
        foreach (QuizID::cases() as $quizId) {
            $I->createQuiz($quizId->value);
            $this->output->write('.');
        }
        $this->output->writeln(' done');

        $this->output->writeln('Create polls');
        foreach ([
            VotingType::SELECT_ONE_CHOICE, VotingType::SELECT_MULTIPLE, VotingType::THUMB_VOTING,
            VotingType::SCORE_VOTING
        ] as $type) {
            $this->createPoll(
                $region1,
                $userbot['id'],
                $type,
                [$user2['id'], $userStoreManager['id'], $userStoreManager2['id'], $userbot['id'], $userorga['id']]
            );
            $this->output->write('.');
        }
        foreach (range(0, 30) as $_) {
            $startDate = Carbon::now()->subDays(random_int(7, 3 * 365));
            $type = random_int(VotingType::SELECT_ONE_CHOICE, VotingType::SCORE_VOTING);
            $this->createPoll($region1, $userbot['id'], $type,
                [$user2['id'], $userStoreManager['id'], $userStoreManager2['id'], $userbot['id'], $userorga['id']],
                $startDate, $startDate->addDays(6)
            );
            $this->output->write('.');
        }
        $this->output->write('.');

        $this->output->writeln(' done');

        $this->output->writeln('Create blacklisted emails');
        $I->createBlacklistedEmailAddress();
        $this->output->writeln(' done');

        $this->output->writeln('Create achievements');
        $this->createAchievements($I);
        $this->output->writeln(' done');

        $I->_getDriver()->executeQuery('SET FOREIGN_KEY_CHECKS=1;', []);
        $I->_getDbh()->commit();
    }

    private function createAchievements(Foodsharing $I)
    {
        $achievementsDataFile = 'src/Dev/achievements.json';
        if (!file_exists($achievementsDataFile)) {
            $this->output->write($achievementsDataFile . ' not found');
            exit(1);
        }
        $achievementsData = json_decode(file_get_contents($achievementsDataFile), true);

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
}
