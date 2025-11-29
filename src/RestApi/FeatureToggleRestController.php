<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Development\FeatureToggles\DependencyInjection\FeatureToggleChecker;
use Foodsharing\Modules\Development\FeatureToggles\Enums\FeatureToggleDefinitions;
use Foodsharing\Modules\Development\FeatureToggles\Querys\HasPermissionToManageFeatureTogglesQuery;
use Foodsharing\Modules\Development\FeatureToggles\Services\FeatureToggleService;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\RestApi\Models\FeatureToggle\FeatureToggle;
use Foodsharing\RestApi\Models\FeatureToggle\FeatureTogglesResponse;
use Foodsharing\RestApi\Models\FeatureToggle\IsFeatureToggleActiveResponse;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class FeatureToggleRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        private readonly FeatureToggleChecker $featureToggleChecker,
        private readonly FeatureToggleService $featureToggleService,
        protected Session $session,
        private readonly HasPermissionToManageFeatureTogglesQuery $hasPermissionToManageFeatureTogglesQuery,
        private readonly CurrentUserUnitsInterface $currentUserUnitsInterface,
    ) {
        parent::__construct($session);
    }

    /**
     * Returns all feature toggle identifiers with some information.
     */
    #[OA\Tag('featuretoggle')]
    #[Rest\Get(path: 'featuretoggle/')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Successful', content: new Model(type: FeatureTogglesResponse::class))]
    public function getAllFeatureToggles(): Response
    {
        $featureToggles = [];

        foreach (FeatureToggleDefinitions::all() as $featureToggleIdentifier) {
            $featureToggles[] = new FeatureToggle(
                $featureToggleIdentifier,
                $this->featureToggleChecker->isFeatureToggleActive($featureToggleIdentifier),
            );
        }

        return $this->respondOK(new FeatureTogglesResponse($featureToggles));
    }

    /**
     * Checks if a feature toggle is active or not.
     */
    #[OA\Tag('featuretoggle')]
    #[Rest\Get(path: 'featuretoggle/{featureToggle}')]
    #[OA\Parameter(name: 'featureToggle', description: 'Identifier for feature toggle', in: 'path', required: true)]
    #[OA\Response(response: Response::HTTP_OK, description: 'Successful', content: new Model(type: IsFeatureToggleActiveResponse::class))]
    public function isFeatureToggleActive(string $featureToggle): Response
    {
        $isFeatureFlagActive = $this->featureToggleChecker->isFeatureToggleActive($featureToggle);

        return $this->respondOK(new IsFeatureToggleActiveResponse($featureToggle, $isFeatureFlagActive));
    }

    /**
     * Toggles a feature toggle state.
     */
    #[OA\Tag('featuretoggle')]
    #[Rest\Post(path: 'featuretoggle/{featureToggle}/toggle')]
    #[OA\Parameter(name: 'featureToggle', description: 'Identifier for feature toggle', in: 'path', required: true)]
    #[OA\Response(response: Response::HTTP_OK, description: 'Successful')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not enough privileges to toggle a feature toggle state')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Feature toggle is not defined')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Feature toggle is not toggable')]
    public function toggleFeatureToggle(string $featureToggle): Response
    {
        if (!$this->hasPermissionToManageFeatureTogglesQuery->execute($this->session, $this->currentUserUnitsInterface)) {
            throw new AccessDeniedHttpException();
        }

        if (!$this->featureToggleService->isFeatureToggleDefined($featureToggle)) {
            throw $this->createNotFoundException('Feature toggle is not defined');
        }

        $currentState = $this->featureToggleChecker->isFeatureToggleActive($featureToggle);

        $this->featureToggleService->updateFeatureToggleState($featureToggle, !$currentState);

        return $this->respondOK();
    }
}
