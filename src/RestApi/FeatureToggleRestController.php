<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Development\FeatureToggles\DependencyInjection\FeatureToggleChecker;
use Foodsharing\Modules\Development\FeatureToggles\Enums\FeatureToggleDefinitions;
use Foodsharing\Modules\Development\FeatureToggles\Exceptions\FeatureToggleNotDefinedException;
use Foodsharing\Modules\Development\FeatureToggles\Querys\HasPermissionToManageFeatureTogglesQuery;
use Foodsharing\Modules\Development\FeatureToggles\Services\FeatureToggleService;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\RestApi\Models\FeatureToggle\FeatureToggle;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag('feature-toggle')]
class FeatureToggleRestController extends AbstractFoodsharingRestController
{
    final public const string FEATURE_TOGGLE_NAME_REQUIREMENT = '[a-zA-Z]+';

    public function __construct(
        protected Session $session,
        private readonly FeatureToggleChecker $featureToggleChecker,
        private readonly FeatureToggleService $featureToggleService,
        private readonly HasPermissionToManageFeatureTogglesQuery $hasPermissionToManageFeatureTogglesQuery,
        private readonly CurrentUserUnitsInterface $currentUserUnitsInterface,
    ) {
        parent::__construct($session);
    }

    #[OA\Get(summary: 'Returns all feature toggles')]
    #[Route('feature-toggles', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: FeatureToggle::class))
    ))]
    public function getAllFeatureToggles(): Response
    {
        $featureToggles = array_map(fn ($id) => new FeatureToggle(
            $id,
            $this->featureToggleChecker->isFeatureToggleActive($id),
        ), FeatureToggleDefinitions::all());

        return $this->respondOK($featureToggles);
    }

    #[OA\Get(summary: 'Checks if a feature toggle is active or not')]
    #[Route('feature-toggles/{featureToggle}', methods: ['GET'], requirements: ['featureToggle' => self::FEATURE_TOGGLE_NAME_REQUIREMENT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: FeatureToggle::class))]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Feature toggle is not defined')]
    public function isFeatureToggleActive(string $featureToggle): Response
    {
        try {
            $isActive = $this->featureToggleChecker->isFeatureToggleActive($featureToggle);
        } catch (FeatureToggleNotDefinedException) {
            throw new NotFoundHttpException('Feature toggle is not defined');
        }

        return $this->respondOK(new FeatureToggle($featureToggle, $isActive));
    }

    #[OA\Patch(summary: 'Changes a feature toggle state')]
    #[Route('feature-toggles/{featureToggle}', methods: ['PATCH'], requirements: ['featureToggle' => self::FEATURE_TOGGLE_NAME_REQUIREMENT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Feature toggle is not defined')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function toggleFeatureToggle(string $featureToggle, #[MapQueryParameter] ?bool $newState = null): Response
    {
        if (!$this->hasPermissionToManageFeatureTogglesQuery->execute($this->session, $this->currentUserUnitsInterface)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        try {
            $currentState = $this->featureToggleChecker->isFeatureToggleActive($featureToggle);
        } catch (FeatureToggleNotDefinedException) {
            throw new NotFoundHttpException('Feature toggle is not defined');
        }

        $this->featureToggleService->updateFeatureToggleState($featureToggle, $newState ?? !$currentState);

        return $this->respondOK();
    }
}
