<?php

declare(strict_types=1);

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Login\EmailBlocklistGateway;
use Foodsharing\Modules\Login\EmailBlocklistTransactions;
use Foodsharing\Permissions\EmailBlocklistPermissions;
use Foodsharing\RestApi\Models\EmailBlocklist\EmailBlocklistEntryModel;
use Foodsharing\RestApi\Models\EmailBlocklist\EmailBlocklistEntryPatchModel;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'admin')]
final class EmailBlocklistAdminRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        Session $session,
        private readonly EmailBlocklistGateway $gateway,
        private readonly EmailBlocklistTransactions $transactions,
        private readonly EmailBlocklistPermissions $permissions,
    ) {
        parent::__construct($session);
    }

    #[OA\Get(summary: 'List all email blocklist entries')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    #[Route(path: '/admin/emailblocklist', methods: ['GET'])]
    public function listBlocklistEntries(): Response
    {
        $this->assertLoggedIn();

        if (!$this->permissions->mayAdministrateEmailBlocklist()) {
            throw new AccessDeniedHttpException();
        }

        $entries = $this->gateway->getAllEntries();

        return $this->respondOK($entries);
    }

    #[OA\Get(summary: 'Get a single email blocklist entry')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Blocklist entry not found')]
    #[Route(path: '/admin/emailblocklist/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getBlocklistEntry(int $id): Response
    {
        $this->assertLoggedIn();

        if (!$this->permissions->mayAdministrateEmailBlocklist()) {
            throw new AccessDeniedHttpException();
        }

        $entry = $this->gateway->getEntry($id);
        if (!$entry) {
            throw new NotFoundHttpException('Blocklist entry not found');
        }

        return $this->respondOK($entry);
    }

    #[OA\Post(summary: 'Create a new email blocklist entry')]
    #[OA\RequestBody(content: new Model(type: EmailBlocklistEntryModel::class))]
    #[OA\Response(response: Response::HTTP_CREATED, description: 'Entry created successfully')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid request data or email pattern already exists')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    #[OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation failed')]
    #[Route(path: '/admin/emailblocklist', methods: ['POST'])]
    public function createBlocklistEntry(
        #[MapRequestPayload] EmailBlocklistEntryModel $model
    ): Response {
        $this->assertLoggedIn();

        if (!$this->permissions->mayAdministrateEmailBlocklist()) {
            throw new AccessDeniedHttpException();
        }

        if ($this->gateway->patternExists($model->email)) {
            throw new BadRequestHttpException('This email pattern already exists in the blocklist');
        }

        $id = $this->transactions->createEntry(
            $model->email,
            $model->reason === '' ? null : $model->reason,
            $model->isActive,
            $this->session->id()
        );

        $entry = $this->gateway->getEntry($id);

        return $this->handleView($this->view($entry, Response::HTTP_CREATED));
    }

    #[OA\Patch(summary: 'Update an existing email blocklist entry')]
    #[OA\RequestBody(content: new Model(type: EmailBlocklistEntryPatchModel::class))]
    #[OA\Response(response: Response::HTTP_OK, description: 'Entry updated successfully')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid request data or email pattern already exists')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Blocklist entry not found')]
    #[OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation failed')]
    #[Route(path: '/admin/emailblocklist/{id}', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function updateBlocklistEntry(
        int $id,
        #[MapRequestPayload] EmailBlocklistEntryPatchModel $model
    ): Response {
        $this->assertLoggedIn();

        if (!$this->permissions->mayAdministrateEmailBlocklist()) {
            throw new AccessDeniedHttpException();
        }

        $entry = $this->gateway->getEntry($id);
        if (!$entry) {
            throw new NotFoundHttpException('Blocklist entry not found');
        }

        $data = [];

        // Only update fields that were provided
        if ($model->email !== null) {
            if (empty($model->email)) {
                throw new BadRequestHttpException('email cannot be empty');
            }
            // Check if pattern exists (excluding current entry)
            if ($model->email !== $entry['email'] && $this->gateway->patternExists($model->email)) {
                throw new BadRequestHttpException('This email pattern already exists in the blocklist');
            }
            $data['email'] = $model->email;
        }

        if ($model->reason !== null) {
            $data['reason'] = $model->reason === '' ? null : $model->reason;
        }

        if ($model->isActive !== null) {
            $data['active'] = $model->isActive;
        }

        if (!empty($data)) {
            $data['updated_by'] = $this->session->id();
            $this->transactions->updateEntry($id, $data);
        }

        $updatedEntry = $this->gateway->getEntry($id);

        return $this->respondOK($updatedEntry);
    }

    #[OA\Delete(summary: 'Delete an email blocklist entry')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Entry deleted successfully')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Blocklist entry not found')]
    #[Route(path: '/admin/emailblocklist/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteBlocklistEntry(int $id): Response
    {
        $this->assertLoggedIn();

        if (!$this->permissions->mayAdministrateEmailBlocklist()) {
            throw new AccessDeniedHttpException();
        }

        $entry = $this->gateway->getEntry($id);
        if (!$entry) {
            throw new NotFoundHttpException('Blocklist entry not found');
        }

        $this->transactions->deleteEntry($id);

        return $this->respondOK();
    }
}
