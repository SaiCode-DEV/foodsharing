<?php

namespace Foodsharing\Modules\Login\WebAuthn;

/**
 * Trait for extracting WebAuthn domain (RP ID) from HTTP request.
 * Shared between WebAuthnService and WebAuthnGateway for consistency.
 */
trait WebAuthnDomainTrait
{
    /**
     * Get the WebAuthn domain (RP ID) from the current request.
     *
     * IMPORTANT: WebAuthn passkeys are bound to a specific RP ID (domain).
     * Passkeys registered on one domain (e.g., foodsharing.de) will NOT work
     * on another domain (e.g., foodsharing.at) due to WebAuthn security model.
     *
     * However, subdomains CAN share passkeys with their parent domain:
     * - RP ID "foodsharing.de" works on both foodsharing.de and beta.foodsharing.de
     * - RP ID "foodsharing.at" works on both foodsharing.at and beta.foodsharing.at
     *
     * For multi-domain deployments (foodsharing.de, .at, .network, .ch), users
     * will need to register separate passkeys on each TLD.
     *
     * @return string The domain to use for WebAuthn RP ID
     */
    private function getWebAuthnDomain(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'foodsharing.de';
        // Strip port from host if it exists (important for dev environments)
        $hostWithoutPort = (string)preg_replace('/:\d+$/', '', $host);

        // Check if it's an IP address - use as-is (for dev/test environments)
        if (filter_var($hostWithoutPort, FILTER_VALIDATE_IP)) {
            return $hostWithoutPort;
        }

        // Format: ['foodsharing.de', 'foodsharing.at', 'foodsharing.network', 'foodsharing.ch']
        // If SESSION_COOKIE_DOMAINS is defined, use it for consistency with session handling
        // This enables subdomain sharing (beta.foodsharing.de can use foodsharing.de passkeys)
        if (defined('SESSION_COOKIE_DOMAINS')) {
            $domains = (array)SESSION_COOKIE_DOMAINS;

            if (count($domains) > 0) {
                // Check if current host matches or is a subdomain of any allowed domain
                foreach ($domains as $domain) {
                    $domain = trim($domain);
                    if ($hostWithoutPort === $domain ||
                        str_ends_with($hostWithoutPort, '.' . $domain)) {
                        // Return the parent domain to enable subdomain sharing
                        // e.g., beta.foodsharing.de → foodsharing.de
                        return $domain;
                    }
                }
            }
        }

        $parts = explode('.', $hostWithoutPort);

        if (count($parts) > 2) {
            return implode('.', array_slice($parts, -2));
        }

        return $hostWithoutPort;
    }
}
