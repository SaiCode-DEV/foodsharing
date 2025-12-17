<?php

namespace Foodsharing\RestApi;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Achievement\AchievementGateway;
use Foodsharing\Modules\Achievement\AchievementTransactions;
use Foodsharing\Modules\Achievement\DTO\Achievement;
use Foodsharing\Modules\Achievement\DTO\AwardedAchievement;
use Foodsharing\Modules\Achievement\DTO\AwardedAchievementWithUserDetails;
use Foodsharing\Permissions\AchievementPermissions;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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
    #[Rest\Get('achievements/region/{regionId}', requirements: ['regionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array',
        items: new OA\Items(ref: new Model(type: Achievement::class)),
        description: 'The list of achievements scoped to this region.'
    ))]
    public function getAchievementsFromRegion(int $regionId): Response
    {
        $this->assertLoggedIn();
        if (!$this->achievementPermissions->maySeeAchievementsFromRegion($regionId)) {
            throw new AccessDeniedHttpException();
        }

        $regions = $this->achievementGateway->getAchievementsFromRegion($regionId);

        return $this->respondOK($regions);
    }

    #[OA\Post(summary: 'Add a new achievement')]
    #[Rest\Post('achievements')]
    #[ParamConverter('achievement', class: Achievement::class, converter: 'fos_rest.request_body')]
    #[OA\RequestBody(content: new Model(type: Achievement::class))]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'integer',
        description: 'the id of the newly created achievement',
    ))]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid data')]
    public function addAchievement(Achievement $achievement, ValidatorInterface $validator): Response
    {
        $this->assertLoggedIn();
        if (!$this->achievementPermissions->mayEditAchievements()) {
            throw new AccessDeniedHttpException();
        }
        $this->assertThereAreNoValidationErrors($validator, $achievement);

        $achievementId = $this->achievementGateway->addAchievement($achievement);

        return $this->respondOK($achievementId);
    }

    #[OA\Patch(summary: 'Edit an existing achievement')]
    #[Rest\Patch('achievements/{achievementId}', requirements: ['achievementId' => Requirement::POSITIVE_INT])]
    #[ParamConverter('achievement', class: Achievement::class, converter: 'fos_rest.request_body')]
    #[OA\RequestBody(content: new Model(type: Achievement::class))]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid data')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Achievement does not exist')]
    public function updateAchievement(int $achievementId, Achievement $achievement, ValidatorInterface $validator): Response
    {
        $this->assertLoggedIn();
        if (!$this->achievementPermissions->mayEditAchievements()) {
            throw new AccessDeniedHttpException();
        }
        $this->assertThereAreNoValidationErrors($validator, $achievement);

        $achievement->id = $achievementId;
        $updated = $this->achievementGateway->updateAchievement($achievement);
        if (!$updated) {
            throw new NotFoundHttpException();
        }

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Delete an existing achievement')]
    #[Rest\Delete('achievements/{achievementId}', requirements: ['achievementId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid data')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Achievement does not exist')]
    public function deleteAchievement(int $achievementId): Response
    {
        $this->assertLoggedIn();
        if (!$this->achievementPermissions->mayEditAchievements()) {
            throw new AccessDeniedHttpException();
        }

        $deleted = $this->achievementGateway->deleteAchievement($achievementId);
        if (!$deleted) {
            throw new NotFoundHttpException();
        }

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Get details about all users that have a specific achievement')]
    #[Rest\Get('achievements/{achievementId}/users', requirements: ['achievementId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array',
        items: new OA\Items(ref: new Model(type: AwardedAchievementWithUserDetails::class)),
        description: 'The list of awarded achievements.'
    ))]
    public function getAwardedUsersForAchievement(int $achievementId): Response
    {
        $this->assertLoggedIn();
        if (!$this->achievementPermissions->mayAdministrateAchievement($achievementId)) {
            throw new AccessDeniedHttpException();
        }

        $users = $this->achievementGateway->getAwardedUsersForAchievement($achievementId);

        return $this->respondOK($users);
    }

    #[OA\Post(summary: 'Award an achievement to a user')]
    #[Rest\Post('achievements/{achievementId}/users/{userId}', requirements: ['achievementId' => Requirement::POSITIVE_INT, 'userId' => Requirement::POSITIVE_INT])]
    #[Rest\RequestParam(name: 'validUntil', nullable: true, description: 'Date until which this is valid, or \'infinite\'')]
    #[Rest\RequestParam(name: 'notice', nullable: true)]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: AwardedAchievementWithUserDetails::class))]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid data')]
    public function awardAchievement(int $achievementId, int $userId, ParamFetcher $paramFetcher): Response
    {
        $this->assertLoggedIn();
        if (!$this->achievementPermissions->mayAwardAchievement($achievementId, $userId)) {
            throw new AccessDeniedHttpException();
        }

        $awardedAchievement = $this->prepareAwardedAchievement($achievementId, $userId, $paramFetcher, false);
        $awardedAchievementId = $this->achievementTransactions->awardAchievement($awardedAchievement);
        $awardedAchievement = $this->achievementGateway->getAwardedAchievementWithUserDetails($achievementId, $awardedAchievementId);

        return $this->respondOK($awardedAchievement);
    }

    #[OA\Patch(summary: 'Edit an awarded achievement of a user')]
    #[Rest\Patch('achievements/awarded/{awardedAchievementId}', requirements: ['awardedAchievementId' => Requirement::POSITIVE_INT])]
    #[Rest\RequestParam(name: 'validUntil', nullable: false, description: 'Date until which this is valid, or \'infinite\'')]
    #[Rest\RequestParam(name: 'notice', nullable: true)]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: AwardedAchievementWithUserDetails::class))]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid data')]
    public function editAwardedAchievement(int $awardedAchievementId, ParamFetcher $paramFetcher): Response
    {
        $this->assertLoggedIn();

        $awardedAchievement = $this->achievementGateway->getAwardedAchievementById($awardedAchievementId);
        if (!$this->achievementPermissions->mayAwardAchievement($awardedAchievement->achievementId, $awardedAchievement->foodsaverId)) {
            throw new AccessDeniedHttpException();
        }

        $awardedAchievement = $this->prepareAwardedAchievement($awardedAchievement->achievementId, $awardedAchievement->foodsaverId, $paramFetcher, true);
        $awardedAchievement->id = $awardedAchievementId;
        $this->achievementGateway->editAwardedAchievement($awardedAchievement);

        $awardedAchievement = $this->achievementGateway->getAwardedAchievementWithUserDetails($awardedAchievement->achievementId, $awardedAchievementId);

        return $this->respondOK($awardedAchievement);
    }

    #[OA\Delete(summary: 'Revoke an achievement from a user')]
    #[Rest\Delete('achievements/awarded/{awardedAchievementId}', requirements: ['awardedAchievementId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    public function revokeAchievement(int $awardedAchievementId): Response
    {
        $this->assertLoggedIn();
        $awardedAchievement = $this->achievementGateway->getAwardedAchievementById($awardedAchievementId);
        if (!$this->achievementPermissions->mayAwardAchievement($awardedAchievement->achievementId, $awardedAchievement->foodsaverId)) {
            throw new AccessDeniedHttpException();
        }

        $this->achievementGateway->revokeAchievement($awardedAchievementId);

        return $this->respondOK();
    }

    /**
     * Processes the validUntil value.
     *
     * @param bool $strict Specifies the behaviour when no value is given via the paramFetcher.
     *                     - true: An error is thrown
     *                     - false: A value is generated based on the achievement default settings
     * @return ?Carbon the date until the awarded achievement will be valid, or null if indefinite
     */
    private function getValidUntil(ParamFetcher $paramFetcher, bool $strict, int $achievementId): ?Carbon
    {
        $validUntil = $paramFetcher->get('validUntil');
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
    private function prepareAwardedAchievement(int $achievementId, int $userId, ParamFetcher $paramFetcher, bool $strict): AwardedAchievement
    {
        $achievement = new AwardedAchievement();
        $achievement->foodsaverId = $userId;
        $achievement->achievementId = $achievementId;
        $achievement->reviewerId = $this->session->id();
        $achievement->validUntil = $this->getValidUntil($paramFetcher, $strict, $achievementId);
        $achievement->notice = $paramFetcher->get('notice') ?: null;

        return $achievement;
    }
}
