<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Store\DTO\CommonLabel;
use Foodsharing\Modules\StoreCategories\StoreCategoriesGateway;
use Foodsharing\Permissions\StoreCategoriesPermissions;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'storecategories')]
class StoreCategoriesRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        protected Session $session,
        private readonly StoreCategoriesPermissions $storeCategoriesPermissions,
        private readonly StoreCategoriesGateway $storeCategoriesGateway,
    ) {
        parent::__construct($this->session);
    }

    #[OA\Get(summary: 'Returns all existing store categories')]
    #[Rest\Get(path: 'storecategories')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new OA\JsonContent(
        type: 'array',
        items: new OA\Items(ref: new Model(type: CommonLabel::class))
    ))]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
    public function getStoreCategories(): Response
    {
        $this->assertLoggedIn();
        $categories = $this->storeCategoriesGateway->getStoreCategories();

        return $this->respondOK($categories);
    }

    #[OA\Post(summary: 'Adds a store category')]
    #[Rest\Post(path: 'storecategories')]
    #[ParamConverter('category', converter: 'fos_rest.request_body')]
    #[OA\RequestBody(content: new Model(type: CommonLabel::class))]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new OA\JsonContent(
        ref: new Model(type: CommonLabel::class)
    ))]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid parameters')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to add store categories')]
    public function addStoreCategory(CommonLabel $category, ValidatorInterface $validator): Response
    {
        $this->assertLoggedIn();
        $this->assertThereAreNoValidationErrors($validator, $category);

        if (!$this->storeCategoriesPermissions->mayEditStoreCategories()) {
            throw new AccessDeniedHttpException();
        }

        $category->id = $this->storeCategoriesGateway->addStoreCategory($category);

        return $this->respondOK($category);
    }

    #[OA\Patch(summary: 'Changes a store category')]
    #[Rest\Patch('storecategories/{id}')]
    #[ParamConverter('category', converter: 'fos_rest.request_body')]
    #[OA\RequestBody(content: new Model(type: CommonLabel::class))]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid parameters')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to edit store categories')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Store category does not exist')]
    public function updateStoreCategory(int $id, CommonLabel $category, ValidatorInterface $validator): Response
    {
        $this->assertLoggedIn();
        $this->assertThereAreNoValidationErrors($validator, $category);

        if (!$this->storeCategoriesPermissions->mayEditStoreCategories()) {
            throw new AccessDeniedHttpException();
        }
        if (!$this->storeCategoriesGateway->getStoreCategory($id)) {
            throw new NotFoundHttpException('store category does not exist');
        }

        $category->id = $id;
        $this->storeCategoriesGateway->updateStoreCategory($category);

        return $this->respondOK();
    }

    #[OA\Patch(summary: 'Deletes a store category')]
    #[Rest\Delete('storecategories/{id}')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to delete store categories')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Store category does not exist')]
    public function deleteStoreCategory(int $id): Response
    {
        $this->assertLoggedIn();

        if (!$this->storeCategoriesPermissions->mayEditStoreCategories()) {
            throw new AccessDeniedHttpException();
        }
        if (!$this->storeCategoriesGateway->getStoreCategory($id)) {
            throw new NotFoundHttpException('store category does not exist');
        }

        $this->storeCategoriesGateway->deleteStoreCategory($id);

        return $this->respondOK();
    }
}
