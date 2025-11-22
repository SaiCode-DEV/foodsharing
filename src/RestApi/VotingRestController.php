<?php

namespace Foodsharing\RestApi;

use Carbon\Carbon;
use DateTimeZone;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Voting\VotingScope;
use Foodsharing\Modules\Core\DBConstants\Voting\VotingType;
use Foodsharing\Modules\Voting\DTO\Poll;
use Foodsharing\Modules\Voting\DTO\PollOption;
use Foodsharing\Modules\Voting\VotingGateway;
use Foodsharing\Modules\Voting\VotingTransactions;
use Foodsharing\Permissions\VotingPermissions;
use Foodsharing\RestApi\Models\Voting\CreatePollRequest;
use Foodsharing\RestApi\Models\Voting\EditPollRequest;
use Foodsharing\RestApi\Models\Voting\VoteRequest;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'polls')]
class VotingRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        protected Session $session,
        private readonly VotingGateway $votingGateway,
        private readonly VotingPermissions $votingPermissions,
        private readonly VotingTransactions $votingTransactions)
    {
        parent::__construct($this->session);
    }

    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permission to see the poll')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Poll does not exist.')]
    #[Route('/polls/{pollId}', requirements: ['pollId' => Requirement::POSITIVE_INT], methods: ['GET'])]
    #[OA\Get(summary: 'Returns the details of a poll.')]
    public function getPoll(int $pollId): Response
    {
        $this->assertLoggedIn();

        $poll = $this->votingTransactions->getPoll($pollId, true);
        if (is_null($poll)) {
            throw new NotFoundHttpException();
        }

        if (!$this->votingPermissions->maySeePoll($poll)) {
            throw new AccessDeniedHttpException();
        }

        return $this->respondOK($poll);
    }

    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permission to list polls in the group')]
    #[Route('/groups/{groupId}/polls', requirements: ['groupId' => Requirement::POSITIVE_INT], methods: ['GET'])]
    #[OA\Get(summary: 'Lists all polls in a region or working group.')]
    public function listPolls(int $groupId): Response
    {
        $this->assertLoggedIn();

        if (!$this->votingPermissions->mayListPolls($groupId)) {
            throw new AccessDeniedHttpException();
        }

        $polls = $this->votingGateway->listPolls($groupId, $this->session->id());

        return $this->respondOK($polls);
    }

    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[Route('/user/current/polls', methods: ['GET'])]
    #[OA\Get(summary: 'Lists all polls the user is invited to.')]
    public function listCurrentPolls(): Response
    {
        $this->assertLoggedIn();

        $polls = $this->votingGateway->listCurrentPolls($this->session->id());

        return $this->respondOK($polls);
    }

    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid options.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to vote in that polls.')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Poll does not exist.')]
    #[Route('/polls/{pollId}/vote', requirements: ['pollId' => Requirement::POSITIVE_INT], methods: ['PUT'])]
    #[OA\Put(summary: 'Vote in a poll. The options need to be a list mapping option indices to the vote values (+1, 0, -1). Depending on the voting type not all options need to be included.')]
    public function vote(int $pollId, #[MapRequestPayload] VoteRequest $request): Response
    {
        $this->assertLoggedIn();

        // check if poll exists and user may vote
        $poll = $this->votingGateway->getPoll($pollId, false);
        if (is_null($poll)) {
            throw new NotFoundHttpException();
        }

        if (!$this->votingPermissions->mayVote($poll)) {
            throw new AccessDeniedHttpException();
        }

        // convert option indices to integers to avoid type problems
        $options = array_combine(array_map('intval', array_keys($request->options)),
            array_map('intval', array_values($request->options)));

        // check if voting options are valid
        if (!$this->votingTransactions->vote($poll, $options)) {
            throw new BadRequestHttpException();
        }

        return $this->respondOK();
    }

    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid parameters.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to create a poll in that region.')]
    #[Route('/polls', methods: ['POST'])]
    #[OA\Post(summary: 'Creates a new poll. The poll and all its options will be assigned valid IDs and option indices by the server. Options must be passed as an array of strings for the options\' texts. The order of the options will be kept.')]
    public function createPoll(#[MapRequestPayload] CreatePollRequest $request): Response
    {
        $this->assertLoggedIn();

        // parse and check parameters
        $poll = new Poll();
        $poll->name = trim($request->name);
        $poll->description = trim($request->description);
        // Set time zone to make sure dates are saved to db in the server time zone
        $serverTimeZone = new DateTimeZone('Europe/Berlin');
        $poll->startDate = $request->startDate->setTimezone($serverTimeZone);
        $poll->endDate = $request->endDate->setTimezone($serverTimeZone);
        if ($poll->startDate >= $poll->endDate
            || Carbon::now()->add($this->votingPermissions->MIN_POLL_EDIT_TIME) >= $poll->startDate) {
            throw new BadRequestHttpException('invalid start or end date');
        }

        $poll->scope = $request->scope;
        if (!VotingScope::isValidScope($poll->scope)) {
            throw new BadRequestHttpException('invalid scope');
        }
        $poll->type = $request->type;
        if (!VotingType::isValidType($poll->type)) {
            throw new BadRequestHttpException('invalid poll type');
        }

        $poll->regionId = $request->regionId;
        if (!$this->votingPermissions->mayCreatePoll($poll->regionId)) {
            throw new AccessDeniedHttpException();
        }

        $poll->authorId = $this->session->id();

        // parse options and check that they are not empty
        $poll->options = $this->parseOptions($request->options);
        $poll->shuffleOptions = $request->shuffleOptions;

        // create poll
        $this->votingTransactions->createPoll($poll, $request->notifyVoters);

        return $this->respondOK($poll);
    }

    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid parameters')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to edit that poll')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Poll does not exist')]
    #[Route('/polls/{pollId}', requirements: ['pollId' => Requirement::POSITIVE_INT], methods: ['PATCH'])]
    #[OA\Patch(summary: 'Updates an existing poll. This can change a poll\'s title, description, and options. Updating is only possible within one hour after creation.')]
    public function editPoll(int $pollId, #[MapRequestPayload] EditPollRequest $request): Response
    {
        $this->assertLoggedIn();

        $poll = $this->votingGateway->getPoll($pollId, false);
        if (is_null($poll)) {
            throw new NotFoundHttpException();
        }

        if (!$this->votingPermissions->mayEditPoll($poll)) {
            throw new AccessDeniedHttpException();
        }

        // check name and description
        if (!empty($request->name)) {
            $poll->name = trim($request->name);
        }
        if (!empty($request->description)) {
            $poll->description = trim($request->description);
        }

        // parse options and check that they are not empty
        if (!empty($request->options)) {
            $poll->options = $this->parseOptions($request->options);
        }

        // update poll
        $poll->shuffleOptions = $request->shuffleOptions;
        $this->votingTransactions->updatePoll($poll);

        return $this->respondOK($poll);
    }

    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to delete that poll.')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Poll does not exist.')]
    #[Route('/polls/{pollId}', requirements: ['pollId' => Requirement::POSITIVE_INT], methods: ['DELETE'])]
    #[OA\Delete(summary: 'Deletes a poll.')]
    public function deletePoll(int $pollId): Response
    {
        $this->assertLoggedIn();

        $poll = $this->votingGateway->getPoll($pollId, false);
        if (is_null($poll)) {
            throw new NotFoundHttpException();
        }

        if (!$this->votingPermissions->mayEditPoll($poll)) {
            throw new AccessDeniedHttpException();
        }

        $this->votingTransactions->deletePoll($pollId);

        return $this->respondOK();
    }

    /**
     * Parses poll options from a request and returns them as {@see PollOption} objects. Throws exceptions if
     * the list is empty or if any option does not have a valid text.
     *
     * @param string[] $data
     * @return PollOption[]
     * @throws BadRequestHttpException if at least one of the options is empty or if not all options are unique
     */
    private function parseOptions(array $data): array
    {
        $options = array_map(function ($x) {
            $o = new PollOption();
            $o->text = trim($x);

            return $o;
        }, $data);
        if (empty($options)) {
            throw new BadRequestHttpException('poll does not have any options');
        }

        // check that no option text is empty
        foreach ($options as $option) {
            if (empty($option->text)) {
                throw new BadRequestHttpException('option text must not be empty');
            }
        }

        // check that no two option texts are equal
        $texts = array_map(fn ($o) => $o->text, $options);
        if (sizeof(array_unique($texts)) != sizeof($texts)) {
            throw new BadRequestHttpException('poll options must not have the same text');
        }

        return $options;
    }
}
