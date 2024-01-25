<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Development\FeatureToggles\DependencyInjection\FeatureToggleChecker;
use Foodsharing\Modules\Development\FeatureToggles\Enums\FeatureToggleDefinitions;
use Foodsharing\Modules\Development\FeatureToggles\Querys\HasPermissionToManageFeatureTogglesQuery;
use Foodsharing\Modules\Development\FeatureToggles\Services\FeatureToggleService;
use Foodsharing\RestApi\Models\FeatureToggle\FeatureToggle;
use Foodsharing\RestApi\Models\FeatureToggle\FeatureTogglesResponse;
use Foodsharing\RestApi\Models\FeatureToggle\IsFeatureToggleActiveResponse;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Tag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class FeatureToggleRestController extends AbstractFOSRestController
{
    public function __construct(
        private readonly FeatureToggleChecker $featureToggleChecker,
        private readonly FeatureToggleService $featureToggleService,
        private readonly Session $session,
        private readonly HasPermissionToManageFeatureTogglesQuery $hasPermissionToManageFeatureTogglesQuery,
    ) {
    }

    /**
     * Returns all feature toggle identifiers with some information.
     */
    #[Tag('featuretoggle')]
    #[Rest\Get(path: 'featuretoggle/')]
    #[Response(response: HttpResponse::HTTP_OK, description: 'Successful', content: new Model(type: FeatureTogglesResponse::class))]
    public function getAllFeatureToggles(): JsonResponse
    {
        $featureToggles = [];

        foreach (FeatureToggleDefinitions::all() as $featureToggleIdentifier) {
            $featureToggles[] = new FeatureToggle(
                $featureToggleIdentifier,
                $this->featureToggleChecker->isFeatureToggleActive($featureToggleIdentifier),
            );
        }

        return $this->json(
            new FeatureTogglesResponse($featureToggles),
            HttpResponse::HTTP_OK,
        );
    }

    /**
     * Checks if a feature toggle is active or not.
     */
    #[Tag('featuretoggle')]
    #[Rest\Get(path: 'featuretoggle/{featureToggle}')]
    #[Parameter(name: 'featureToggle', description: 'Identifier for feature toggle', in: 'path', required: true)]
    #[Response(response: HttpResponse::HTTP_OK, description: 'Successful', content: new Model(type: IsFeatureToggleActiveResponse::class))]
    public function isFeatureToggleActive(string $featureToggle): JsonResponse
    {
        $isFeatureFlagActive = $this->featureToggleChecker->isFeatureToggleActive($featureToggle);

        return $this->json(
            new IsFeatureToggleActiveResponse($featureToggle, $isFeatureFlagActive),
            HttpResponse::HTTP_OK,
        );
    }

    /**
     * Toggles a feature toggle state.
     */
    #[Tag('featuretoggle')]
    #[Rest\Post(path: 'featuretoggle/{featureToggle}/toggle')]
    #[Parameter(name: 'featureToggle', description: 'Identifier for feature toggle', in: 'path', required: true)]
    #[Response(response: HttpResponse::HTTP_OK, description: 'Successful')]
    #[Response(response: HttpResponse::HTTP_FORBIDDEN, description: 'Not enough privileges to toggle a feature toggle state')]
    #[Response(response: HttpResponse::HTTP_NOT_FOUND, description: 'Feature toggle is not defined')]
    #[Response(response: HttpResponse::HTTP_BAD_REQUEST, description: 'Feature toggle is not toggable')]
    public function toggleFeatureToggle(string $featureToggle): JsonResponse
    {
        if (!$this->hasPermissionToManageFeatureTogglesQuery->execute($this->session)) {
            throw new AccessDeniedHttpException();
        }

        if (!$this->featureToggleService->isFeatureToggleDefined($featureToggle)) {
            throw $this->createNotFoundException('Feature toggle is not defined');
        }

        $currentState = $this->featureToggleChecker->isFeatureToggleActive($featureToggle);

        $this->featureToggleService->updateFeatureToggleState($featureToggle, !$currentState);

        return $this->json(
            null,
            HttpResponse::HTTP_OK,
        );
    }
}
