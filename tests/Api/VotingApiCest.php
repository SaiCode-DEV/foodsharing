<?php

declare(strict_types=1);

namespace Tests\Api;

use Carbon\Carbon;
use Codeception\Util\HttpCode as Http;
use DateTime;
use Faker\Factory;
use Faker\Generator;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Core\DBConstants\Voting\VotingScope;
use Foodsharing\Modules\Core\DBConstants\Voting\VotingType;
use Tests\Support\ApiTester;

/**
 * Tests for the voting api.
 */
/**
 * @group api-group-2
 */
class VotingApiCest
{
    private Generator $faker;
    private $region;
    private $userFoodsaverUnverified;
    private $userFoodsaver;
    private $userStoreManager;
    private $userAmbassador;
    private $poll;

    private const string POLLS_API = 'api/polls';
    private const string GROUPS_API = 'api/groups';

    public function _before(ApiTester $I): void
    {
        $this->faker = Factory::create('de_DE');

        $this->region = $I->createRegion();
        $this->userFoodsaverUnverified = $I->createFoodsaver(null, ['bezirk_id' => $this->region['id'], 'verified' => 0]);
        $this->userFoodsaver = $I->createFoodsaver(null, ['bezirk_id' => $this->region['id']]);

        $this->userAmbassador = $I->createAmbassador(null, ['bezirk_id' => $this->region['id']]);
        $I->addRegionAdmin($this->region['id'], $this->userAmbassador['id']);

        $store = $I->createStore($this->region['id']);
        $this->userStoreManager = $I->createStoreCoordinator(null, ['bezirk_id' => $this->region['id']]);
        $I->addStoreTeam($store['id'], $this->userStoreManager['id'], true);

        // Create a voting group and make userFoodsaver admin of that group
        $group = $I->createWorkingGroup('Abstimmung', ['parent_id' => $this->region['id']]);
        $I->haveInDatabase('fs_region_function', [
                'region_id' => $group['id'],
                'function_id' => WorkgroupFunction::VOTING,
                'target_id' => $this->region['id']
        ]);
        $I->addRegionAdmin($group['id'], $this->userFoodsaver['id']);

        $this->poll = $this->createPoll($I, [1], [
            $this->userFoodsaver['id'], $this->userFoodsaverUnverified['id'], $this->userAmbassador['id'],
            $this->userStoreManager['id']
        ], ['type' => VotingType::SELECT_ONE_CHOICE]
        );
    }

    public function canSeePolls(ApiTester $I): void
    {
        $I->sendGET(self::POLLS_API . '/' . $this->poll['id']);
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);

        $I->sendGET(self::GROUPS_API . '/' . $this->region['id'] . '/polls');
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);

        $I->login($this->userFoodsaver['email']);
        $I->sendGET(self::POLLS_API . '/' . $this->poll['id']);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'id' => $this->poll['id']
        ]);

        $I->sendGET(self::GROUPS_API . '/' . $this->region['id'] . '/polls');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'id' => $this->poll['id']
        ]);
    }

    public function canNotGetNonExistingPoll(ApiTester $I): void
    {
        $I->login($this->userFoodsaver['email']);
        $I->sendGET(self::POLLS_API . '/' . ($this->poll['id'] + 1));
        $I->seeResponseCodeIs(Http::NOT_FOUND);
    }

    public function canOnlyVoteOnce(ApiTester $I): void
    {
        $choice = 0;
        $votes = $I->grabFromDatabase('fs_poll_option_has_value', 'votes', [
            'poll_id' => $this->poll['id'],
            'option' => $choice,
            'value' => 1
        ]);

        $I->login($this->userFoodsaver['email']);
        $I->sendPOST(self::POLLS_API . '/' . $this->poll['id'] . '/vote', ['options' => [$choice => 1]]);
        $I->seeResponseCodeIs(Http::OK);
        $I->sendPOST(self::POLLS_API . '/' . $this->poll['id'] . '/vote', ['options' => [$choice => 1]]);
        $I->seeResponseCodeIs(Http::FORBIDDEN);

        $I->seeInDatabase('fs_poll_option_has_value', [
            'poll_id' => $this->poll['id'],
            'option' => $choice,
            'value' => 1,
            'votes' => $votes + 1
        ]);
    }

    public function canNotVoteInDifferentRegion(ApiTester $I): void
    {
        $choice = 0;
        $votes = $I->grabFromDatabase('fs_poll_option_has_value', 'votes', [
            'poll_id' => $this->poll['id'],
            'option' => $choice,
            'value' => 1
        ]);

        $region = $I->createRegion();
        $user = $I->createFoodsaver(null, ['bezirk_id' => $region['id']]);

        $I->login($user['email']);
        $I->sendPOST(self::POLLS_API . '/' . $this->poll['id'] . '/vote', ['options' => [0 => 1]]);
        $I->seeResponseCodeIs(Http::FORBIDDEN);

        $I->seeInDatabase('fs_poll_option_has_value', [
            'poll_id' => $this->poll['id'],
            'option' => $choice,
            'value' => 1,
            'votes' => $votes
        ]);
    }

    public function canOnlyVoteInOngoingPoll(ApiTester $I): void
    {
        $I->login($this->userFoodsaver['email']);

        // create outdated poll
        $poll = $this->createPoll($I, [1], [$this->userFoodsaver['id']], [
            'type' => VotingType::SELECT_ONE_CHOICE,
            'end' => (new DateTime('now - 1 day'))->format('Y-m-d H:i:s')
        ]);

        $I->sendPOST(self::POLLS_API . '/' . $poll['id'] . '/vote', [
            'options' => [0 => 1]
        ]);
        $I->seeResponseCodeIs(Http::FORBIDDEN);

        // create future poll
        $poll2 = $this->createPoll($I, [1], [$this->userFoodsaver['id']], [
            'type' => VotingType::SELECT_ONE_CHOICE,
            'start' => (new DateTime('now + 1 day'))->format('Y-m-d H:i:s')
        ]);

        $I->sendPOST(self::POLLS_API . '/' . $poll2['id'] . '/vote', [
            'options' => [0 => 1]
        ]);
        $I->seeResponseCodeIs(Http::FORBIDDEN);
    }

    public function canUseSingleSelection(ApiTester $I): void
    {
        // create poll with single selection
        $poll = $this->createPoll($I, [1], [$this->userFoodsaver['id']], ['type' => VotingType::SELECT_ONE_CHOICE]);

        // vote with different numbers of options
        $I->login($this->userFoodsaver['email']);
        $I->sendPOST(self::POLLS_API . '/' . $poll['id'] . '/vote', [
            'options' => [0 => 1, 1 => 0, 2 => 1]
        ]);
        $I->seeStatusCodeIs([Http::BAD_REQUEST, Http::UNPROCESSABLE_ENTITY]);
        $I->sendPOST(self::POLLS_API . '/' . $poll['id'] . '/vote', [
            'options' => []
        ]);
        $I->seeStatusCodeIs([Http::BAD_REQUEST, Http::UNPROCESSABLE_ENTITY]);
        $I->sendPOST(self::POLLS_API . '/' . $poll['id'] . '/vote', [
            'options' => [1 => 0]
        ]);
        $I->seeStatusCodeIs([Http::BAD_REQUEST, Http::UNPROCESSABLE_ENTITY]);
        $I->sendPOST(self::POLLS_API . '/' . $poll['id'] . '/vote', [
            'options' => [1 => 1]
        ]);
        $I->seeResponseCodeIs(Http::OK);
    }

    public function canUseMultipleSelection(ApiTester $I): void
    {
        // create poll with multi-selection
        $poll = $this->createPoll($I, [1], [$this->userFoodsaver['id']], ['type' => VotingType::SELECT_MULTIPLE]);

        // vote with different numbers of options
        $I->login($this->userFoodsaver['email']);
        $I->sendPOST(self::POLLS_API . '/' . $poll['id'] . '/vote', [
            'options' => [0 => 1, 1 => 0]
        ]);
        $I->seeStatusCodeIs([Http::BAD_REQUEST, Http::UNPROCESSABLE_ENTITY]);
        $I->sendPOST(self::POLLS_API . '/' . $poll['id'] . '/vote', [
            'options' => [0 => 1, 1 => 0, 2 => 1, 3 => 0, 4 => 1, 5 => 0]
        ]);
        $I->seeStatusCodeIs([Http::BAD_REQUEST, Http::UNPROCESSABLE_ENTITY]);
        $I->sendPOST(self::POLLS_API . '/' . $poll['id'] . '/vote', [
            'options' => []
        ]);
        $I->seeStatusCodeIs([Http::BAD_REQUEST, Http::UNPROCESSABLE_ENTITY]);
        $I->sendPOST(self::POLLS_API . '/' . $poll['id'] . '/vote', [
            'options' => [1 => 1, 2 => 1]
        ]);
        $I->seeResponseCodeIs(Http::OK);
    }

    public function canUseThumbVoting(ApiTester $I): void
    {
        // create poll with score voting
        $poll = $this->createPoll($I, [-1, 0, 1], [$this->userFoodsaver['id']], ['type' => VotingType::THUMB_VOTING]);

        // vote with different numbers of options
        $I->login($this->userFoodsaver['email']);
        $I->sendPOST(self::POLLS_API . '/' . $poll['id'] . '/vote', [
            'options' => [0 => 1, 1 => 0]
        ]);
        $I->seeStatusCodeIs([Http::BAD_REQUEST, Http::UNPROCESSABLE_ENTITY]);
        $I->sendPOST(self::POLLS_API . '/' . $poll['id'] . '/vote', [
            'options' => [0 => 1, 1 => 0, 2 => 1, 3 => 0, 4 => 1, 5 => 0]
        ]);
        $I->seeStatusCodeIs([Http::BAD_REQUEST, Http::UNPROCESSABLE_ENTITY]);
        $I->sendPOST(self::POLLS_API . '/' . $poll['id'] . '/vote', [
            'options' => []
        ]);
        $I->seeStatusCodeIs([Http::BAD_REQUEST, Http::UNPROCESSABLE_ENTITY]);
        $I->sendPOST(self::POLLS_API . '/' . $poll['id'] . '/vote', [
            'options' => [0 => 0, 1 => 1, 2 => -1, 3 => 0]
        ]);
        $I->seeResponseCodeIs(Http::OK);
    }

    public function canUseScoreVoting(ApiTester $I): void
    {
        // create poll with score voting
        $poll = $this->createPoll($I, range(-3, 3), [$this->userFoodsaver['id']], ['type' => VotingType::SCORE_VOTING]);

        // vote with different numbers of options
        $I->login($this->userFoodsaver['email']);
        $I->sendPOST(self::POLLS_API . '/' . $poll['id'] . '/vote', [
            'options' => [0 => 1, 1 => 0]
        ]);
        $I->seeStatusCodeIs([Http::BAD_REQUEST, Http::UNPROCESSABLE_ENTITY]);
        $I->sendPOST(self::POLLS_API . '/' . $poll['id'] . '/vote', [
            'options' => [0 => 1, 1 => 0, 2 => 1, 3 => 0, 4 => 1, 5 => 0]
        ]);
        $I->seeStatusCodeIs([Http::BAD_REQUEST, Http::UNPROCESSABLE_ENTITY]);
        $I->sendPOST(self::POLLS_API . '/' . $poll['id'] . '/vote', [
            'options' => []
        ]);
        $I->seeStatusCodeIs([Http::BAD_REQUEST, Http::UNPROCESSABLE_ENTITY]);
        $I->sendPOST(self::POLLS_API . '/' . $poll['id'] . '/vote', [
            'options' => [0 => 0, 1 => -5, 2 => -1, 3 => 0]
        ]);
        $I->seeStatusCodeIs([Http::BAD_REQUEST, Http::UNPROCESSABLE_ENTITY]);
        $I->sendPOST(self::POLLS_API . '/' . $poll['id'] . '/vote', [
            'options' => [0 => 0, 1 => 1, 2 => -1, 3 => 0]
        ]);
        $I->seeResponseCodeIs(Http::OK);
    }

    public function canCancelPoll(ApiTester $I): void
    {
        $poll = $this->createPoll($I, [-1, 0, 1], [], [
            'type' => VotingType::THUMB_VOTING,
            'start' => Carbon::now()->add('2 hours')->format('Y-m-d H:i:s'),
            'end' => Carbon::now()->add('1 day')->format('Y-m-d H:i:s'),
            'creation_timestamp' => Carbon::now()->sub('10 minutes')->format('Y-m-d H:i:s')
        ]);
        $I->login($this->userFoodsaver['email']);
        $I->sendDELETE(self::POLLS_API . '/' . $poll['id']);
        $I->seeResponseCodeIs(Http::OK);
        $I->dontSeeInDatabase('fs_poll', [
            'id' => $poll['id']
        ]);
    }

    public function canVoteInScopeFoodsavers(ApiTester $I): void
    {
        $poll = $this->createPoll($I, [-1, 0, 1], [
            $this->userFoodsaver['id'], $this->userFoodsaverUnverified['id'], $this->userAmbassador['id'],
            $this->userStoreManager['id']
        ], [
            'type' => VotingType::THUMB_VOTING,
            'scope' => VotingScope::FOODSAVERS
        ]);
        $this->testCanVote($I, $poll, $this->userFoodsaverUnverified);
        $this->testCanVote($I, $poll, $this->userFoodsaver);
        $this->testCanVote($I, $poll, $this->userStoreManager);
        $this->testCanVote($I, $poll, $this->userAmbassador);
    }

    public function canVoteInScopeVerifiedFoodsavers(ApiTester $I): void
    {
        $poll = $this->createPoll($I, [-1, 0, 1], [
            $this->userFoodsaver['id'], $this->userAmbassador['id'], $this->userStoreManager['id']
        ], [
            'type' => VotingType::THUMB_VOTING,
            'scope' => VotingScope::VERIFIED_FOODSAVERS
        ]);
        $this->testCanVote($I, $poll, $this->userFoodsaverUnverified, false);
        $this->testCanVote($I, $poll, $this->userFoodsaver);
        $this->testCanVote($I, $poll, $this->userStoreManager);
        $this->testCanVote($I, $poll, $this->userAmbassador);
    }

    public function canVoteInScopeStoreManagers(ApiTester $I): void
    {
        $poll = $this->createPoll($I, [-1, 0, 1], [$this->userStoreManager['id']], [
            'type' => VotingType::THUMB_VOTING,
            'scope' => VotingScope::STORE_MANAGERS
        ]);
        $this->testCanVote($I, $poll, $this->userFoodsaverUnverified, false);
        $this->testCanVote($I, $poll, $this->userFoodsaver, false);
        $this->testCanVote($I, $poll, $this->userStoreManager);
        $this->testCanVote($I, $poll, $this->userAmbassador, false);
    }

    public function canVoteInScopeAmbassadors(ApiTester $I): void
    {
        $poll = $this->createPoll($I, [-1, 0, 1], [$this->userAmbassador['id']], [
            'type' => VotingType::THUMB_VOTING,
            'scope' => VotingScope::AMBASSADORS
        ]);
        $this->testCanVote($I, $poll, $this->userFoodsaverUnverified, false);
        $this->testCanVote($I, $poll, $this->userFoodsaver, false);
        $this->testCanVote($I, $poll, $this->userStoreManager, false);
        $this->testCanVote($I, $poll, $this->userAmbassador);
    }

    public function canCreatePoll(ApiTester $I): void
    {
        $data = $this->createRandomPollData();

        // Fail without login
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST(self::POLLS_API, $data);
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);

        // Succeed with login
        $I->login($this->userFoodsaver['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST(self::POLLS_API, $data);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $pollId = $I->grabDataFromResponseByJsonPath('id')[0];
        $I->seeInDatabase('fs_poll', [
            'region_id' => $data['regionId'],
            'name' => $data['name'],
            'description' => $data['description'],
            'scope' => $data['scope'],
            'type' => $data['type'],
            'start' => $data['startDate'],
            'end' => $data['endDate'],
            'author' => $this->userFoodsaver['id'],
            'votes' => 0,
            'shuffle_options' => false
        ]);
        foreach ($data['options'] as $option) {
            $I->seeInDatabase('fs_poll_has_options', [
                'poll_id' => $pollId,
                'option_text' => $option
            ]);
        }
    }

    public function canEditPoll(ApiTester $I): void
    {
        // Create a poll that has not yet started
        $futurePoll = $this->createPoll($I, [1], [
            $this->userFoodsaver['id'], $this->userFoodsaverUnverified['id'], $this->userAmbassador['id'], $this->userStoreManager['id']
        ], [
            'type' => VotingType::SELECT_ONE_CHOICE,
            'start' => $this->faker->dateTimeBetween('+1 days', '+7 days')->format('Y-m-d H:i:s'),
            'end' => $this->faker->dateTimeBetween('+8 day', '+10 days')->format('Y-m-d H:i:s'),
        ]);
        $newData = [
            'name' => $this->faker->text(30),
            'description' => $this->faker->realText(500),
            'shuffleOptions' => true,
            'options' => [],
        ];
        $numOptions = $this->faker->numberBetween(3, 10);
        for ($i = 0; $i < $numOptions; ++$i) {
            $newData['options'][] = $this->faker->text(30);
        }

        // Fail without login
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::POLLS_API . '/' . $futurePoll['id'], $newData);
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);

        // Succeed with login
        $I->login($this->userFoodsaver['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::POLLS_API . '/' . $futurePoll['id'], $newData);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeInDatabase('fs_poll', [
            'id' => $futurePoll['id'],
            'name' => $newData['name'],
            'description' => $newData['description'],
            'shuffle_options' => true
        ]);
        foreach ($newData['options'] as $option) {
            $I->seeInDatabase('fs_poll_has_options', [
                'poll_id' => $futurePoll['id'],
                'option_text' => $option
            ]);
        }
    }

    private function createPoll(ApiTester $I, array $values, array $voterIds, array $params = []): array
    {
        $poll = $I->createPoll($this->region['id'], $this->userFoodsaver['id'], $params);
        foreach (range(0, 3) as $_) {
            $I->createPollOption($poll['id'], $values);
        }
        $I->addVoters($poll['id'], $voterIds);

        return $poll;
    }

    private function testCanVote(ApiTester $I, array $poll, array $user, bool $canVote = true): void
    {
        $I->login($user['email']);
        $I->sendPOST(self::POLLS_API . '/' . $poll['id'] . '/vote', [
            'options' => [0 => 0, 1 => 0, 2 => 0, 3 => 0]
        ]);
        $I->seeResponseCodeIs($canVote ? Http::OK : Http::FORBIDDEN);
    }

    /**
     * Creates an array containing a random poll for sending to the API.
     */
    private function createRandomPollData(): array
    {
        $data = [
            'name' => $this->faker->text(30),
            'description' => $this->faker->realText(500),
            'startDate' => $this->faker->dateTimeBetween('+1 days', '+3 days')->format('Y-m-d H:i:s'),
            'endDate' => $this->faker->dateTimeBetween('+7 days', '+14 days')->format('Y-m-d H:i:s'),
            'regionId' => $this->region['id'],
            'scope' => VotingScope::FOODSAVERS,
            'type' => $this->faker->randomElement(range(VotingType::SELECT_ONE_CHOICE, VotingType::THUMB_VOTING)),
            'options' => [],
            'notifyVoters' => false,
            'shuffleOptions' => false,
        ];
        $numOptions = $this->faker->numberBetween(3, 10);
        for ($i = 0; $i < $numOptions; ++$i) {
            $data['options'][] = $this->faker->text(30);
        }

        return $data;
    }
}
