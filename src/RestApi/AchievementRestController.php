<?php

namespace Foodsharing\RestApi;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Achievement\AchievementGateway;
use Foodsharing\Modules\Achievement\AchievementTransactions;
use Foodsharing\Modules\Achievement\DTO\Achievement;
use Foodsharing\Modules\Achievement\DTO\AwardedAchievement;
use Foodsharing\Modules\Achievement\DTO\AwardedAchievementDetails;
use Foodsharing\Modules\Achievement\DTO\AwardedAchievementWithUserDetails;
use Foodsharing\Permissions\AchievementPermissions;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'achievement')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
#[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
class AchievementRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        protected Session $session,
        private readonly AchievementGateway $achievementGateway,
        private readonly AchievementPermissions $achievementPermissions,
        private readonly AchievementTransactions $achievementTransactions,
    ) {
    }

    #[OA\Get(summary: 'Get all achievements that belong to a region')]
    #[Route('achievements/region/{regionId}', methods: ['GET'], requirements: ['regionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array',
        items: new OA\Items(ref: new Model(type: Achievement::class)),
        description: 'The list of achievements scoped to this region.'
    ))]
    public function getAchievementsFromRegion(int $regionId): Response
    {
        $this->assertLoggedIn();
        if (!$this->achievementPermissions->maySeeAchievementsFromRegion($regionId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $regions = $this->achievementGateway->getAchievementsFromRegion($regionId);

        return $this->respondOK($regions);
    }

    #[OA\Post(summary: 'Add a new achievement')]
    #[Route('achievements', methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'id', type: 'integer', description: 'Id of the newly created achievement')
    ]))]
    #[OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Invalid data')]
    public function addAchievement(#[MapRequestPayload] Achievement $achievement): Response
    {
        $this->assertLoggedIn();
        if (!$this->achievementPermissions->mayEditAchievements()) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $achievementId = $this->achievementGateway->addAchievement($achievement);

        return $this->respondOK(['id' => $achievementId]);
    }

    #[OA\Patch(summary: 'Edit an existing achievement')]
    #[Route('achievements/{achievementId}', methods: ['PATCH'], requirements: ['achievementId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Invalid data')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Achievement does not exist')]
    public function updateAchievement(int $achievementId, #[MapRequestPayload] Achievement $achievement): Response
    {
        $this->assertLoggedIn();
        if (!$this->achievementPermissions->mayEditAchievements()) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $achievement->id = $achievementId;
        $updated = $this->achievementGateway->updateAchievement($achievement);
        if (!$updated) {
            throw new NotFoundHttpException('Achievement does not exist');
        }

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Delete an existing achievement')]
    #[Route('achievements/{achievementId}', methods: ['DELETE'], requirements: ['achievementId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Invalid data')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Achievement does not exist')]
    public function deleteAchievement(int $achievementId): Response
    {
        $this->assertLoggedIn();
        if (!$this->achievementPermissions->mayEditAchievements()) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $deleted = $this->achievementGateway->deleteAchievement($achievementId);
        if (!$deleted) {
            throw new NotFoundHttpException('Achievement does not exist');
        }

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Get details about all users that have a specific achievement')]
    #[Route('achievements/{achievementId}/users', methods: ['GET'], requirements: ['achievementId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array',
        items: new OA\Items(ref: new Model(type: AwardedAchievementWithUserDetails::class)),
        description: 'The list of awarded achievements.'
    ))]
    public function getAwardedUsersForAchievement(int $achievementId): Response
    {
        $this->assertLoggedIn();
        if (!$this->achievementPermissions->mayAdministrateAchievement($achievementId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $users = $this->achievementGateway->getAwardedUsersForAchievement($achievementId);

        return $this->respondOK($users);
    }

    #[OA\Post(summary: 'Award an achievement to a user')]
    #[Route('achievements/{achievementId}/users/{userId}', methods: ['POST'], requirements: ['achievementId' => Requirement::POSITIVE_INT, 'userId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: AwardedAchievementWithUserDetails::class))]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid data')]
    public function awardAchievement(int $achievementId, int $userId, #[MapRequestPayload] AwardedAchievementDetails $awardedAchievementDetails): Response
    {
        $this->assertLoggedIn();
        if (!$this->achievementPermissions->mayAwardAchievement($achievementId, $userId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $awardedAchievement = $this->prepareAwardedAchievement($achievementId, $userId, $awardedAchievementDetails, false);
        $awardedAchievementId = $this->achievementTransactions->awardAchievement($awardedAchievement);
        $awardedAchievement = $this->achievementGateway->getAwardedAchievementWithUserDetails($achievementId, $awardedAchievementId);

        return $this->respondOK($awardedAchievement);
    }

    #[OA\Patch(summary: 'Edit an awarded achievement of a user')]
    #[Route('achievements/awarded/{awardedAchievementId}', methods: ['PATCH'], requirements: ['awardedAchievementId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: AwardedAchievementWithUserDetails::class))]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid data')]
    public function editAwardedAchievement(int $awardedAchievementId, #[MapRequestPayload] AwardedAchievementDetails $awardedAchievementDetails): Response
    {
        $this->assertLoggedIn();

        $awardedAchievement = $this->achievementGateway->getAwardedAchievementById($awardedAchievementId);
        if (!$this->achievementPermissions->mayAwardAchievement($awardedAchievement->achievementId, $awardedAchievement->foodsaverId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $awardedAchievement = $this->prepareAwardedAchievement($awardedAchievement->achievementId, $awardedAchievement->foodsaverId, $awardedAchievementDetails, true);
        $awardedAchievement->id = $awardedAchievementId;
        $this->achievementGateway->editAwardedAchievement($awardedAchievement);

        $awardedAchievement = $this->achievementGateway->getAwardedAchievementWithUserDetails($awardedAchievement->achievementId, $awardedAchievementId);

        return $this->respondOK($awardedAchievement);
    }

    #[OA\Delete(summary: 'Revoke an achievement from a user')]
    #[Route('achievements/awarded/{awardedAchievementId}', methods: ['DELETE'], requirements: ['awardedAchievementId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    public function revokeAchievement(int $awardedAchievementId): Response
    {
        $this->assertLoggedIn();
        $awardedAchievement = $this->achievementGateway->getAwardedAchievementById($awardedAchievementId);
        if (!$this->achievementPermissions->mayAwardAchievement($awardedAchievement->achievementId, $awardedAchievement->foodsaverId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $this->achievementGateway->revokeAchievement($awardedAchievementId);

        return $this->respondOK();
    }

    /**
     * Processes the validUntil value.
     *
     * @param bool $strict Specifies the behaviour when no value is given
     *                     - true: An error is thrown
     *                     - false: A value is generated based on the achievement default settings
     * @return ?Carbon the date until the awarded achievement will be valid, or null if indefinite
     */
    private function getValidUntil(?string $validUntil, bool $strict, int $achievementId): ?Carbon
    {
        if (!$validUntil) {
            if ($strict) {
                throw new BadRequestHttpException('Provide a validity date!');
            }

            return $this->achievementTransactions->getValidityDateFromNow($achievementId);
        }
        if ($validUntil === 'infinite') {
            return null;
        }
        try {
            $validUntil = new Carbon($validUntil);
        } catch (InvalidFormatException $e) {
            throw new BadRequestHttpException('Invalid date format');
        }

        return $validUntil;
    }

    /**
     * Prepares an `AwardedAchievement` object using the given data.
     *
     * @param bool $strict see `getValidUntil`
     * @return AwardedAchievement The prepared `AwardedAchievement`. The current session user is set as reviewer.
     */
    private function prepareAwardedAchievement(int $achievementId, int $userId, AwardedAchievementDetails $awardedAchievementDetails, bool $strict): AwardedAchievement
    {
        $achievement = new AwardedAchievement();
        $achievement->foodsaverId = $userId;
        $achievement->achievementId = $achievementId;
        $achievement->reviewerId = $this->session->id();
        $achievement->validUntil = $this->getValidUntil($awardedAchievementDetails->validUntil, $strict, $achievementId);
        $achievement->notice = $awardedAchievementDetails->notice;

        return $achievement;
    }
}
