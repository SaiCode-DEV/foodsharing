<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Search\DTO\MixedSearchResult;
use Foodsharing\Modules\Search\DTO\SimplifiedUserSearchResult;
use Foodsharing\Modules\Search\DTO\ThreadSearchResult;
use Foodsharing\Modules\Search\SearchGateway;
use Foodsharing\Modules\Search\SearchTransactions;
use Foodsharing\Permissions\ForumPermissions;
use Foodsharing\Permissions\SearchPermissions;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Throwable;

class SearchRestController extends AbstractFOSRestController
{
    private Session $session;
    private SearchGateway $searchGateway;
    private SearchTransactions $searchTransactions;
    private ForumPermissions $forumPermissions;
    private SearchPermissions $searchPermissions;

    public function __construct(
        Session $session,
        SearchGateway $searchGateway,
        SearchTransactions $searchTransactions,
        ForumPermissions $forumPermissions,
        SearchPermissions $searchPermissions,
    ) {
        $this->session = $session;
        $this->searchGateway = $searchGateway;
        $this->searchTransactions = $searchTransactions;
        $this->forumPermissions = $forumPermissions;
        $this->searchPermissions = $searchPermissions;
    }

    /**
     * Search for users.
     */
    #[OA\Tag(name: 'search')]
    #[Rest\Get('search/user')]
    #[Rest\QueryParam(name: 'q', description: 'Search query')]
    #[Rest\QueryParam(name: 'regionId', requirements: "\d+", nullable: true, description: 'Restricts the search to a region')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(ref: new Model(type: SimplifiedUserSearchResult::class))
    ))]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'No query provided')]
    public function listUserResultsAction(ParamFetcher $paramFetcher): Response
    {
        $this->assertLoggedIn();
        $query = $this->getQuery($paramFetcher);
        $regionId = $paramFetcher->get('regionId');
        $maySearchByEmailAddress = $this->searchPermissions->maySearchByEmailAddress();

        if (!$regionId) {
            $users = $this->searchGateway->searchUsers($query, $this->session->id(), false, $maySearchByEmailAddress);
        } elseif (!$this->searchPermissions->maySearchInRegion($regionId)) {
            throw new AccessDeniedHttpException('insufficient permissions to search in that region');
        } else {
            $users = $this->searchGateway->searchUsersGlobal($query, $regionId, false, false);
        }

        $users = array_map(fn ($user) => SimplifiedUserSearchResult::fromUserSearchResult($user), $users);

        return $this->handleView($this->view($users, Response::HTTP_OK));
    }

    /**
     * General search endpoint that returns all kinds of searchable entry types.
     *
     * This includes foodsavers, stores, regions, working groups, food share points, chats and threads.
     */
    #[OA\Tag(name: 'search')]
    #[Rest\Get('search/all')]
    #[Rest\QueryParam(name: 'q', description: 'Search query')]
    #[Rest\QueryParam(name: 'global', description: 'Search globally')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: MixedSearchResult::class))]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'No query provided')]
    public function searchAction(ParamFetcher $paramFetcher): Response
    {
        $this->assertLoggedIn();
        $query = $this->getQuery($paramFetcher);
        if (empty($query)) {
            throw new BadRequestHttpException();
        }
        $global = false;
        try {
            $paramFetcher->get('global', true);
            $global = true;
        } catch (Throwable $e) {
        }

        $results = $this->searchTransactions->search($query, $global);

        return $this->handleView($this->view($results, 200));
    }

    /**
     * Returns search index for quick local search of likely searched entities.
     */
    #[OA\Tag(name: 'search')]
    #[Rest\Get('search/index')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: MixedSearchResult::class))]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    public function searchIndexAction(): Response
    {
        $this->assertLoggedIn();
        $results = $this->searchTransactions->searchIndex();

        return $this->handleView($this->view($results, 200));
    }

    /**
     * Search in the titles of forum threads in a specific group.
     */
    #[OA\Tag(name: 'search')]
    #[Rest\Get('search/forum/{groupId}/{subforumId}', requirements: ['groupId' => "\d+", 'subforumId' => "\d+"])]
    #[OA\Parameter(name: 'groupId', in: 'path', schema: new OA\Schema(type: 'integer'), description: 'Which forum to return threads for (region or group)')]
    #[OA\Parameter(name: 'subforumId', in: 'path', schema: new OA\Schema(type: 'integer'), description: 'ID of the forum in the group (normal or ambassador forum)')]
    #[Rest\QueryParam(name: 'q', description: 'Search query')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(ref: new Model(type: ThreadSearchResult::class))
    ))]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to search in that forum')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'No query provided')]
    public function searchForumTitleAction(int $groupId, int $subforumId, ParamFetcher $paramFetcher): Response
    {
        $this->assertLoggedIn();
        if (!$this->forumPermissions->mayAccessForum($groupId, $subforumId)) {
            throw new AccessDeniedHttpException();
        }
        $query = $this->getQuery($paramFetcher);

        $disableRegionCheck = $this->forumPermissions->maySearchEveryForum();
        $results = $this->searchGateway->searchThreads($query, $this->session->id(), $groupId, $subforumId, $disableRegionCheck);

        return $this->handleView($this->view($results, 200));
    }

    private function getQuery(ParamFetcher $paramFetcher): string
    {
        $query = $paramFetcher->get('q');
        if (empty($query)) {
            throw new BadRequestHttpException('No query provided');
        }

        return $query;
    }

    private function assertLoggedIn(): void
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('', 'Not logged in');
        }
    }
}
