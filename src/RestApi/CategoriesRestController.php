<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Categories\AbstractCategoriesGateway;
use Foodsharing\Modules\Categories\Category;
use Foodsharing\Modules\Categories\ResourceCategoriesGateway;
use Foodsharing\Modules\Categories\StoreCategoriesGateway;
use Foodsharing\Modules\Core\DBConstants\CategoryType;
use Foodsharing\Modules\Store\DTO\CommonLabel;
use Foodsharing\Modules\Store\StoreTransactions;
use Foodsharing\Permissions\CategoriesPermissions;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'categories')]
class CategoriesRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        protected Session $session,
        private readonly CategoriesPermissions $categoriesPermissions,
        private readonly StoreCategoriesGateway $storeCategoriesGateway,
        private readonly ResourceCategoriesGateway $resourceCategoriesGateway,
        private readonly StoreTransactions $storeTransactions,
    ) {
        parent::__construct($this->session);
    }

    #[OA\Get(summary: 'Returns all existing categories')]
    #[Rest\Get(path: 'categories/{type}', requirements: ['type' => '\w+'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new OA\JsonContent(
        type: 'array',
        items: new OA\Items(ref: new Model(type: Category::class))
    ))]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Invalid category type')]
    public function getCategories(string $type): Response
    {
        $this->assertLoggedIn();
        $type = $this->parseCategoryType($type);
        $categories = $this->getCategoriesGateway($type)->getCategoriesWithUsageCounts();

        return $this->respondOK($categories);
    }

    #[OA\Post(summary: 'Adds a category')]
    #[Rest\Post(path: 'categories/{type}', requirements: ['type' => '\w+'])]
    #[OA\RequestBody(content: new Model(type: CommonLabel::class))]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new OA\JsonContent(
        ref: new Model(type: CommonLabel::class)
    ))]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid parameters')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to add the category')]
    public function addStoreCategory(string $type, #[MapRequestPayload] CommonLabel $category): Response
    {
        $this->assertLoggedIn();
        $type = $this->parseCategoryType($type);
        $this->assertHasEditPermissions($type);

        $category->id = $this->getCategoriesGateway($type)->addCategory($category);

        $this->handleTypeSpecificSideEffects($type);

        return $this->respondOK($category);
    }

    #[OA\Patch(summary: 'Changes a category')]
    #[Rest\Patch('categories/{type}/{id}', requirements: ['type' => '\w+', 'id' => Requirement::DIGITS])]
    #[ParamConverter('category', converter: 'fos_rest.request_body')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid parameters')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to edit store categories')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Store category does not exist')]
    public function updateStoreCategory(string $type, int $id, #[MapRequestPayload] CommonLabel $category): Response
    {
        $this->assertLoggedIn();
        $type = $this->parseCategoryType($type);
        $this->assertHasEditPermissions($type);
        $this->assertCategoryExists($type, $id);

        $category->id = $id;
        $this->getCategoriesGateway($type)->updateCategory($category);
        $this->handleTypeSpecificSideEffects($type);

        return $this->respondOK();
    }

    #[OA\Patch(summary: 'Deletes a category')]
    #[Rest\Delete('categories/{type}/{id}', requirements: ['type' => '\w+', 'id' => Requirement::DIGITS])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to delete categories')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Category does not exist')]
    public function deleteCategory(string $type, int $id): Response
    {
        $this->assertLoggedIn();
        $type = $this->parseCategoryType($type);
        $this->assertHasEditPermissions($type);
        $this->assertCategoryExists($type, $id);

        $this->getCategoriesGateway($type)->deleteCategory($id);
        $this->handleTypeSpecificSideEffects($type);

        return $this->respondOK();
    }

    #[OA\Post(summary: 'Merge two categories')]
    #[Rest\Post('categories/{type}/merge/{sourceId}/{targetId}', requirements: [
        'type' => '\w+',
        'sourceId' => Requirement::DIGITS,
        'targetId' => Requirement::DIGITS,
    ])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new OA\JsonContent(
        type: 'object',
        properties: ['duplicates' => new OA\Property(type: 'integer', description: 'Number of duplicates removed')]
    ))]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to merge categories')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'One of the categories does not exist')]
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

        $duplicates = $this->getCategoriesGateway($type)->mergeCategories($sourceId, $targetId);
        $this->handleTypeSpecificSideEffects($type);

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
            throw new AccessDeniedHttpException();
        }
    }

    private function assertCategoryExists(CategoryType $type, int $id): void
    {
        if (!$this->getCategoriesGateway($type)->categoryExists($id)) {
            throw new NotFoundHttpException('Category does not exist');
        }
    }

    private function handleTypeSpecificSideEffects(CategoryType $type): void
    {
        if ($type === CategoryType::STORE) {
            $this->storeTransactions->invalidateCachedStoreMetadata();
        }
    }

    private function getCategoriesGateway(CategoryType $type): AbstractCategoriesGateway
    {
        return match ($type) {
            CategoryType::STORE => $this->storeCategoriesGateway,
            CategoryType::RESOURCE => $this->resourceCategoriesGateway,
        };
    }
}
