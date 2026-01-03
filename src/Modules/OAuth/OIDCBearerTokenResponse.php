<?php

declare(strict_types=1);

namespace Foodsharing\Modules\OAuth;

use Lcobucci\JWT\Token\Builder;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use OpenIDConnectServer\ClaimExtractor;
use OpenIDConnectServer\IdTokenResponse as BaseIdTokenResponse;
use OpenIDConnectServer\Repositories\IdentityProviderInterface;

class OIDCBearerTokenResponse extends BaseIdTokenResponse
{
    protected ?string $nonce = null;

    public function __construct(
        IdentityProviderInterface $identityProvider,
        ClaimExtractor $claimExtractor
    ) {
        parent::__construct($identityProvider, $claimExtractor);
    }

    protected function getBuilder(AccessTokenEntityInterface $accessToken, UserEntityInterface $userEntity): Builder
    {
        $builder = parent::getBuilder($accessToken, $userEntity);

        $issuer = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $builder = $builder->issuedBy($issuer);

        if (!empty($this->nonce)) {
            $builder = $builder->withClaim('nonce', $this->nonce);
        }

        return $builder;
    }

    public function setNonce(?string $nonce): void
    {
        $this->nonce = $nonce;
    }

    public function getNonce(): ?string
    {
        return $this->nonce;
    }
}
