<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Achievement\AchievementGateway;
use Foodsharing\Modules\Achievement\DTO\Achievement;
use Foodsharing\Permissions\AchievementPermissions;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'achievement')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
class AchievementRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        protected Session $session,
        private readonly AchievementGateway $achievementGateway,
        private readonly AchievementPermissions $achievementPermissions,
    ) {
    }

    #[Rest\Get('achievements/region/{regionId}', requirements: ['regionId' => '\d+'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array',
        items: new OA\Items(ref: new Model(type: Achievement::class)),
        description: 'The list of achievements scoped to this region.'
    ))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted to access these achievements')]
    public function getAchievementsFromRegion(int $regionId): Response
    {
        $this->assertLoggedIn();
        if (!$this->achievementPermissions->maySeeAchievementsFromRegion($regionId)) {
            throw new AccessDeniedHttpException('You are not part of this region');
        }

        $regions = $this->achievementGateway->getAchievementsFromRegion($regionId);

        return $this->respondOK($regions);
    }

    #[Rest\Post('achievements')]
    #[ParamConverter('achievement', class: Achievement::class, converter: 'fos_rest.request_body')]
    #[OA\RequestBody(content: new Model(type: Achievement::class))]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'integer',
        description: 'the id of the newly created achievement',
    ))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted to access these achievements')]
    public function addAchievement(Achievement $achievement, ValidatorInterface $validator): Response
    {
        $this->assertLoggedIn();
        if (!$this->achievementPermissions->mayCreateAchievement()) {
            throw new AccessDeniedHttpException('You are not allowed to create achievements');
        }
        $this->assertThereAreNoValidationErrors($validator, $achievement);

        $achievementId = $this->achievementGateway->addAchievement($achievement);

        return $this->respondOK($achievementId);
    }
}
