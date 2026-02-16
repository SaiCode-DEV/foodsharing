<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Categories\CategoriesTransactions;
use Foodsharing\Modules\Categories\Category;
use Foodsharing\Modules\Core\DBConstants\CategoryType;
use Foodsharing\Modules\Store\DTO\CategoryWithType;
use Foodsharing\Permissions\CategoriesPermissions;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'categories')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
#[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid category type')]
class CategoriesRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        protected Session $session,
        private readonly CategoriesPermissions $categoriesPermissions,
        private readonly CategoriesTransactions $categoriesTransactions,
    ) {
        parent::__construct($this->session);
    }

    #[OA\Get(summary: 'Returns all existing categories of the given type')]
    #[Route('categories/{type}', methods: ['GET'], requirements: ['type' => '\w+'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array',
        items: new OA\Items(ref: new Model(type: Category::class))
    ))]
    public function getCategories(string $type): Response
    {
        $this->assertLoggedIn();
        $type = $this->parseCategoryType($type);
        $categories = $this->categoriesTransactions->getCategoriesGateway($type)->getCategoriesWithUsageCounts();

        return $this->respondOK($categories);
    }

    #[OA\Post(summary: 'Adds a category')]
    #[Route('categories/{type}', methods: ['POST'], requirements: ['type' => '\w+'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        ref: new Model(type: CategoryWithType::class)
    ))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function addCategory(string $type, #[MapRequestPayload] CategoryWithType $category): Response
    {
        $this->assertLoggedIn();
        $type = $this->parseCategoryType($type);
        $this->assertHasEditPermissions($type);

        $category->id = $this->categoriesTransactions->getCategoriesGateway($type)->addCategory($category);

        $this->categoriesTransactions->handleTypeSpecificSideEffects($type);

        return $this->respondOK($category);
    }

    #[OA\Patch(summary: 'Changes a category')]
    #[Route('categories/{type}/{id}', methods: ['PATCH'], requirements: ['type' => '\w+', 'id' => Requirement::POSITIVE_INT])]
    #[ParamConverter('category', converter: 'fos_rest.request_body')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Category doesn\'t exist')]
    public function updateCategory(string $type, int $id, #[MapRequestPayload] CategoryWithType $category): Response
    {
        $this->assertLoggedIn();
        $type = $this->parseCategoryType($type);
        $this->assertHasEditPermissions($type);
        $this->assertCategoryExists($type, $id);

        $category->id = $id;
        $this->categoriesTransactions->getCategoriesGateway($type)->updateCategory($category);
        $this->categoriesTransactions->handleTypeSpecificSideEffects($type);

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Deletes a category')]
    #[Route('categories/{type}/{id}', methods: ['DELETE'], requirements: ['type' => '\w+', 'id' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Category doesn\'t exist')]
    public function deleteCategory(string $type, int $id): Response
    {
        $this->assertLoggedIn();
        $type = $this->parseCategoryType($type);
        $this->assertHasEditPermissions($type);
        $this->assertCategoryExists($type, $id);

        $this->categoriesTransactions->getCategoriesGateway($type)->deleteCategory($id);
        $this->categoriesTransactions->handleTypeSpecificSideEffects($type);

        return $this->respondOK();
    }

    #[OA\Post(summary: 'Merge two categories')]
    #[Route('categories/{type}/{sourceId}/merges/{targetId}', methods: ['POST'], requirements: [
        'type' => '\w+',
        'sourceId' => Requirement::POSITIVE_INT,
        'targetId' => Requirement::POSITIVE_INT,
    ])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'integer',
        description: 'Number of duplicates removed'
    ))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Category doesn\'t exist')]
    public function mergeCategories(string $type, int $sourceId, int $targetId): Response
    {
        $this->assertLoggedIn();
        $type = $this->parseCategoryType($type);
        $this->assertHasEditPermissions($type);
        $this->assertCategoryExists($type, $sourceId);
        $this->assertCategoryExists($type, $targetId);

        if ($sourceId === $targetId) {
            throw new BadRequestHttpException('Cannot merge a category with itself');
        }

        $duplicates = $this->categoriesTransactions->getCategoriesGateway($type)->mergeCategories($sourceId, $targetId);
        $this->categoriesTransactions->handleTypeSpecificSideEffects($type);

        return $this->respondOK(['duplicates' => $duplicates]);
    }

    private function parseCategoryType(string $type): CategoryType
    {
        $categoryType = CategoryType::tryFrom($type);
        if (!$categoryType) {
            throw new BadRequestHttpException('invalid category type');
        }

        return $categoryType;
    }

    private function assertHasEditPermissions(CategoryType $type): void
    {
        if (!$this->categoriesPermissions->mayEditCategories($type)) {
            throw new AccessDeniedHttpException('You are not permitted to edit categories of type ' . $type->value);
        }
    }

    private function assertCategoryExists(CategoryType $type, int $id): void
    {
        if (!$this->categoriesTransactions->getCategoriesGateway($type)->categoryExists($id)) {
            throw new NotFoundHttpException('Category does not exist');
        }
    }
}
