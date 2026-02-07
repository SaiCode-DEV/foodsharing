<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\Pagination;
use Foodsharing\Modules\Store\DTO\MinimalStoreIdentifier;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Modules\StoreChain\DTO\StoreChainData;
use Foodsharing\Modules\StoreChain\DTO\StoreChainForChainList;
use Foodsharing\Modules\StoreChain\StoreChainGateway;
use Foodsharing\Modules\StoreChain\StoreChainTransactionException;
use Foodsharing\Modules\StoreChain\StoreChainTransactions;
use Foodsharing\Permissions\StoreChainPermissions;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use UnexpectedValueException;

#[OA\Tag('chain')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
#[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Missing permissions')]
class StoreChainRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        protected Session $session,
        private readonly StoreGateway $storeGateway,
        private readonly StoreChainGateway $gateway,
        private readonly StoreChainTransactions $transactions,
        private readonly StoreChainPermissions $permissions
    ) {
        parent::__construct($this->session);
    }

    #[OA\Get(summary: 'Returns the list of store chains')]
    #[Route('chains', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        ref: new Model(type: StoreChainForChainList::class)
    ))]
    public function getStoreChains(#[MapQueryParameter] ?int $limit, #[MapQueryParameter] ?int $offset): Response
    {
        $this->assertLoggedIn();
        if (!$this->permissions->maySeeChainList()) {
            throw new AccessDeniedHttpException('Missing permissions');
        }

        $pagination = (!is_null($limit) && $limit > 0) ? Pagination::create($limit, $offset) : null;

        return $this->respondOK($this->gateway->getStoreChains($pagination));
    }

    #[OA\Get(summary: 'Returns a specific store chain')]
    #[Route('chains/{chainId}', requirements: ['chainId' => Requirement::POSITIVE_INT], methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        ref: new Model(type: StoreChainForChainList::class)
    ))]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Store chain does not exist')]
    public function getStoreChain(int $chainId): Response
    {
        $this->assertLoggedIn();
        if (!$this->permissions->maySeeChainList()) {
            throw new AccessDeniedHttpException('Missing permissions');
        }

        try {
            $chain = $this->gateway->getStoreChain($chainId);
        } catch (UnexpectedValueException $e) {
            throw new NotFoundHttpException('Requested store chain not found.');
        }

        return $this->respondOK($chain);
    }

    #[OA\Post(
        description: 'The name must not be empty. All other parameters are optional. Returns the created store chain.',
        summary: 'Creates a new store'
    )]
    #[Route('chains', methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    public function createChain(#[MapRequestPayload] StoreChainData $storeChainData): Response
    {
        $this->assertLoggedIn();
        if (!$this->permissions->mayCreateChain()) {
            throw new AccessDeniedHttpException('Missing permissions');
        }

        try {
            $id = $this->transactions->addStoreChain($storeChainData);
        } catch (StoreChainTransactionException $ex) {
            throw new BadRequestException($ex->getMessage());
        }

        return $this->respondOK($this->gateway->getStoreChain($id));
    }

    #[OA\Patch(summary: 'Updates a store chain')]
    #[Route('chains/{chainId}', requirements: ['chainId' => Requirement::POSITIVE_INT], methods: ['PATCH'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Store chain does not exist')]
    public function updateChain($chainId, #[MapRequestPayload] StoreChainData $storeChainData): Response
    {
        $this->assertLoggedIn();
        if (!$this->gateway->chainExists($chainId)) {
            throw new NotFoundHttpException('chain does not exist');
        }
        if (!$this->permissions->mayEditChain($chainId)) {
            throw new AccessDeniedHttpException('Missing permissions');
        }

        $updateKams = $this->permissions->mayEditKams($chainId);
        $this->transactions->updateStoreChain($chainId, $storeChainData, $updateKams);

        return $this->respondOK($this->gateway->getStoreChain($chainId));
    }

    #[OA\Get(summary: 'Returns the list of stores that are part of a given chain')]
    #[Route('chains/{chainId}/stores', requirements: ['chainId' => Requirement::POSITIVE_INT], methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        ref: new Model(type: MinimalStoreIdentifier::class)
    ))]
    public function getChainStores(int $chainId, #[MapQueryParameter] ?int $limit, #[MapQueryParameter] ?int $offset): Response
    {
        $this->assertLoggedIn();
        if (!$this->permissions->maySeeChainStores($chainId)) {
            throw new AccessDeniedHttpException('Missing permissions');
        }

        if (!$this->gateway->chainExists($chainId)) {
            throw new NotFoundHttpException('Chain does not exists');
        }

        $pagination = (!is_null($limit) && $limit > 0) ? Pagination::create($limit, $offset) : null;

        return $this->respondOK($this->storeGateway->findAllStoresOfStoreChain($chainId, $pagination));
    }
}
