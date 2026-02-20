<?php

namespace Foodsharing\RestApi;

use Foodsharing\Attribute\DisableCsrfProtection;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Login\DTO\PasskeyAuthenticationRequest;
use Foodsharing\Modules\Login\DTO\PasskeyRegistrationRequest;
use Foodsharing\Modules\Login\DTO\PasskeyRenameRequest;
use Foodsharing\Modules\Login\WebAuthn\WebAuthnGateway;
use Foodsharing\Modules\Login\WebAuthn\WebAuthnService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'passkey')]
class PasskeyRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        protected Session $session,
        private readonly WebAuthnService $webAuthnService,
        private readonly WebAuthnGateway $webAuthnGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
    ) {
    }

    #[OA\Post(summary: 'Generate registration options for creating a new passkey')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success - returns registration options')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[Route('passkey/registration/options', methods: ['POST'])]
    public function registrationOptions(): Response
    {
        $this->assertLoggedIn();

        try {
            $options = $this->webAuthnService->generateRegistrationOptions();

            return $this->respondOK($options);
        } catch (\Exception $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }

    #[OA\Post(summary: 'Verify and save a new passkey credential')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success - passkey registered')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid credential data')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[Route('passkey/registration/verify', methods: ['POST'])]
    public function verifyRegistration(
        #[MapRequestPayload] PasskeyRegistrationRequest $request
    ): Response {
        $this->assertLoggedIn();

        try {
            $this->webAuthnService->verifyRegistration($request->credential, $request->name);

            return $this->respondOK();
        } catch (\Exception $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }

    #[OA\Post(summary: 'Generate authentication options for passkey login')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success - returns authentication options')]
    // CSRF protection is disabled because this endpoint is called before authentication,
    // so no CSRF token is available. This is safe as it only generates a challenge and
    // doesn't modify any sensitive state.
    #[DisableCsrfProtection]
    #[Route('passkey/authentication/options', methods: ['POST'])]
    public function authenticationOptions(): Response
    {
        // Ensure session is initialized even for unauthenticated users
        // so we can store the authentication challenge
        try {
            $this->session->init();
        } catch (\Exception $e) {
            // Session already initialized, which is fine
        }

        try {
            $options = $this->webAuthnService->generateAuthenticationOptions();

            return $this->respondOK($options);
        } catch (\Exception $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }

    #[OA\Post(summary: 'Verify passkey authentication and log in the user')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success - user authenticated')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid assertion data')]
    // CSRF protection is disabled because the WebAuthn protocol provides its own protection
    // against cross-origin attacks through cryptographic verification of the credential,
    // challenge binding, and origin validation.
    #[DisableCsrfProtection]
    #[Route('passkey/authentication/verify', methods: ['POST'])]
    public function verifyAuthentication(
        #[MapRequestPayload] PasskeyAuthenticationRequest $request
    ): Response {
        try {
            $userId = $this->webAuthnService->verifyAuthentication($request->credential);

            // Log the user in using your session/login system
            $user = $this->foodsaverGateway->getFoodsaverDetails($userId);
            if (!$user) {
                throw new BadRequestHttpException('User not found');
            }

            // Set up the session - use your existing login mechanism
            $this->session->login($userId);

            return $this->respondOK(['userId' => $userId]);
        } catch (\Exception $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }

    #[OA\Get(summary: 'List all passkeys for the current user')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success - returns list of passkeys')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[Route('passkey/list', methods: ['GET'])]
    public function listPasskeys(): Response
    {
        $this->assertLoggedIn();

        $passkeys = $this->webAuthnGateway->getCredentialsForUser($this->session->id());

        return $this->respondOK($passkeys);
    }

    #[OA\Delete(summary: 'Delete a passkey')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success - passkey deleted')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Passkey not found')]
    #[Route('passkey/{id}', requirements: ['id' => '[a-zA-Z0-9]+'], methods: ['DELETE'])]
    public function deletePasskey(string $id): Response
    {
        $this->assertLoggedIn();

        $deleted = $this->webAuthnGateway->deleteCredential($id, $this->session->id());
        if (!$deleted) {
            throw new NotFoundHttpException('Passkey not found');
        }

        return $this->respondOK();
    }

    #[OA\Patch(summary: 'Rename a passkey')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success - passkey renamed')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Passkey not found')]
    #[Route('passkey/{id}', requirements: ['id' => '[a-zA-Z0-9]+'], methods: ['PATCH'])]
    public function renamePasskey(
        string $id,
        #[MapRequestPayload] PasskeyRenameRequest $request
    ): Response {
        $this->assertLoggedIn();

        $updated = $this->webAuthnGateway->updateCredentialName($id, $this->session->id(), $request->name);
        if (!$updated) {
            throw new NotFoundHttpException('Passkey not found');
        }

        return $this->respondOK();
    }
}
