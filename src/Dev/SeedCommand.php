<?php

namespace Foodsharing\Dev;

use Carbon\Carbon;
use Codeception\CustomCommandInterface;
use DateInterval;
use Foodsharing\Modules\Core\DBConstants\Achievement\AchievementIDs;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Core\DBConstants\Configuration\ConfigurationCategory;
use Foodsharing\Modules\Core\DBConstants\Configuration\ConfigurationKey;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\ApplyType;
use Foodsharing\Modules\Core\DBConstants\Region\GroupCategory;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Core\DBConstants\Store\CooperationStatus;
use Foodsharing\Modules\Core\DBConstants\Store\StoreLogAction;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Core\DBConstants\Voting\VotingScope;
use Foodsharing\Modules\Core\DBConstants\Voting\VotingType;
use Foodsharing\Modules\Development\FeatureToggles\Enums\FeatureToggleDefinitions;
use Tests\Support\Helper\Foodsharing;

// this is Codeception specific, so we can't use the regular AsCommand attribute
class SeedCommand extends AbstractSeedCommand implements CustomCommandInterface
{
    protected static string $defaultDescription = 'Seed the dev db.';
    private const string USER_PASSWORD = 'user';

    public static function getCommandName(): string
    {
        return 'foodsharing:seed';
    }

    protected function configure(): void
    {
        $this->setHelp('This commands adds seed data to the database. The general rule is that before running this command, you have a working instance of foodsharing without customized data (e.g. missing regions, quizzes, ...) but already including all the data that is directly used in the code (so you will not get any internal server errors). The future goal is, to make the code as much independent of data as possible and move all data you may want to playing around into the seed.');
    }

    protected function seed(): void
    {
        $I = $this->helper;

        // Create group categories:
        $this->createGroupCategories();

        // Create base regions
        $this->output->writeln('Create base regions');
        $I->createRootRegion();
        $I->createRegion('Foodsharing auf Festivals', ['id' => RegionIDs::FOODSHARING_ON_FESTIVALS, 'parent_id' => RegionIDs::ROOT, 'type' => UnitType::CITY, 'has_children' => 0]);
        $I->createRegion('Arbeitsgruppen Überregional', ['id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'parent_id' => RegionIDs::ROOT, 'type' => UnitType::BIG_CITY, 'master' => 392, 'mailbox_id' => 32678, 'email' => 'arbeitsgruppen.ueberregional', 'email_name' => 'Foodsharing Arbeitsgruppen Überregional', 'stat_last_update' => '2020-05-24 02:17:57', 'stat_fetchweight' => '5176.00', 'stat_fetchcount' => '208', 'stat_postcount' => '53969', 'stat_betriebcount' => '1', 'stat_korpcount' => '0', 'stat_botcount' => '1', 'stat_fscount' => '3360']);
        $I->createWorkingGroup('Vereinsvorstand', ['id' => RegionIDs::TEAM_BOARD_MEMBER, 'parent_id' => RegionIDs::ROOT, 'teaser' => '.', 'master' => RegionIDs::TEAM_BOARD_MEMBER, 'mailbox_id' => 26644, 'email' => 'vereinsvorstand', 'name' => 'Vereinsvorstand', 'email_name' => 'Foodsharing Vereinsvorstand', 'apply_type' => ApplyType::NOBODY, 'moderated' => true]);
        $I->createWorkingGroup('Orgateam Archiv', ['id' => RegionIDs::ORGA_TEAM_ARCHIVE, 'parent_id' => RegionIDs::ROOT, 'teaser' => 'Das Forum des alten Orgateams', 'mailbox_id' => 528, 'email' => 'orgateam.archiv', 'email_name' => 'Foodsharing Orgateam', 'apply_type' => ApplyType::NOBODY, 'moderated' => true]);
        $I->createWorkingGroup('Aktive (Überregional)', ['id' => RegionIDs::TEAM_ADMINISTRATION_MEMBER, 'parent_id' => RegionIDs::ORGA_TEAM_ARCHIVE, 'teaser' => 'Wer hier in der Gruppe aufgelistet wird, erscheint auch auf der Teamseite. Ist bisher eine stille Gruppe.', 'mailbox_id' => 30176, 'email' => 'aktive', 'email_name' => 'Foodsharing Aktive']);
        $I->createWorkingGroup('Ehemalige (Vorstand und Orgateam)', ['id' => RegionIDs::TEAM_ALUMNI_MEMBER, 'parent_id' => RegionIDs::ORGA_TEAM_ARCHIVE, 'teaser' => 'x', 'mailbox_id' => 30177, 'email' => 'ehemalige', 'email_name' => 'Foodsharing Ehemalige', 'apply_type' => ApplyType::NOBODY]);

        $regionEurope = $I->createRegion('Europa', ['id' => RegionIDs::EUROPE, 'parent_id' => RegionIDs::ROOT, 'type' => UnitType::CONTINENT, 'has_children' => 1, 'mailbox_id' => 25467, 'email' => 'europa', 'email_name' => 'Foodsharing Europa', 'stat_last_update' => '2020-05-24 02:18:15', 'stat_fetchweight' => '33829400.50', 'stat_fetchcount' => '2116647', 'stat_postcount' => '1733615', 'stat_betriebcount' => '23002', 'stat_korpcount' => '7031', 'stat_botcount' => '1004', 'stat_fscount' => '74600', 'stat_fairteilercount' => '891'], fillMailbox: true);
        $regionGermany = $I->createRegion('Deutschland', ['id' => RegionIDs::GERMANY, 'parent_id' => $regionEurope['id'], 'type' => UnitType::COUNTRY, 'has_children' => 1], fillMailbox: true);
        $I->createRegion('Schweiz', ['id' => RegionIDs::SWITZERLAND, 'parent_id' => $regionEurope['id'], 'type' => UnitType::COUNTRY, 'has_children' => 1]);
        $regionLowerSaxony = $I->createRegion('Niedersachsen', ['parent_id' => $regionGermany['id'], 'type' => UnitType::FEDERAL_STATE, 'has_children' => 1], fillMailbox: true);
        $regionOne = $I->createRegion('Göttingen', ['parent_id' => $regionLowerSaxony['id'], 'type' => UnitType::CITY, 'has_children' => 1, 'email' => 'goettingen'], fillMailbox: true);
        $region1 = $regionOne['id'];
        $I->createRegion('Stadtteil von Göttingen', ['type' => UnitType::PART_OF_TOWN, 'parent_id' => $region1], fillMailbox: true);
        $regionTwo = $I->createRegion('Entenhausen', ['parent_id' => $regionLowerSaxony['id'], 'type' => UnitType::CITY, 'has_children' => 1], fillMailbox: true);
        $region2 = $regionTwo['id'];

        $this->output->writeln('Create store categories');
        $I->createStoreCategories();
        // Create users
        $this->output->writeln('Create basic users:');
        $user1 = $this->createUser($I, Role::FOODSHARER, 'foodsharer', ['email' => 'user1@example.com', 'name' => 'One']);

        $userData = $this->loadJsonFromFile('userData.json');

        $user2 = $this->createUser($I, Role::FOODSAVER, 'foodsaver', [
            'email' => 'user2@example.com',
            'name' => 'Two',
            'bezirk_id' => $region1,
            'about_me_public' => $userData['user2']['about_me_public'],
            'position' => $userData['user2']['position'],
            'image' => true
        ]);
        $userStoreManager = $this->createUser($I, Role::STORE_MANAGER, 'store coordinator', [
            'email' => 'storemanager1@example.com',
            'name' => 'Three',
            'bezirk_id' => $region1,
            'about_me_public' => $userData['userStoreManager']['about_me_public'],
            'position' => $userData['userStoreManager']['position'],
            'image' => true
        ]);
        $userStoreManager2 = $this->createUser($I, Role::STORE_MANAGER, 'store coordinator2', [
            'email' => 'storemanager2@example.com',
            'name' => 'Four',
            'bezirk_id' => $region1,
            'about_me_public' => $userData['userStoreManager2']['about_me_public'],
            'position' => $userData['userStoreManager2']['position'],
            'image' => true
        ]);
        $userbot = $this->createUser($I, Role::AMBASSADOR, 'ambassador', [
            'email' => 'userbot@example.com',
            'name' => 'Bot',
            'bezirk_id' => $region1,
            'about_me_intern' => 'hello!',
            'about_me_public' => $userData['userbot']['about_me_public'],
            'position' => $userData['userbot']['position'],
            'image' => true
        ]);
        $userbot2 = $this->createUser($I, Role::AMBASSADOR, 'ambassador', [
            'email' => 'userbot2@example.com',
            'name' => 'Bot2',
            'bezirk_id' => $region1,
            'about_me_intern' => 'hello!',
            'about_me_public' => $userData['userbot2']['about_me_public'],
            'position' => $userData['userbot2']['position'],
            'image' => true
        ]);
        // Create an ambassador whose profile is already deleted and cannot be used but who will show up in verification histories
        $userbotDeleted = $this->createUser($I, Role::AMBASSADOR, 'deleted ambassador', [
            'email' => 'userbotdeleted@example.com',
            'name' => 'Bot3',
            'bezirk_id' => $region2,
            'about_me_intern' => 'hello!',
            'deleted_at' => Carbon::now()->subYear()
        ]);
        $userbotregion2 = $this->createUser($I, Role::AMBASSADOR, 'ambassador', [
            'email' => 'userbotreg2@example.com',
            'name' => 'Bot Entenhausen',
            'bezirk_id' => $region2,
            'about_me_intern' => 'hello!',
            'image' => true
        ]);
        $userorga = $this->createUser($I, Role::ORGA, 'orga', [
            'email' => 'userorga@example.com',
            'name' => 'Orga',
            'bezirk_id' => $region1,
            'about_me_intern' => 'hello!',
            'about_me_public' => $userData['userorga']['about_me_public'],
            'position' => $userData['userorga']['position'],
            'image' => true
        ]);
        $userorgaWG = $this->createUser($I, Role::ORGA, 'orga', ['email' => 'userorgaWG@example.com', 'name' => 'OrgaWG', 'bezirk_id' => $region1, 'id' => RegionIDs::CREATING_WORK_GROUPS_WORK_GROUP, 'image' => true]);
        $userAuth = $this->createUser($I, Role::STORE_MANAGER, 'store coordinator - OAUTH User', ['email' => 'userauth@example.com', 'name' => 'OAuth', 'bezirk_id' => $region1, 'image' => true]);

        $foodsavers = array_column([$user2, $userbot, $userorga, $userbot2, $userStoreManager, $userStoreManager2], 'id');

        $this->output->writeln('Adding buddies to userbot');
        $this->addBuddies($I, $userbot, $userorga);
        $this->addBuddies($I, $userbot, $userorgaWG, false);
        $this->addBuddies($I, $userbotregion2, $userbot, false);

        $this->output->writeln('Create global working groups');
        $this->createGlobalWorkingGroups($I, $userbot['id']);
        $this->output->writeln('');

        $this->output->writeln('Create local working groups');
        $I->createWorkingGroup('Betriebsketten Schweiz', ['parent_id' => RegionIDs::SWITZERLAND, 'id' => RegionIDs::STORE_CHAIN_GROUP_SWITZERLAND, 'category_id' => GroupCategory::DEVELOPMENT->value, 'include_thread' => $userbot['id']]);
        $regionOneWorkGroup = $I->createWorkingGroup('Schnippelparty Göttingen', ['parent_id' => $regionOne['id'], 'include_thread' => $userbot['id']]);

        $this->output->writeln('Create achievements');
        $this->createAchievements($I);

        // Add users to region
        $this->output->writeln('Add users to region');
        $this->addRegionMembers($I, $region1, [], [$userbot['id'], $userbot2['id']]);
        $this->addRegionMembers($I, $region2, [$userbot['id']], []);
        $this->addRegionMembers($I, RegionIDs::QUIZ_AND_REGISTRATION_WORK_GROUP, [], [$userbot['id']]);
        $this->addRegionMembers($I, RegionIDs::NEW_QUIZZES_WORK_GROUP, [$userStoreManager['id']], [$userbot['id']]);
        $this->addRegionMembers($I, RegionIDs::QUIZ_GROUP_FR, [], [$userbot['id']]);
        $this->addRegionMembers($I, RegionIDs::PR_START_PAGE, [], [$userbot['id'], $userStoreManager['id']]);
        $this->addRegionMembers($I, RegionIDs::PR_PARTNER_AND_TEAM_WORK_GROUP, [], [$userbot['id'], $userStoreManager2['id']]);
        $this->addRegionMembers($I, RegionIDs::TEAM_BOARD_MEMBER, [
            $user2['id'], $userbot['id'], $userStoreManager['id'], $userStoreManager2['id'], $userorga['id']
        ], []);
        $this->addRegionMembers($I, RegionIDs::TEAM_ADMINISTRATION_MEMBER, [
            $userbot['id'], $userStoreManager['id'], $userStoreManager2['id'], $userorga['id']
        ], []);
        $this->addRegionMembers($I, RegionIDs::TEAM_ALUMNI_MEMBER, [
            $user2['id'], $userStoreManager['id'], $userStoreManager2['id'], $userorga['id']
        ], []);
        $this->addRegionMembers($I, RegionIDs::STORE_CHAIN_GROUP, [$user2['id']], [$userbot['id']]);
        $this->addRegionMembers($I, RegionIDs::HYGIENE_GROUP, [$user2['id']], [$userbot['id']]);
        $this->addRegionMembers($I, RegionIDs::POLITICAL_CAMPAIGNS, [$user2['id']], [$userbot['id']]);
        $this->addRegionMembers($I, RegionIDs::FOODSHARING_ACADEMY, [$user2['id']], [$userbot['id']]);
        $this->addRegionMembers($I, RegionIDs::EDITORIAL_GROUP, [], [$userStoreManager2['id'], $userorga['id']]);
        $this->addRegionMembers($I, RegionIDs::EDITORIAL_GROUP, [], [$userbot['id']]);
        $this->addRegionMembers($I, RegionIDs::POLITICAL_CAMPAIGNS, [], [$userbot['id'], $userorga['id']]);

        $this->addRegionMembers($I, RegionIDs::OAUTH_CLIENT_ADMINISTRATION_WORK_GROUP, [], [$userAuth['id']]);
        $this->addRegionMembers($I, RegionIDs::CREATING_WORK_GROUPS_WORK_GROUP, [], [$userorgaWG['id']]);
        $this->addRegionMembers($I, RegionIDs::FUNDRAISING_AND_FINANCIAL_PLANNING_GROUP, [], [$userbot['id']]);

        // Make ambassador responsible for all work groups in the region
        $this->output->writeln('Make ambassador responsible for all work groups');
        $workGroupsIds = $I->grabColumnFromDatabase('fs_bezirk', 'id', ['parent_id' => $region1, 'type' => UnitType::WORKING_GROUP]);
        foreach ($workGroupsIds as $id) {
            $this->addRegionMembers($I, $id, [], [$userbot['id']]);
        }
        // create event
        $this->output->writeln('Create events');
        $events = $this->createEvents($I, 3, $region1, $foodsavers);
        $this->output->writeln('');

        $this->output->writeln('Create engagement statistik user');
        $this->createEngagementsStat($region1, $events[0]['id']);

        // create Community Pin
        $this->output->writeln('Create community pin');
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
        $this->output->writeln('Create store and add team members');
        $regions = [
            $region1 => [
                // array structure: [managers, members, waiting]
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
        foreach ($regions as $regionId => $statuses) {
            foreach ($statuses as $status => $userLists) {
                $addRecurringPickup = $status === CooperationStatus::COOPERATION_ESTABLISHED->value;
                $store = $this->createStoreAndAddToTeam($I, $regionId, $status, $userLists[0], $userLists[1], $userLists[2], addRecurringPickup: $addRecurringPickup);

                $additionalStoreCount = 2;
                for ($i = 0; $i < $additionalStoreCount; ++$i) {
                    $store = $this->createStoreAndAddToTeam($I, $regionId, $status, $userLists[0], $userLists[1], $userLists[2]);
                }
            }
        }

        $this->output->writeln('Create store chains');
        $chain_ids = [];
        foreach ($this->progressBar->iterate(range(1, 50)) as $_) {
            $chain = $I->addStoreChain();
            $chain_ids[] = $chain['id'];
        }
        $I->addKamToStoreChain($chain_ids[0], $userbot['id']);
        $this->output->writeln('');

        $this->output->writeln('Create food types');
        foreach ($this->progressBar->iterate(range(1, 10)) as $_) {
            $I->addStoreFoodType();
        }
        $this->output->writeln('');

        // Forum theads and posts
        $this->output->writeln('Create forum threads and posts');
        $thread = $I->addForumThread($region1, $userbot['id']);
        $I->addForumThreadPost($thread['id'], $user2['id']);
        $thread = $I->addForumThread($region1, $user2['id']);
        $I->addForumThreadPost($thread['id'], $user1['id']);
        $thread = $I->addForumThread($region1, $user1['id']);
        $I->addForumThreadPost($thread['id'], $userorga['id']);

        $this->output->writeln('Follow a food share point');
        $foodSharePoint = $I->createFoodSharePoint($userbot['id'], $region1);
        $I->addFoodSharePointFollower($user2['id'], $foodSharePoint['id']);
        $I->addFoodSharePointPost($userbot['id'], $foodSharePoint['id']);

        // create users and collect their ids in a list
        $this->output->writeln('Create some more users');
        foreach ($foodsavers as $user) {
            $this->addVerificationAndPassHistory($I, $user, $userbotDeleted['id'], 13);
            $this->addVerificationAndPassHistory($I, $user, $userbot['id']);
        }
        foreach ($this->progressBar->iterate(range(1, 50)) as $_) {
            $user = $I->createFoodsaver(self::USER_PASSWORD, ['bezirk_id' => $region1, 'image' => true]);
            $foodsavers[] = $user['id'];
            $I->addStoreTeam($store['id'], $user['id']);
            $I->addCollector($user['id'], $store['id']);
            $I->addStoreNotiz($user['id'], $store['id']);
            $I->addForumThreadPost($thread['id'], $user['id']);
            $this->addVerificationAndPassHistory($I, $user['id'], $userbotDeleted['id'], 13);
            $this->addVerificationAndPassHistory($I, $user['id'], $userbot['id']);
            $I->addEventInvitation($events[0]['id'], $user['id']);
        }
        $this->output->writeln('');

        $this->output->writeln('Create old users');
        foreach ($this->progressBar->iterate(range(1, 20)) as $_) {
            $I->createFoodsaver(self::USER_PASSWORD, ['bezirk_id' => $region1, 'last_login' => Carbon::now()->subyears(6)]);
        }
        $this->output->writeln('');

        $this->output->writeln('Create old users with no_automatic_delete flag');
        foreach ($this->progressBar->iterate(range(1, 20)) as $_) {
            $I->createFoodsaver(self::USER_PASSWORD, ['bezirk_id' => $region1, 'last_login' => Carbon::now()->subyears(6), 'no_automatic_delete' => 1]);
        }
        $this->output->writeln('');

        $this->output->writeln('Creating resources');
        $this->createResources($foodsavers);

        // give some trust bananas
        $this->output->writeln('Give some trust bananas');
        foreach ($this->progressBar->iterate($foodsavers) as $recipient) {
            foreach ($this->getRandomIDOfArray($foodsavers, 2) as $sender) {
                $I->giveBanana($sender, $recipient);
            }
        }
        $this->output->writeln('');

        // create conversations between users
        $this->output->writeln('Create conversations between users');
        $this->createConversations($I, $foodsavers);
        $this->output->writeln('');

        // Create more Forum Threads
        $this->output->writeln('Create more forum Threads');
        $randomFsList = $this->getRandomIDOfArray($foodsavers, 20);
        foreach ($this->progressBar->iterate($randomFsList) as $random_user) {
            foreach (range(1, 5) as $_) {
                $I->addForumThread($region1, $random_user);
            }
        }
        $this->output->writeln('');

        // add some users to a workgroup
        $this->output->writeln('Add users to workgroup');
        // but only the ones we generated above
        $this->addRegionMembers($I, $regionOneWorkGroup['id'], $randomFsList, []);

        $this->output->writeln('Creating special working groups');
        $this->createFunctionWorkgroups($region1, $userbot);
        $this->output->writeln('');

        // create more stores and collect their ids in a list
        $this->output->writeln('Create some stores');
        $stores = $this->createStores($I, 60, $region1, $chain_ids, $userbot['id']);
        $stores[] = $store['id'];
        $this->output->writeln('');

        // Create a special store for screenshots for onboarding
        $this->output->writeln('Create a special store for screenshots for onboarding');
        $store = $this->createSpecialOnboardingStore($I, $foodsavers, [$userStoreManager['id'], $userbot['id']], $region1, $chain_ids[0]);
        $stores[] = $store['id'];
        $this->output->writeln('- created with id ' . $store['id']);

        // create pickups
        $this->output->writeln('Create more pickups');
        $this->createMorePickups($stores, $foodsavers);
        $this->output->writeln('');

        // create foodbaskets
        $this->output->writeln('Create food baskets');
        $this->progressBar->start(100);
        $this->createFoodBaskets($I, 50, $foodsavers);
        $this->createFoodBaskets($I, 50, $foodsavers, [$userbot['lat'], $userbot['lon']]);
        $this->progressBar->finish();
        $this->output->writeln('');

        // create food share point
        $this->output->writeln('Create food share points');
        $this->createFoodSharePoints($I, 50, $region1, $foodsavers);
        $this->output->writeln('');

        $this->output->writeln('Create blog posts');
        foreach ($this->progressBar->iterate(range(1, 20)) as $_) {
            $I->addBlogPost($userbot['id'], $region1);
        }
        $this->output->writeln('');

        $this->output->writeln('Create reports');
        $this->createReports($I, $region1, $foodsavers);
        $this->output->writeln('');

        $this->output->writeln('Create polls');
        $pollTypes = [
            VotingType::SELECT_ONE_CHOICE, VotingType::SELECT_MULTIPLE, VotingType::THUMB_VOTING,
            VotingType::SCORE_VOTING
        ];
        foreach ($this->progressBar->iterate($pollTypes) as $type) {
            $this->createPoll(
                $region1,
                $userbot['id'],
                $type,
                [$user2['id'], $userStoreManager['id'], $userStoreManager2['id'], $userbot['id'], $userorga['id']]
            );
        }
        $this->output->writeln('');

        $this->output->writeln('Create more one choice polls');
        foreach ($this->progressBar->iterate(range(1, 30)) as $_) {
            $startDate = Carbon::now()->subDays(random_int(7, 3 * 365));
            $type = random_int(VotingType::SELECT_ONE_CHOICE, VotingType::SCORE_VOTING);
            $this->createPoll($region1, $userbot['id'], $type,
                [$user2['id'], $userStoreManager['id'], $userStoreManager2['id'], $userbot['id'], $userorga['id']],
                $startDate, $startDate->addDays(6)
            );
        }
        $this->output->writeln('');

        $this->output->writeln('Create blacklisted emails');
        $I->createBlacklistedEmailAddress($userorga['id']);

        $this->output->writeln('Enable feature toggles');
        $this->activeFeatureToggles();

        $this->output->writeln('Inserting fetch weight values');
        $this->insertFetchWeightValues($I);

        $this->output->writeln('Adding content');
        $this->createContent($I);

        $this->output->writeln('Inserting configuration values');
        $this->insertConfigurationValues($I);

        $this->output->writeln('Create bell notifications');
        $I->addBells([$userbot, $userbot2], [
            'name' => 'new_foodsaver_title',
            'body' => 'new_foodsaver_verified',
            'vars' => serialize(['name' => $user2['name'] . ' ' . $user2['nachname'], 'bezirk' => $regionOne['name']]),
            'attr' => serialize(['href' => '/profile/' . $user2['id']]),
            'icon' => '',
            'identifier' => BellType::createIdentifier(BellType::NEW_FOODSAVER_IN_REGION, $user2),
            'time' => Carbon::now()->subDays(random_int(1, 5))->format('Y-m-d H:i:s'),
            'closeable' => 1
        ]);
        $blogPost = $I->grabEntryFromDatabase('fs_blog_entry');
        $I->addBells([$user2, $userbot, $userorga, $userbot2, $userStoreManager, $userStoreManager2], [
            'name' => 'blog_new_check_title',
            'body' => 'blog_new_check',
            'vars' => serialize([
                'user' => $userbot['name'],
                'teaser' => $blogPost['teaser'],
                'title' => $blogPost['name'],
            ]),
            'attr' => serialize(['href' => '/blog?sub=edit&id=' . $blogPost['id']]),
            'icon' => 'fas fa-bullhorn',
            'identifier' => BellType::createIdentifier(BellType::NEW_BLOG_POST, $blogPost['id']),
            'time' => $blogPost['time'],
            'closeable' => 1
        ]);
    }

    /**
     * Retrieves one or multiple random elements from the given array.
     *
     * @param array $value the array from which to select random elements
     * @param int $number The number of random elements to retrieve. Defaults to 1.
     * @return mixed returns a single random element if $number is 1,
     *               an associative array of random elements if $number > 1
     */
    private function getRandomIDOfArray(array $value, int $number = 1): mixed
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
    private function getRandomIDOfArrayAndDelete(array &$value, int $number = 1): array
    {
        $values = $this->getRandomIDOfArray($value, $number);

        foreach ($values as $i => $_) {
            unset($value[$i]);
        }

        return $values;
    }

    private function createEngagementsStat(int $region1, int $eventid = 0): void
    {
        $I = $this->helper;
        $user = $I->createStoreCoordinator(self::USER_PASSWORD, ['email' => 'userengagement@example.com', 'bezirk_id' => $region1, 'image' => true]);
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

    private function createFunctionWorkgroups(int $region, array $adminOfAll): void
    {
        $I = $this->helper;

        $workgroups = [
            ['name' => 'Begrüßung', 'function' => WorkgroupFunction::WELCOME, 'key' => 'welcome', 'category' => GroupCategory::ADMINISTRATIVE->value],
            ['name' => 'Abstimmungen', 'function' => WorkgroupFunction::VOTING, 'key' => 'voting', 'addAdminsToRegion' => RegionIDs::VOTING_ADMIN_GROUP, 'category' => GroupCategory::ADMINISTRATIVE->value],
            ['name' => 'Fairteiler', 'function' => WorkgroupFunction::FSP, 'key' => 'fsp', 'category' => GroupCategory::DEVELOPMENT->value, 'apply_type' => ApplyType::OPEN],
            ['name' => 'Betriebskoordination', 'function' => WorkgroupFunction::STORES_COORDINATION, 'key' => 'stores', 'category' => GroupCategory::DEVELOPMENT->value],
            ['name' => 'Meldungsbearbeitung', 'function' => WorkgroupFunction::REPORT, 'key' => 'report', 'category' => GroupCategory::ADMINISTRATIVE->value, 'apply_type' => ApplyType::NOBODY],
            ['name' => 'Mediation', 'function' => WorkgroupFunction::MEDIATION, 'key' => 'mediation', 'category' => GroupCategory::ADMINISTRATIVE->value],
            ['name' => 'Schiedsstelle', 'function' => WorkgroupFunction::ARBITRATION, 'key' => 'arbitration', 'category' => GroupCategory::ADMINISTRATIVE->value, 'apply_type' => ApplyType::NOBODY],
            ['name' => 'Verwaltung', 'function' => WorkgroupFunction::FSMANAGEMENT, 'key' => 'fsManagement', 'category' => GroupCategory::ADMINISTRATIVE->value],
            ['name' => 'Öffentlichkeitsarbeit', 'function' => WorkgroupFunction::PR, 'key' => 'pr', 'category' => GroupCategory::DEVELOPMENT->value, 'apply_type' => ApplyType::OPEN],
            ['name' => 'Moderation', 'function' => WorkgroupFunction::MODERATION, 'key' => 'moderation', 'category' => GroupCategory::ADMINISTRATIVE->value],
            ['name' => 'Vorstand', 'function' => WorkgroupFunction::BOARD, 'key' => 'board', 'category' => GroupCategory::ADMINISTRATIVE->value, 'apply_type' => ApplyType::NOBODY],
            ['name' => 'Wahlen', 'function' => WorkgroupFunction::ELECTION, 'key' => 'election', 'addAdminsToRegion' => RegionIDs::ELECTION_ADMIN_GROUP, 'category' => GroupCategory::ADMINISTRATIVE->value],
            ['name' => 'Ressourcen', 'function' => WorkgroupFunction::RESOURCES, 'key' => 'resources', 'category' => GroupCategory::DEVELOPMENT->value, 'apply_type' => ApplyType::OPEN],
        ];

        foreach ($this->progressBar->iterate($workgroups) as $wg) {
            $group = $I->createWorkingGroup($wg['name'] . ' Göttingen', [
                'parent_id' => $region,
                'email' => preg_replace(['/ä/', '/ö/', '/ü/', '/ß/'], ['ae', 'oe', 'ue', 'ss'], strtolower($wg['name'])) . '.goettingen',
                'teaser' => 'Hier ist die AG ' . $wg['name'] . ' für unseren Bezirk',
                'category_id' => $wg['category'],
                'apply_type' => $wg['apply_type'] ?? ApplyType::EVERYBODY,
                'include_thread' => $adminOfAll['id'],
            ]);
            $I->haveInDatabase('fs_region_function', [
                'region_id' => $group['id'],
                'function_id' => $wg['function'],
                'target_id' => $region,
            ]);
            for ($i = 1; $i <= 3; ++$i) {
                $user = $I->createStoreCoordinator(self::USER_PASSWORD, [
                    'email' => "user{$wg['key']}$i@example.com",
                    'bezirk_id' => $region,
                    'image' => true,
                ]);
                $this->addRegionMembers($I, $group['id'], [], [$user['id']]);

                if (!empty($wg['addAdminsToRegion'])) {
                    $I->addRegionMember($wg['addAdminsToRegion'], $user['id']);
                }
                if (isset($this->{$wg['key'] . 'Admins'})) {
                    $this->{$wg['key'] . 'Admins'}[] = $user['id'];
                }
            }
            $this->addRegionMembers($I, $group['id'], [], [$adminOfAll['id']]);
        }
    }

    private function createMorePickups(array $stores, array $userIds): void
    {
        $this->progressBar->start(121);
        for ($m = 0; $m <= 10; ++$m) {
            $store_id = $this->getRandomIDOfArray($stores);
            for ($i = 0; $i <= 10; ++$i) {
                $pickupDate = Carbon::create(2022, 4, random_int(1, 30), random_int(1, 24), random_int(1, 59));
                $maxFoodsavers = count($userIds) > 2 ? 2 : count($userIds);
                for ($k = 0; $k <= $maxFoodsavers; ++$k) {
                    $foodSaver_id = $this->getRandomIDOfArray($userIds);
                    $this->helper->addCollector($foodSaver_id, $store_id, ['date' => $pickupDate->toDateTimeString()]);
                }
                $this->progressBar->advance();
            }
        }
        $this->progressBar->finish();
    }

    private function createUser(Foodsharing $I, Role $role, string $name, array $params): array
    {
        $user = match ($role) {
            Role::FOODSAVER => $I->createFoodsaver(self::USER_PASSWORD, $params),
            Role::STORE_MANAGER => $I->createStoreCoordinator(self::USER_PASSWORD, $params),
            Role::AMBASSADOR => $I->createAmbassador(self::USER_PASSWORD, $params),
            Role::ORGA => $I->createOrga(self::USER_PASSWORD, false, $params),
            default => $I->createFoodsharer(self::USER_PASSWORD, $params)
        };
        // Make sure that the user is a member of all parent regions of the home region
        if (!empty($user['bezirk_id'])) {
            $regionId = $I->grabFromDatabase('fs_bezirk', 'parent_id', ['id' => $user['bezirk_id']]);
            while ($regionId !== 0) {
                $I->addRegionMember($regionId, $user['id']);
                $regionId = $I->grabFromDatabase('fs_bezirk', 'parent_id', ['id' => $regionId]);
            }
        }

        $this->output->writeln('- created ' . $name . ' ' . $user['email'] . ' with password "' . self::USER_PASSWORD . '"');

        return $user;
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
        array $extraStoreParams = []
    ): array {
        $conv1Id = $I->createConversation([], ['name' => 'betrieb_bla', 'locked' => 1])['id'];
        $conv2Id = $I->createConversation([], ['name' => 'springer_bla', 'locked' => 1])['id'];
        $extraStoreParams['betrieb_status_id'] = $statusId;
        $store = $I->createStore($regionId, $conv1Id, $conv2Id, $extraStoreParams);
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

    /**
     * Activates a predefined list of feature toggles.
     */
    private function activeFeatureToggles(): void
    {
        $features = [
            FeatureToggleDefinitions::HYGIENE_QUIZ->value,
            FeatureToggleDefinitions::FORUM_FULL_TEXT_SEARCH->value
        ];
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
        ?Carbon $startDate = null, ?Carbon $endDate = null): void
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
    private function addVerificationAndPassHistory(Foodsharing $I, int $userId, int $verifierId, int $monthsInPast = 0): void
    {
        $offset = Carbon::today()->subMonths($monthsInPast);
        $I->addVerificationHistory($userId, $verifierId, true, $offset->sub('1 year'));
        $I->addVerificationHistory($userId, $verifierId, false, $offset->sub('6 months'));
        $I->addVerificationHistory($userId, $verifierId, true, $offset->sub('1 month'));

        foreach (range(0, 3) as $_) {
            $I->addPassHistory($userId, $verifierId);
        }
    }

    private function createGroupCategories(): void
    {
        $categoriesData = $this->loadJsonFromFile('groupCategories.json');
        foreach ($categoriesData as $id => $name) {
            $this->helper->addGroupCategory($name, $id + 1);
        }
    }

    private function createResources(array $userIds): void
    {
        $resourcesData = $this->loadJsonFromFile('resources.json');
        $resourceCategoriesData = $this->loadJsonFromFile('resourceCategories.json');

        $offset = 0;
        foreach ($resourceCategoriesData as $id => $name) {
            $categoryId = $this->helper->addResourceCategory($name);
            if ($id === 0) {
                $offset = $categoryId;
            }
        }

        shuffle($resourcesData);
        while (count($resourcesData) > 0) {
            $userId = $this->getRandomIDOfArray($userIds);
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

    private function insertConfigurationValues(Foodsharing $I): void
    {
        $donation = [
            ConfigurationKey::DONATION_CAMPAIGN_ID->value => '12573',
            ConfigurationKey::DONATION_FRIENDSHIP_CIRCLE_ID->value => '398',
            ConfigurationKey::DONATION_MODAL_ID->value => '12573',
            ConfigurationKey::DONATION_ONE_TIME_DONATION_ID->value => '384',
            ConfigurationKey::DONATION_SHOW_CAMPAIGN_CARD->value => '1',
            ConfigurationKey::DONATION_SHOW_DONATION_MODAL->value => '0',
            ConfigurationKey::DONATION_SHOW_DONATION_MODAL_IN_HOURS_FOR_LOGGED_IN_USERS->value => '24',
            ConfigurationKey::DONATION_SHOW_DONATION_MODAL_IN_HOURS_FOR_LOGGED_OUT_USERS->value => '1',
            ConfigurationKey::DONATION_SHOW_CAMPAIGN_PART_1->value => '1',
            ConfigurationKey::DONATION_SHOW_CAMPAIGN_PART_2->value => '1',
            ConfigurationKey::DONATION_SHOW_CAMPAIGN_GALLERY->value => '1',
            ConfigurationKey::DONATION_MODAL_INFO_URL->value => 'donation/campaign',
            ConfigurationKey::DONATION_MODAL_POPUP_URL->value => 'https://spenden.twingle.de/foodsharing-e-v/spendenkampagne-ueberregionale-arbeit/tw65a581c764fa1/page',
            ConfigurationKey::DONATION_IFRAME_CAMPAIGN_URL->value => 'https://spenden.twingle.de/embed/foodsharing-e-v/spendenkampagne-ueberregionale-arbeit/tw65a581c764fa1/widget',
            ConfigurationKey::DONATION_IFRAME_FRIENDSHIP_CIRCLE_URL->value => 'https://spenden.twingle.de/embed/foodsharing-e-v/freundeskreis/tw5ba5f44dcb36f/widget',
            ConfigurationKey::DONATION_IFRAME_ONE_TIME_URL->value => 'https://spenden.twingle.de/embed/foodsharing-e-v/einmal-spenden/tw5ba1eb3588eb2/widget',
            ConfigurationKey::DONATION_IFRAME_SELF_SERVICE_URL->value => 'https://spenden.twingle.de/selfservice/dEUvRHVpeG5VVEtNOWRPdTFuS0F2QT09'
        ];
        foreach ($donation as $key => $value) {
            $I->haveInDatabase('configuration', ['key' => $key, 'value' => $value, 'category' => ConfigurationCategory::DONATION->value]);
        }

        // Last calculation of the user statistics: this needs to be far in the past to force full recalculation of the profile statistics after seeding
        $I->haveInDatabase('configuration', ['key' => ConfigurationKey::STATISTICS_FOODSAVER_LAST_UPDATE->value, 'value' => Carbon::createFromFormat('Y-m-d', '1970-01-01')->toISOString()]);
    }

    /**
     * Creates conversations between 11 random users from the array.
     */
    private function createConversations(Foodsharing $I, array $users): void
    {
        foreach ($this->progressBar->iterate($users) as $user) {
            foreach ($this->getRandomIDOfArray($users, 10) as $chatpartner) {
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
        }
    }

    /**
     * Creates all groups in "Arbeitsgruppen überregional" and their sub-groups.
     */
    private function createGlobalWorkingGroups(Foodsharing $I, int $threadAuthorId): void
    {
        // parameters: name, group category, apply type
        $globalGroups = [
            RegionIDs::CREATING_WORK_GROUPS_WORK_GROUP => ['AG Anlegen', GroupCategory::ADMINISTRATIVE, ApplyType::NOBODY],
            RegionIDs::IT_SUPPORT_GROUP => ['Support', GroupCategory::ADMINISTRATIVE],
            RegionIDs::PR_PARTNER_AND_TEAM_WORK_GROUP => ['Öffentlichkeitsarbeit - Partner + Teamseite', GroupCategory::ADMINISTRATIVE, ApplyType::NOBODY],
            RegionIDs::PR_START_PAGE => ['Öffentlichkeitsarbeit - Startseite', GroupCategory::ADMINISTRATIVE, ApplyType::NOBODY],
            RegionIDs::ORGA_COORDINATION_GROUP => ['Orgarechte-Koordination', GroupCategory::ADMINISTRATIVE],
            RegionIDs::EDITORIAL_GROUP => ['Redaktion', GroupCategory::ADMINISTRATIVE],
            RegionIDs::BOARD_ADMIN_GROUP => ['foodsharing Vereins-Vorstände', GroupCategory::ADMINISTRATIVE, ApplyType::NOBODY],
            RegionIDs::WELCOME_TEAM_ADMIN_GROUP => ['Begrüßungsteam Praxisaustausch', GroupCategory::EXCHANGE, ApplyType::NOBODY],
            RegionIDs::VOTING_ADMIN_GROUP => ['Abstimmungs-AG Praxisaustausch', GroupCategory::EXCHANGE, ApplyType::NOBODY],
            RegionIDs::ELECTION_ADMIN_GROUP => ['Wahlen-AG Praxisaustausch', GroupCategory::EXCHANGE, ApplyType::NOBODY],
            RegionIDs::FSP_TEAM_ADMIN_GROUP => ['Fairteiler-AG Praxisaustausch', GroupCategory::EXCHANGE, ApplyType::NOBODY],
            RegionIDs::STORE_COORDINATION_TEAM_ADMIN_GROUP => ['Betriebskoordination-AG Praxisaustausch', GroupCategory::EXCHANGE, ApplyType::NOBODY],
            RegionIDs::STORE_CHAIN_GROUP => ['AG Betriebsketten', GroupCategory::DEVELOPMENT],
            RegionIDs::HYGIENE_GROUP => ['Hygiene', GroupCategory::DEVELOPMENT],
            RegionIDs::POLITICAL_CAMPAIGNS => ['PolKa', GroupCategory::DEVELOPMENT],
            RegionIDs::FOODSHARING_ACADEMY => ['Akademie und Bildungsreferent:innen', GroupCategory::DEVELOPMENT],
            RegionIDs::QUIZ_GROUP_FR => ['Quiz FR', GroupCategory::DEVELOPMENT], // actually in france, but for the seed data it's here...
            RegionIDs::REPORT_TEAM_ADMIN_GROUP => ['Meldungen-AG Praxisaustausch', GroupCategory::EXCHANGE, ApplyType::NOBODY],
            RegionIDs::MEDIATION_TEAM_ADMIN_GROUP => ['Mediation-AG Praxisaustausch', GroupCategory::EXCHANGE, ApplyType::NOBODY],
            RegionIDs::ARBITRATION_TEAM_ADMIN_GROUP => ['Schiedsstelle-AG Praxisaustausch', GroupCategory::EXCHANGE, ApplyType::NOBODY],
            RegionIDs::FSMANAGEMENT_TEAM_ADMIN_GROUP => ['Verwaltung-AG Praxisaustausch', GroupCategory::EXCHANGE, ApplyType::NOBODY],
            RegionIDs::PR_TEAM_ADMIN_GROUP => ['Öffentlichkeitsarbeit-AG Praxisaustausch', GroupCategory::EXCHANGE, ApplyType::NOBODY],
            RegionIDs::MODERATION_TEAM_ADMIN_GROUP => ['Moderation-AG Praxisaustausch', GroupCategory::EXCHANGE, ApplyType::NOBODY],
            RegionIDs::PRODUCT_TEAM => ['Produktteam', null, ApplyType::OPEN],
            RegionIDs::OAUTH_CLIENT_ADMINISTRATION_WORK_GROUP => ['Oauth Client Administration', null, ApplyType::NOBODY],
            RegionIDs::QUIZ_AND_REGISTRATION_WORK_GROUP => ['Anmeldevorgang & Quiz', null, ApplyType::NOBODY],
            RegionIDs::TDL_2026_GROUP => ['Tag der Lebensmittelrettung 2026', GroupCategory::PROJECT, ApplyType::OPEN],
            //TODO: 6825 is something else on production. TDL2025 does not exist there.
            6825 => ['Tag der Lebensmittelrettung 2025', GroupCategory::ARCHIVED, ApplyType::OPEN],
            RegionIDs::FUNDRAISING_AND_FINANCIAL_PLANNING_GROUP => ['Fundraising und Finanzplanung', GroupCategory::ADMINISTRATIVE, ApplyType::NOBODY],
        ];
        foreach ($this->progressBar->iterate($globalGroups) as $id => $params) {
            $I->createWorkingGroup($params[0], [
                'parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS,
                'id' => $id,
                'category_id' => !is_null($params[1]) ? $params[1]->value : null,
                'apply_type' => $params[2] ?? ApplyType::NOBODY,
                'include_thread' => $threadAuthorId
            ]);
        }

        $I->createWorkingGroup('Quizfragen', ['parent_id' => RegionIDs::QUIZ_AND_REGISTRATION_WORK_GROUP, 'id' => RegionIDs::NEW_QUIZZES_WORK_GROUP, 'apply_type' => ApplyType::EVERYBODY]);
    }

    /**
     * Creates several stores. Each store will be added to the region and to a random store chain from the list. Also
     * assigns the store manager to the store.
     *
     * @param int $amount the number of stores to create
     * @param int $regionId the region to which the stores will be added
     * @param int[] $chainIds a list of the possible chains. Set to null to use no chains.
     * @param int $storeManagerId ID of the user who will be the manager of all stores
     * @return int[] IDs of all created stores
     */
    private function createStores(Foodsharing $I, int $amount, int $regionId, ?array $chainIds, int $storeManagerId): array
    {
        $stores = [];
        foreach ($this->progressBar->iterate(range(1, $amount)) as $_) {
            $extra_params = [];
            if (!empty($chainIds) || random_int(0, 1) == 1) {
                $extra_params['kette_id'] = $chainIds[random_int(0, 10)];
            }

            $store = $I->createStore($regionId, null, null, $extra_params);

            // Add userbot randomly to stores as team member (50%), coordinator
            // (10%) or jumper (10%), not added (30%)
            $random = random_int(0, 10);
            $isJumper = $random == 1;
            $isCordinator = $random == 2;
            if ($random <= 6) {
                $I->addStoreTeam($store['id'], $storeManagerId, $isCordinator, $isJumper, true);
            }

            foreach (range(0, 5) as $__) {
                $I->addRecurringPickup($store['id']);
            }
            $stores[] = $store['id'];
        }

        return $stores;
    }

    /**
     * Creates several food baskets.
     *
     * @param int $amount how many baskets to create
     * @param array $authors a list of users from which the basket owners will be randomly picked
     * @param ?array $centerPoint optional coordinates around which the baskets will be centered
     */
    private function createFoodBaskets(Foodsharing $I, int $amount, array $authors, array $centerPoint = null): void
    {
        foreach (range(1, $amount) as $_) {
            $extraParams = [];
            if (!empty($centerPoint)) {
                [$lat, $lon] = $I->getPointAtDistance($centerPoint[0], $centerPoint[1], $_ + 1, -1);
                $extraParams['lat'] = $lat;
                $extraParams['lon'] = $lon;
            }
            $user = $this->getRandomIDOfArray($authors);
            $I->createFoodbasket($user, $extraParams);
            $this->progressBar->advance();
        }
    }

    /**
     * Creates a special store that can be used for creating screenshots for onboarding.
     *
     * @param int[] $foodsavers IDs of the store members
     * @param int[] $managers IDs of the store managers
     * @param int $regionId to which region the store will be added
     * @param int $chainId to which chain the store will be added
     * @return array the store
     */
    private function createSpecialOnboardingStore(Foodsharing $I, array $foodsavers, array $managers, int $regionId, int $chainId): array
    {
        // Make sure that the managers are not part of the list of foodsaver
        foreach ($managers as $manager) {
            unset($foodsavers[array_search($manager, $foodsavers)]);
        }

        $members = $this->getRandomIDOfArrayAndDelete($foodsavers, 20);
        $jumpers = $this->getRandomIDOfArrayAndDelete($foodsavers, 2);
        $applied = $this->getRandomIDOfArrayAndDelete($foodsavers, 2);

        $I->awardAchievement($this->getRandomIDOfArray($members, 15), AchievementIDs::HYGIENE_CERTIFICATE, null);

        $store = $this->createStoreAndAddToTeam($I, $regionId, CooperationStatus::COOPERATION_ESTABLISHED->value, $managers, $members, $jumpers, $applied, false, [
            'kette_id' => $chainId,
            'name' => 'Schulungsbetrieb Onboarding',
        ]);

        $appliedDate = Carbon::now()->addDays(-3);
        foreach ($applied as $applicant) {
            $I->addStoreLog($store['id'], $applicant, $applicant, StoreLogAction::REQUEST_TO_JOIN, ['content' => $I->faker->realText(100), 'date_reference' => $appliedDate, 'date_activity' => $appliedDate]);
        }

        // add regular pickups in fs_abholzeiten
        $extra_params = [
            'betrieb_id' => $store['id'],
            'time' => sprintf('%02d:%ss:00', 20, 0),
            'fetcher' => 2,
        ];
        foreach (range(0, 6) as $__) {
            $extra_params['dow'] = $__;
            $I->addRecurringPickup($store['id'], $extra_params);
        }

        // add confirmed pickups
        $pickup = Carbon::now()->settime(20, 0);
        $pickup = $pickup->addDays(-6);
        foreach (range(0, 8) as $__) {
            $pickup = $pickup->addDay();
            $this->helper->addCollector($this->getRandomIDOfArray($foodsavers), $store['id'], ['date' => $pickup->toDayDateTimeString(), 'confirmed' => 1]);
            $this->helper->addCollector($this->getRandomIDOfArray($foodsavers), $store['id'], ['date' => $pickup->toDayDateTimeString(), 'confirmed' => 1]);
        }

        // add some unconfirmed pickups
        $pickup = $pickup->addDay();
        $this->helper->addCollector($this->getRandomIDOfArray($foodsavers), $store['id'], ['date' => $pickup->toDayDateTimeString(), 'confirmed' => 0]);
        $this->helper->addCollector($this->getRandomIDOfArray($foodsavers), $store['id'], ['date' => $pickup->toDayDateTimeString(), 'confirmed' => 0]);
        $pickup = $pickup->addDay();
        $this->helper->addCollector($this->getRandomIDOfArray($foodsavers), $store['id'], ['date' => $pickup->toDayDateTimeString(), 'confirmed' => 0]);

        return $store;
    }

    /**
     * Adds a list of users to a region, either as admin or member. Admins will also be added as members.
     *
     * @param int[] $members
     * @param int[] $admins
     */
    private function addRegionMembers(Foodsharing $I, int $regionId, array $members, array $admins): void
    {
        // Make sure that the admins are not part of the list of members
        foreach ($admins as $admin) {
            unset($members[array_search($admin, $members)]);
        }

        foreach ($admins as $admin) {
            $I->addRegionMember($regionId, $admin);
            $I->addRegionAdmin($regionId, $admin);
        }
        foreach ($members as $member) {
            $I->addRegionMember($regionId, $member);
        }
    }

    /**
     * Creates several events of each type (ongoing, past, and future) in a region.
     *
     * @param int $amount how many events to create of each type
     * @param int $regionId the parent region
     * @param int[] $authors IDs of creators of the events will be randomly drawn from this list
     * @return array the created events
     */
    private function createEvents(Foodsharing $I, int $amount, int $regionId, array $authors): array
    {
        $this->progressBar->start(3 * $amount);

        // Ongoing
        foreach (range(1, $amount) as $_) {
            $events[] = $I->createEvents($regionId, $this->getRandomIDOfArray($authors));
            $this->progressBar->advance();
        }

        // Past
        foreach (range(1, $amount) as $_) {
            $start = $I->faker->dateTimeBetween('-1 year', '-1 month');
            $events[] = $I->createEvents($regionId, $this->getRandomIDOfArray($authors), [
                'start' => $start->format('Y-m-d H:i:s'),
                'end' => $start->add(new DateInterval('PT4H'))->format('Y-m-d H:i:s'),
            ]);
            $this->progressBar->advance();
        }

        // Future
        foreach (range(1, $amount) as $_) {
            $start = $I->faker->dateTimeBetween('+1 month', '+1 year');
            $events[] = $I->createEvents($regionId, $this->getRandomIDOfArray($authors), [
                'start' => $start->format('Y-m-d H:i:s'),
                'end' => $start->add(new DateInterval('PT4H'))->format('Y-m-d H:i:s'),
            ]);
            $this->progressBar->advance();
        }
        $this->progressBar->finish();

        return $events;
    }

    /**
     * Creates several food share points in the region. Adds 10 random users as followers to each FSP.
     *
     * @param int $amount how many to create
     * @param int $regionId which region to add them to
     * @param int[] $users IDs of the users who will be randomly picked as authors of the FSPs
     */
    private function createFoodSharePoints(Foodsharing $I, int $amount, int $regionId, array $users): void
    {
        $authors = $this->getRandomIDOfArray($users, $amount);
        foreach ($this->progressBar->iterate($authors) as $user) {
            $foodSharePoint = $I->createFoodSharePoint($user, $regionId);
            foreach ($this->getRandomIDOfArray($users, 10) as $follower) {
                if ($user !== $follower) {
                    $I->addFoodSharePointFollower($follower, $foodSharePoint['id']);
                }
                $I->addFoodSharePointPost($follower, $foodSharePoint['id']);
            }
        }
    }

    /**
     * Creates reports in the region between random members of the users array and the admins of the  region's
     * arbitration and reports groups.
     *
     * @param int[] $users a list of user IDs that should be used as reporters and reportees
     */
    private function createReports(Foodsharing $I, int $regionId, array $users): void
    {
        // Find all admins of the reports and arbitration groups
        $arbitrationGroupId = $I->grabFromDatabase('fs_region_function', 'region_id', [
            'function_id' => WorkgroupFunction::ARBITRATION,
            'target_id' => $regionId
        ]);
        $arbitrationAdmins = $I->grabColumnFromDatabase('fs_botschafter', 'foodsaver_id', ['bezirk_id' => $arbitrationGroupId]);
        $reportGroupId = $I->grabFromDatabase('fs_region_function', 'region_id', [
            'function_id' => WorkgroupFunction::REPORT,
            'target_id' => $regionId
        ]);
        $reportAdmins = $I->grabColumnFromDatabase('fs_botschafter', 'foodsaver_id', ['bezirk_id' => $reportGroupId]);

        $reportUserGroups = [
            [$reportAdmins, $users],
            [$users, $reportAdmins],
            [$arbitrationAdmins, $users],
            [$users, $arbitrationAdmins],
            [$reportAdmins, $arbitrationAdmins],
            [$arbitrationAdmins, $reportAdmins],
            [$users, $users],
        ];
        foreach ($this->progressBar->iterate($reportUserGroups) as $reportUserGroup) {
            $I->addReport($this->getRandomIDOfArray($reportUserGroup[0]), $this->getRandomIDOfArray($reportUserGroup[1]));
        }
    }

    /**
     * Adds a buddy request from user1 to user2. If the request is not confirmed yet, this creates a bell notification
     * for user2.
     *
     * @param array $user1 sender of the request
     * @param array $user2 receiver of the request
     * @param bool $isConfirmed if the users already are buddies or if the request still needs to be confirmed by user2
     */
    private function addBuddies(Foodsharing $I, array $user1, array $user2, bool $isConfirmed = true): void
    {
        $I->addBuddy($user1['id'], $user2['id'], false);

        if (!$isConfirmed) {
            $I->addBells([$user2], [
                'name' => 'buddy_request_title',
                'body' => 'buddy_request',
                'vars' => serialize(['name' => $user1['name']]),
                'attr' => serialize(['href' => '/profile/' . $user1['id']]),
                'icon' => '',
                'identifier' => BellType::createIdentifier(BellType::BUDDY_REQUEST, $user1['id'], $user2['id']),
                'time' => Carbon::now()->subDays(random_int(1, 5)),
                'closeable' => 1
            ]);
        }
    }
}
