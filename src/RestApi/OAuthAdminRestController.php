<?php

declare(strict_types=1);

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\OAuth\OAuthGateway;
use Foodsharing\Permissions\OAuthPermissions;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class OAuthAdminRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        Session $session,
        private readonly OAuthGateway $gateway,
        private readonly OAuthPermissions $permissions,
    ) {
        parent::__construct($session);
    }

    #[Route('admin/oauthclients', methods: ['GET'])]
    public function listClientsAction(): Response
    {
        $this->assertLoggedIn();

        if (!$this->permissions->mayAdministrateOAuthClients()) {
            throw new AccessDeniedHttpException();
        }

        $clients = $this->gateway->getAllClients();

        // Parse JSON fields and remove secret hashes
        $clients = array_map(function ($client) {
            $client['redirect_uris'] = json_decode($client['redirect_uris'], true);
            $client['scopes'] = json_decode($client['scopes'], true);
            $client['grant_types'] = json_decode($client['grant_types'], true);
            $client['required_region_ids'] = $client['required_region_ids']
                ? json_decode($client['required_region_ids'], true)
                : null;
            unset($client['secret_hash']);

            return $client;
        }, $clients);

        return $this->respondOK($clients);
    }

    #[Route('admin/oauthclients/{identifier}', methods: ['GET'])]
    public function getClientAction(string $identifier): Response
    {
        $this->assertLoggedIn();

        if (!$this->permissions->mayAdministrateOAuthClients()) {
            throw new AccessDeniedHttpException();
        }

        $client = $this->gateway->getClient($identifier);
        if (!$client) {
            throw new NotFoundHttpException('Client not found');
        }

        // Parse JSON fields and remove secret hash
        $client['redirect_uris'] = json_decode($client['redirect_uris'], true);
        $client['scopes'] = json_decode($client['scopes'], true);
        $client['grant_types'] = json_decode($client['grant_types'], true);
        $client['required_region_ids'] = $client['required_region_ids']
            ? json_decode($client['required_region_ids'], true)
            : null;
        unset($client['secret_hash']);

        return $this->respondOK($client);
    }

    #[Route('admin/oauthclients', methods: ['POST'])]
    public function createClientAction(): Response
    {
        $this->assertLoggedIn();

        if (!$this->permissions->mayAdministrateOAuthClients()) {
            throw new AccessDeniedHttpException();
        }

        $request = $this->getRequest();
        $data = json_decode($request->getContent(), true) ?? [];
        $identifier = $data['identifier'] ?? null;
        $name = $data['name'] ?? null;
        $confidential = !empty($data['confidential']);
        $redirectUris = $data['redirect_uris'] ?? [];
        $scopes = $data['scopes'] ?? [];
        $grantTypes = $data['grant_types'] ?? [];
        $requiredRegionIds = $data['required_region_ids'] ?? null;

        // Validate
        if ($this->gateway->getClient($identifier)) {
            throw new BadRequestHttpException('Client with this identifier already exists');
        }

        if (!is_array($redirectUris) || empty($redirectUris)) {
            throw new BadRequestHttpException('redirect_uris must be a non-empty array');
        }

        if (!is_array($scopes) || empty($scopes)) {
            throw new BadRequestHttpException('scopes must be a non-empty array');
        }

        if (!is_array($grantTypes) || empty($grantTypes)) {
            throw new BadRequestHttpException('grant_types must be a non-empty array');
        }

        // Parse required_region_ids
        if ($requiredRegionIds && !is_array($requiredRegionIds)) {
            throw new BadRequestHttpException('required_region_ids must be an array or null');
        }

        // Generate secret for confidential clients
        $secret = null;
        $secretHash = null;
        if ($confidential) {
            $secret = bin2hex(random_bytes(32));
            $secretHash = password_hash($secret, PASSWORD_BCRYPT);
        }

        $this->gateway->createClient(
            $identifier,
            $name,
            $confidential,
            $redirectUris,
            $scopes,
            $grantTypes,
            $secretHash,
            !empty($requiredRegionIds) ? $requiredRegionIds : null,
            $this->session->id()
        );

        $response = [
            'identifier' => $identifier,
            'name' => $name,
            'confidential' => $confidential,
            'redirect_uris' => $redirectUris,
            'scopes' => $scopes,
            'grant_types' => $grantTypes,
            'required_region_ids' => $requiredRegionIds,
        ];

        if ($secret) {
            $response['secret'] = $secret;
        }

        return $this->handleView($this->view($response, Response::HTTP_CREATED));
    }

    #[Route('admin/oauthclients/{identifier}', methods: ['PATCH'])]
    public function updateClientAction(string $identifier): Response
    {
        $this->assertLoggedIn();

        if (!$this->permissions->mayAdministrateOAuthClients()) {
            throw new AccessDeniedHttpException();
        }

        $client = $this->gateway->getClient($identifier);
        if (!$client) {
            throw new NotFoundHttpException('Client not found');
        }

        $request = $this->getRequest();
        $body = json_decode($request->getContent(), true) ?? [];
        $data = [];

        if (array_key_exists('name', $body)) {
            $data['name'] = $body['name'];
        }
        if (array_key_exists('active', $body)) {
            $data['active'] = !empty($body['active']);
        }
        if (array_key_exists('redirect_uris', $body)) {
            $redirectUris = $body['redirect_uris'];
            if (!is_array($redirectUris)) {
                throw new BadRequestHttpException('redirect_uris must be an array');
            }
            $data['redirect_uris'] = $redirectUris;
        }
        if (array_key_exists('scopes', $body)) {
            $scopes = $body['scopes'];
            if (!is_array($scopes)) {
                throw new BadRequestHttpException('scopes must be an array');
            }
            $data['scopes'] = $scopes;
        }
        if (array_key_exists('grant_types', $body)) {
            $grantTypes = $body['grant_types'];
            if (!is_array($grantTypes)) {
                throw new BadRequestHttpException('grant_types must be an array');
            }
            $data['grant_types'] = $grantTypes;
        }
        if (array_key_exists('required_region_ids', $body)) {
            $requiredRegionIds = $body['required_region_ids'];
            if ($requiredRegionIds !== null && !is_array($requiredRegionIds)) {
                throw new BadRequestHttpException('required_region_ids must be an array or null');
            }
            $data['required_region_ids'] = $requiredRegionIds;
        }
        $newSecret = null;
        if (!empty($body['regenerate_secret'])) {
            if (!$client['confidential']) {
                throw new BadRequestHttpException('Cannot regenerate secret for public client');
            }
            $newSecret = bin2hex(random_bytes(32));
            $data['secret_hash'] = password_hash($newSecret, PASSWORD_BCRYPT);
        }
        if (!empty($data)) {
            $data['changed_by'] = $this->session->id();
            $this->gateway->updateClient($identifier, $data);
        }
        $response = ['success' => true];
        if ($newSecret) {
            $response['secret'] = $newSecret;
        }

        return $this->respondOK($response);
    }

    #[Route('admin/oauthclients/{identifier}', methods: ['DELETE'])]
    public function deleteClientAction(string $identifier): Response
    {
        $this->assertLoggedIn();

        if (!$this->permissions->mayAdministrateOAuthClients()) {
            throw new AccessDeniedHttpException();
        }

        $client = $this->gateway->getClient($identifier);
        if (!$client) {
            throw new NotFoundHttpException('Client not found');
        }

        $this->gateway->deleteClient($identifier);

        return $this->respondOK(['success' => true]);
    }

    private function getRequest()
    {
        return $this->container->get('request_stack')->getCurrentRequest();
    }
}
