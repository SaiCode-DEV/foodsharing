<?php

namespace Foodsharing\Modules\Login\WebAuthn;

use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA;
use Cose\Algorithm\Signature\EdDSA;
use Cose\Algorithm\Signature\RSA;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AndroidKeyAttestationStatementSupport;
use Webauthn\AttestationStatement\AppleAttestationStatementSupport;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\FidoU2FAttestationStatementSupport;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AttestationStatement\TPMAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\AuthenticationExtensions;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\Exception\WebauthnException;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;

class WebAuthnService
{
    use WebAuthnDomainTrait;

    private const SESSION_REGISTRATION_OPTIONS = 'webauthn_registration_options';
    private const SESSION_AUTHENTICATION_OPTIONS = 'webauthn_authentication_options';

    private WebAuthnGateway $gateway;
    private FoodsaverGateway $foodsaverGateway;
    private Session $session;
    private SerializerInterface $serializer;
    private AuthenticatorAttestationResponseValidator $attestationValidator;
    private AuthenticatorAssertionResponseValidator $assertionValidator;
    private NativeClock $clock;

    public function __construct(
        WebAuthnGateway $gateway,
        FoodsaverGateway $foodsaverGateway,
        Session $session
    ) {
        $this->gateway = $gateway;
        $this->foodsaverGateway = $foodsaverGateway;
        $this->session = $session;
        $this->clock = new NativeClock();

        // Setup WebAuthn components
        $this->setupWebAuthn();
    }

    private function setupWebAuthn(): void
    {
        // Attestation Statement Support Manager
        // Add support for multiple attestation formats
        $attestationStatementSupportManager = AttestationStatementSupportManager::create();
        $attestationStatementSupportManager->add(NoneAttestationStatementSupport::create());

        $attestationStatementSupportManager->add(FidoU2FAttestationStatementSupport::create());
        $attestationStatementSupportManager->add(AppleAttestationStatementSupport::create());
        $attestationStatementSupportManager->add(AndroidKeyAttestationStatementSupport::create());
        $attestationStatementSupportManager->add(TPMAttestationStatementSupport::create($this->clock));
        // Note: For production, consider adding more attestation formats:
        // - AndroidKeyAttestationStatementSupport
        // - AndroidSafetyNetAttestationStatementSupport
        // - AppleAttestationStatementSupport
        // - FidoU2FAttestationStatementSupport
        // - PackedAttestationStatementSupport
        // - TPMAttestationStatementSupport

        // Serializer
        $factory = new WebauthnSerializerFactory($attestationStatementSupportManager);
        $this->serializer = $factory->create();

        // Algorithm Manager
        $algorithmManager = Manager::create()
            ->add(
                ECDSA\ES256::create(),
                ECDSA\ES256K::create(),
                ECDSA\ES384::create(),
                ECDSA\ES512::create(),
                EdDSA\Ed256::create(),
                EdDSA\Ed512::create(),
                RSA\RS256::create(),
                RSA\RS384::create(),
                RSA\RS512::create(),
                RSA\PS256::create(),
                RSA\PS384::create(),
                RSA\PS512::create()
            );

        // Ceremony Step Manager
        $csmFactory = new CeremonyStepManagerFactory();
        $creationCSM = $csmFactory->creationCeremony();
        $requestCSM = $csmFactory->requestCeremony();

        // Validators
        $this->attestationValidator = AuthenticatorAttestationResponseValidator::create($creationCSM);
        $this->assertionValidator = AuthenticatorAssertionResponseValidator::create($requestCSM);
    }

    /**
     * Generate registration options for a user.
     */
    public function generateRegistrationOptions(?int $userId = null): array
    {
        if ($userId === null) {
            $userId = $this->session->id();
        }

        $user = $this->foodsaverGateway->getFoodsaverDetails($userId);
        if (!$user) {
            throw new \RuntimeException('User not found');
        }

        // Relying Party Entity
        $rpEntity = $this->getRpEntity();

        // User Entity
        $userEntity = PublicKeyCredentialUserEntity::create(
            $user['email'],
            (string)$user['id'],
            $user['name'] . ' ' . $user['nachname']
        );

        // Challenge
        $challenge = random_bytes(32);

        // Get existing credentials to exclude
        $existingCredentials = $this->gateway->findAllForUserEntity($userEntity);
        $excludeCredentials = array_map(
            fn (PublicKeyCredentialSource $credential): PublicKeyCredentialDescriptor => $credential->getPublicKeyCredentialDescriptor(),
            $existingCredentials
        );

        // Authenticator Selection Criteria
        $authenticatorSelectionCriteria = AuthenticatorSelectionCriteria::create(
            userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
        );

        // Public Key Credential Parameters (supported algorithms)
        $pubKeyCredParams = [
            PublicKeyCredentialParameters::create('public-key', \Cose\Algorithms::COSE_ALGORITHM_ES256),
            PublicKeyCredentialParameters::create('public-key', \Cose\Algorithms::COSE_ALGORITHM_RS256),
            PublicKeyCredentialParameters::create('public-key', \Cose\Algorithms::COSE_ALGORITHM_ES256K),
            PublicKeyCredentialParameters::create('public-key', \Cose\Algorithms::COSE_ALGORITHM_ES384),
        ];

        // Create options
        $publicKeyCredentialCreationOptions = PublicKeyCredentialCreationOptions::create(
            $rpEntity,
            $userEntity,
            $challenge,
            $pubKeyCredParams,
            timeout: 60000,
            excludeCredentials: $excludeCredentials,
            authenticatorSelection: $authenticatorSelectionCriteria
        );

        // Store in session for verification
        $this->session->set(self::SESSION_REGISTRATION_OPTIONS, [
            'options' => $publicKeyCredentialCreationOptions,
            'user_entity' => $userEntity,
        ]);

        // Serialize to JSON
        return json_decode(
            $this->serializer->serialize(
                $publicKeyCredentialCreationOptions,
                'json',
                [\Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer::SKIP_NULL_VALUES => true]
            ),
            true
        );
    }

    /**
     * Verify registration response and save credential.
     */
    public function verifyRegistration(array $credentialData, ?string $name = null): PublicKeyCredentialSource
    {
        $sessionData = $this->session->get(self::SESSION_REGISTRATION_OPTIONS);
        if (!$sessionData) {
            throw new \RuntimeException('No registration in progress');
        }

        /** @var PublicKeyCredentialCreationOptions $publicKeyCredentialCreationOptions */
        $publicKeyCredentialCreationOptions = $sessionData['options'];
        /** @var PublicKeyCredentialUserEntity $userEntity */
        $userEntity = $sessionData['user_entity'];

        // Deserialize credential
        $publicKeyCredential = $this->serializer->deserialize(
            json_encode($credentialData),
            PublicKeyCredential::class,
            'json'
        );

        $response = $publicKeyCredential->response;
        if (!$response instanceof AuthenticatorAttestationResponse) {
            throw new \RuntimeException('Invalid response type');
        }

        try {
            // Verify the response
            $publicKeyCredentialSource = $this->attestationValidator->check(
                $response,
                $publicKeyCredentialCreationOptions,
                $this->getHost()
            );

            // Save to database with current RP ID
            $this->gateway->saveCredentialSource($publicKeyCredentialSource, $name, $this->getRpId());

            // Clear session data
            $this->session->set(self::SESSION_REGISTRATION_OPTIONS, null);

            return $publicKeyCredentialSource;
        } catch (WebauthnException $e) {
            throw new \RuntimeException('Registration verification failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Generate authentication options - supports usernameless authentication.
     * Passkey credentials are unique and don't require email/username.
     */
    public function generateAuthenticationOptions(): array
    {
        // Challenge
        $challenge = random_bytes(32);

        // Create options for usernameless authentication
        // No allowedCredentials means any registered passkey can be used
        $publicKeyCredentialRequestOptions = PublicKeyCredentialRequestOptions::create(
            $challenge,
            timeout: 60000,
            rpId: $this->getRpId(),
            allowCredentials: [], // Empty = usernameless authentication
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            extensions: AuthenticationExtensions::create()
        );

        // Store in session for verification
        $this->session->set(self::SESSION_AUTHENTICATION_OPTIONS, [
            'options' => $publicKeyCredentialRequestOptions,
        ]);

        // Serialize to JSON
        return json_decode(
            $this->serializer->serialize(
                $publicKeyCredentialRequestOptions,
                'json',
                [\Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer::SKIP_NULL_VALUES => true]
            ),
            true
        );
    }

    /**
     * Verify authentication response.
     * Returns the user ID from the credential - no email/username needed!
     */
    public function verifyAuthentication(array $assertionData): int
    {
        $sessionData = $this->session->get(self::SESSION_AUTHENTICATION_OPTIONS);
        if (!$sessionData) {
            throw new \RuntimeException('No authentication in progress');
        }

        /** @var PublicKeyCredentialRequestOptions $publicKeyCredentialRequestOptions */
        $publicKeyCredentialRequestOptions = $sessionData['options'];

        // Deserialize credential
        $publicKeyCredential = $this->serializer->deserialize(
            json_encode($assertionData),
            PublicKeyCredential::class,
            'json'
        );

        $response = $publicKeyCredential->response;
        if (!$response instanceof AuthenticatorAssertionResponse) {
            throw new \RuntimeException('Invalid response type');
        }

        try {
            // Find the credential by its ID
            $publicKeyCredentialSource = $this->gateway->findOneByCredentialId($publicKeyCredential->rawId);
            if (!$publicKeyCredentialSource) {
                throw new \RuntimeException('Credential not found');
            }

            // Get user ID from the credential itself
            $userId = (int)$publicKeyCredentialSource->userHandle;

            // Verify the response
            $publicKeyCredentialSource = $this->assertionValidator->check(
                $publicKeyCredentialSource,
                $response,
                $publicKeyCredentialRequestOptions,
                $this->getHost(),
                (string)$userId // userHandle for verification
            );

            // Update counter and last used
            $this->gateway->updateCredentialSource($publicKeyCredentialSource);

            // Clear session data
            $this->session->set(self::SESSION_AUTHENTICATION_OPTIONS, null);

            return $userId;
        } catch (WebauthnException $e) {
            throw new \RuntimeException('Authentication verification failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get Relying Party Entity.
     */
    private function getRpEntity(): PublicKeyCredentialRpEntity
    {
        $rpId = $this->getRpId();

        return PublicKeyCredentialRpEntity::create(
            'foodsharing',
            $rpId
        );
    }

    /**
     * Get Relying Party ID (domain).
     */
    private function getRpId(): string
    {
        return $this->getWebAuthnDomain();
    }

    /**
     * Get host for verification.
     */
    private function getHost(): string
    {
        return 'https://' . $this->getRpId();
    }
}
