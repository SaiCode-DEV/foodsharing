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
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Throwable;

class SearchRestController extends AbstractFoodsharingRestController
{
    private readonly SearchGateway $searchGateway;
    private readonly SearchTransactions $searchTransactions;
    private readonly ForumPermissions $forumPermissions;
    private readonly SearchPermissions $searchPermissions;

    public function __construct(
        Session $session,
        SearchGateway $searchGateway,
        SearchTransactions $searchTransactions,
        ForumPermissions $forumPermissions,
        SearchPermissions $searchPermissions,
    ) {
        parent::__construct($session);

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
    public function listUserResults(ParamFetcher $paramFetcher): Response
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

        return $this->respondOK($users);
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
    public function search(ParamFetcher $paramFetcher): Response
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
        } catch (Throwable) {
        }

        $results = $this->searchTransactions->search($query, $global);

        return $this->respondOK($results);
    }

    /**
     * Returns search index for quick local search of likely searched entities.
     */
    #[OA\Tag(name: 'search')]
    #[Rest\Get('search/index')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: MixedSearchResult::class))]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    public function searchIndex(): Response
    {
        $this->assertLoggedIn();
        $results = $this->searchTransactions->searchIndex();

        return $this->respondOK($results);
    }

    /**
     * Search in the titles of forum threads in a specific group.
     */
    #[OA\Tag(name: 'search')]
    #[Rest\Get('search/forum/{groupId}/{subforumId}', requirements: ['groupId' => "\d+", 'subforumId' => "\d+"])]
    #[OA\Parameter(name: 'groupId', in: 'path', schema: new OA\Schema(type: 'integer'), description: 'Which forum to return threads for (region or group)')]
    #[OA\Parameter(name: 'subforumId', in: 'path', schema: new OA\Schema(type: 'integer'), description: 'ID of the forum in the group (normal or ambassador forum)')]
    #[Rest\QueryParam(name: 'q', description: 'Search query')]
    #[Rest\QueryParam(name: 'searchBody', description: 'Search body instead of title', nullable: true, requirements: '^(1|0|true|false)$')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(ref: new Model(type: ThreadSearchResult::class))
    ))]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to search in that forum')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'No query provided')]
    public function searchForumTitle(int $groupId, int $subforumId, ParamFetcher $paramFetcher): Response
    {
        $this->assertLoggedIn();
        if (!$this->forumPermissions->mayAccessForum($groupId, $subforumId)) {
            throw new AccessDeniedHttpException();
        }
        $query = $this->getQuery($paramFetcher);
        $searchBody = ($paramFetcher->get('searchBody')) ? (bool)$paramFetcher->get('searchBody') : false;

        $disableRegionCheck = $this->forumPermissions->maySearchEveryForum();
        $results = $this->searchGateway->searchThreads($query, $this->session->id(), $groupId, $subforumId, $disableRegionCheck, $searchBody);

        return $this->respondOK($results);
    }

    private function getQuery(ParamFetcher $paramFetcher): string
    {
        $query = $paramFetcher->get('q');
        if (empty($query)) {
            throw new BadRequestHttpException('No query provided');
        }

        return $query;
    }
}
