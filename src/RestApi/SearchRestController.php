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
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'search')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
class SearchRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        protected Session $session,
        private readonly SearchGateway $searchGateway,
        private readonly SearchTransactions $searchTransactions,
        private readonly ForumPermissions $forumPermissions,
        private readonly SearchPermissions $searchPermissions,
    ) {
        parent::__construct($session);
    }

    #[OA\Get(summary: 'Search for users')]
    #[Route('search/users', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(ref: new Model(type: SimplifiedUserSearchResult::class))
    ))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to search in that region')]
    public function listUserResults(
        #[MapQueryParameter('q', filter: \FILTER_VALIDATE_REGEXP, options: ['regexp' => '/^.+$/'])] string $query,
        #[MapQueryParameter(options: ['min_range' => 1])] ?int $regionId,
    ): Response {
        $this->assertLoggedIn();
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

    #[OA\Get(summary: 'General search endpoint', description: 'Returns all kinds of searchable entry types')]
    #[Route('search/all', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: MixedSearchResult::class))]
    public function search(
        #[MapQueryParameter('q', filter: \FILTER_VALIDATE_REGEXP, options: ['regexp' => '/^.+$/'])] string $query,
        #[MapQueryParameter] ?bool $global,
    ): Response {
        $this->assertLoggedIn();
        $global ??= false;

        $results = $this->searchTransactions->search($query, $global);

        return $this->respondOK($results);
    }

    #[OA\Get(summary: 'Search index for quick local search of likely searched entities')]
    #[Route('search/index', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: MixedSearchResult::class))]
    public function searchIndex(): Response
    {
        $this->assertLoggedIn();
        $results = $this->searchTransactions->searchIndex();

        return $this->respondOK($results);
    }

    #[OA\Get(summary: 'Search threads in a specific forum')]
    #[Route('search/regions/{regionId}/forum', methods: ['GET'], requirements: ['regionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(ref: new Model(type: ThreadSearchResult::class))
    ))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to search in that forum')]
    public function searchForumTitle(
        int $regionId,
        #[MapQueryParameter('q', filter: \FILTER_VALIDATE_REGEXP, options: ['regexp' => '/^.+$/'])] string $query,
        #[MapQueryParameter('searchBody')] ?bool $searchBody,
        #[MapQueryParameter(options: ['min_range' => 0])] ?int $subforumId,
    ): Response {
        $this->assertLoggedIn();
        $searchBody ??= false;
        $subforumId ??= 0;
        if (!$this->forumPermissions->mayAccessForum($regionId, $subforumId)) {
            throw new AccessDeniedHttpException('Insufficient permissions to search in that forum');
        }

        $disableRegionCheck = $this->forumPermissions->maySearchEveryForum();
        $results = $this->searchGateway->searchThreads($query, $this->session->id(), $regionId, $subforumId, $disableRegionCheck, $searchBody);

        return $this->respondOK($results);
    }
}
