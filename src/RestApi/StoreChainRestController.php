<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\Pagination;
use Foodsharing\Modules\Store\DTO\MinimalStoreIdentifier;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Modules\StoreChain\DTO\PatchStoreChain;
use Foodsharing\Modules\StoreChain\DTO\StoreChain;
use Foodsharing\Modules\StoreChain\DTO\StoreChainForChainList;
use Foodsharing\Modules\StoreChain\StoreChainGateway;
use Foodsharing\Modules\StoreChain\StoreChainTransactionException;
use Foodsharing\Modules\StoreChain\StoreChainTransactions;
use Foodsharing\Permissions\StoreChainPermissions;
use Foodsharing\RestApi\Models\StoreChain\CreateStoreChainModel;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class StoreChainRestController extends AbstractFOSRestController
{
    // literal constants
    private const NOT_LOGGED_IN = 'not logged in';

    public function __construct(
        private readonly Session $session,
        private readonly StoreGateway $storeGateway,
        private readonly StoreChainGateway $gateway,
        private readonly StoreChainTransactions $transactions,
        private readonly StoreChainPermissions $permissions
    ) {
    }

    /**
     * Returns the list of store chains.
     *
     * @OA\Tag(name="chain")
     * @OA\Response(
     * 		response="200",
     * 		description="Success.",
     *      @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=StoreChainForChainList::class))
     *      )
     * )
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Insufficient permissions")
     */
    #[Rest\Get('chains')]
    #[Rest\QueryParam(name: 'pageSize', description: 'Count of chains on page', requirements: '\d+', default: 0, strict: true)]
    #[Rest\QueryParam(name: 'offset', description: 'Offset of items', requirements: '\d+', default: 0, strict: true)]
    public function getStoreChains(ParamFetcher $paramFetcher): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException(self::NOT_LOGGED_IN);
        }
        if (!$this->permissions->maySeeChainList()) {
            throw new AccessDeniedHttpException();
        }

        $pagination = new Pagination();
        $pagination->pageSize = $paramFetcher->get('pageSize');
        $pagination->offset = $paramFetcher->get('offset');

        return $this->handleView($this->view($this->transactions->getStoreChains(null, $this->permissions->maySeeChainDetails(), $pagination), 200));
    }

    /**
     * Returns a specific store chain.
     *
     * @OA\Tag(name="chain")
     * @OA\Response(
     * 		response="200",
     * 		description="Success.",
     *      @Model(type=StoreChainForChainList::class)
     * )
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Insufficient permissions")
     */
    #[Rest\Get('chains/{chainId}', requirements: ['chainId' => '\d+'])]
    public function getStoreChain(int $chainId): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException(self::NOT_LOGGED_IN);
        }
        if (!$this->permissions->maySeeChainList()) {
            throw new AccessDeniedHttpException();
        }

        $chain = $this->transactions->getStoreChains($chainId, $this->permissions->maySeeChainDetails($chainId));
        if (empty($chain)) {
            throw new NotFoundHttpException('Requested store chain not found.');
        }

        return $this->handleView($this->view($chain[0], 200));
    }

    /**
     * Creates a new store.
     * The name must not be empty. All other parameters are
     * optional. Returns the created store chain.
     *
     * @OA\Tag(name="chain")
     * @OA\RequestBody(@Model(type=StoreChain::class))
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Insufficient permissions")
     */
    #[Rest\Post('chains')]
    #[ParamConverter('storeModel', converter: 'fos_rest.request_body')]
    public function createChain(CreateStoreChainModel $storeModel, ConstraintViolationListInterface $validationErrors): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException(self::NOT_LOGGED_IN);
        }
        if (!$this->permissions->mayCreateChain()) {
            throw new AccessDeniedHttpException();
        }

        $this->throwBadRequestExceptionOnError($validationErrors);
        try {
            $id = $this->transactions->addStoreChain($storeModel->toCreateStore());
        } catch (StoreChainTransactionException $ex) {
            throw new BadRequestException($ex->getMessage());
        }

        return $this->handleView($this->view($this->gateway->getStoreChains($id)[0], 201));
    }

    /**
     * Updates a store.
     *
     * @OA\Tag(name="chain")
     * @OA\RequestBody(@Model(type=PatchStoreChain::class))
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Insufficient permissions")
     * @OA\Response(response="404", description="Chain does not exist")
     */
    #[Rest\Patch('chains/{chainId}', requirements: ['chainId' => '\d+'])]
    #[ParamConverter('storeModel', converter: 'fos_rest.request_body')]
    public function updateChain($chainId, PatchStoreChain $storeModel, ConstraintViolationListInterface $validationErrors): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException(self::NOT_LOGGED_IN);
        }
        if (!$this->gateway->chainExists($chainId)) {
            throw new NotFoundHttpException('chain does not exist');
        }
        if (!$this->permissions->mayEditChain($chainId)) {
            throw new AccessDeniedHttpException();
        }

        $this->throwBadRequestExceptionOnError($validationErrors);

        if (!$this->gateway->chainExists($chainId)) {
            throw new NotFoundHttpException('Chain does not exists');
        }

        try {
            $updateKams = $this->permissions->mayEditKams($chainId);
            $changed = $this->transactions->updateStoreChain($chainId, $storeModel, $updateKams);
            if ($changed) {
                return $this->handleView($this->view($this->gateway->getStoreChains($chainId)[0]));
            } else {
                throw new BadRequestException('No information changed.');
            }
        } catch (StoreChainTransactionException $ex) {
            throw new BadRequestException($ex->getMessage());
        }
    }

    /**
     * Returns the list of stores that are part of a given chain.
     *
     * @OA\Tag(name="chain")
     * @OA\Response(
     * 		response="200",
     * 		description="Success.",
     *      @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=MinimalStoreIdentifier::class))
     *      )
     * )
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Insufficient permissions")
     */
    #[Rest\QueryParam(name: 'pageSize', description: 'Count of chains on page', requirements: '\d+', default: 0, strict: true)]
    #[Rest\QueryParam(name: 'offset', description: 'Offset of items', requirements: '\d+', default: 0, strict: true)]
    #[Rest\Get('chains/{chainId}/stores', requirements: ['chainId' => '\d+'])]
    public function getChainStores(int $chainId, ParamFetcher $paramFetcher): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException(self::NOT_LOGGED_IN);
        }
        if (!$this->permissions->maySeeChainStores($chainId)) {
            throw new AccessDeniedHttpException();
        }

        if (!$this->gateway->chainExists($chainId)) {
            throw new NotFoundHttpException('Chain does not exists');
        }

        $pagination = new Pagination();
        $pagination->pageSize = $paramFetcher->get('pageSize');
        $pagination->offset = $paramFetcher->get('offset');

        return $this->handleView($this->view($this->storeGateway->findAllStoresOfStoreChain($chainId, $pagination), 200));
    }

    /**
     * Check if a Constraint violation is found and if it exist it throws an BadRequestExeption.
     *
     * @param ConstraintViolationListInterface $errors Validation result
     *
     * @throws BadRequestHttpException if violation is detected
     */
    private function throwBadRequestExceptionOnError(ConstraintViolationListInterface $errors): void
    {
        if ($errors->count() > 0) {
            $firstError = $errors->get(0);
            $relevantErrorContent = ['field' => $firstError->getPropertyPath(), 'message' => $firstError->getMessage()];
            throw new BadRequestHttpException(json_encode($relevantErrorContent));
        }
    }
}
