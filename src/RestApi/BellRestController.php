<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\BellForList;
use Foodsharing\Modules\Core\Pagination;
use Foodsharing\RestApi\Models\IDList;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'bells')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
class BellRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        private readonly BellGateway $bellGateway,
        protected Session $session
    ) {
        parent::__construct($this->session);
    }

    #[OA\Get(summary: 'Returns all bells for the current user')]
    #[Route('bells', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(ref: new Model(type: BellForList::class))
    ))]
    public function listBells(#[MapQueryParameter] ?int $limit, #[MapQueryParameter] ?int $offset): Response
    {
        $this->assertLoggedIn();

        $pagination = Pagination::create($limit, $offset);
        $bells = $this->bellGateway->listBells($this->session->id(), $pagination);

        return $this->respondOK($bells);
    }

    #[OA\Patch(summary: 'Sets the read status of one or more bells as unread/read')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Bells were successfully marked or already had that status.')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'The user does not have a bell with that ID')]
    #[Route('bells/readStatus', methods: ['PATCH'])]
    public function setBellReadStatus(
        #[MapRequestPayload] IDList $bellIds,
        #[MapQueryParameter(options: ['min_range' => 0, 'max_range' => 1])] int $isRead
    ): Response {
        $this->assertLoggedIn();

        if (!$this->bellGateway->doesFoodsaverHaveBells($bellIds->ids, $this->session->id())) {
            throw new NotFoundHttpException('You don\'t have these bells.');
        }
        $this->bellGateway->setReadStatus($bellIds->ids, $this->session->id(), $isRead);

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Deletes bells for the current user')]
    #[OA\Response(response: Response::HTTP_OK, description: 'At least one of the bells was successfully deleted')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'The user does not have a bell with that ID')]
    #[Route('bells', methods: ['DELETE'])]
    public function deleteBells(#[MapRequestPayload] IDList $bellIds): Response
    {
        $this->assertLoggedIn();

        $deleted = $this->bellGateway->delBellsForFoodsaver($bellIds->ids, $this->session->id());
        if (!$deleted) {
            throw new NotFoundHttpException('You don\'t have these bells.');
        }

        return $this->respondOK();
    }
}
