<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\RestApi\Models\IDList;
use FOS\RestBundle\Controller\Annotations as Rest;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[OA\Tag(name: 'bells')]
class BellRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        private readonly BellGateway $bellGateway,
        protected Session $session
    ) {
        parent::__construct($this->session);
    }

    #[OA\Get(summary: 'Returns all bells for the current user.')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Successful')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to list bells')]
    #[Rest\Get('bells')]
    #[Rest\QueryParam(name: 'limit', requirements: '\d+', default: '20', description: 'How many bells to return.')]
    #[Rest\QueryParam(name: 'offset', requirements: '\d+', default: '0', description: 'Offset for returned bells.')]
    public function listBells(#[MapQueryParameter] int $limit = 20, #[MapQueryParameter] int $offset = 0): Response
    {
        $this->assertLoggedIn();

        $bells = $this->bellGateway->listBells($this->session->id(), $limit, $offset);

        return $this->respondOK($bells);
    }

    #[OA\Patch(summary: 'Marks one or more bells as read.')]
    #[OA\Response(response: Response::HTTP_OK, description: 'At least one of the bells was successfully marked.')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'The list of IDs is empty or none of the bells could be marked.')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to change the bells')]
    #[Rest\Patch('bells')]
    public function markBellsAsRead(#[MapRequestPayload] IDList $bellIds): Response
    {
        $this->assertLoggedIn();

        $changed = $this->bellGateway->setBellsAsSeen($bellIds->ids, $this->session->id());
        if (!$changed) {
            throw new BadRequestHttpException();
        }

        return $this->respondOK(['marked' => $changed]);
    }

    #[OA\Delete(summary: 'Deletes a bell.')]
    #[OA\Response(response: Response::HTTP_OK, description: 'At least one of the bells was successfully deleted')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'The list of IDs is empty or none of the bells could be deleted')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'The user does not have a bell with that ID')]
    #[Rest\Delete('bells')]
    #[Rest\RequestParam(name: 'ids', description: 'which bell to delete')]
    public function deleteBells(#[MapRequestPayload] IDList $bellIds): Response
    {
        $this->assertLoggedIn();

        $deleted = $this->bellGateway->delBellsForFoodsaver($bellIds->ids, $this->session->id());
        if (!$deleted) {
            throw new NotFoundHttpException();
        }

        return $this->respondOK(['deleted' => $deleted]);
    }
}
