<?php

namespace App\Service;

use Random\RandomException;

/**
 * Holds the Content-Security-Policy nonce for the current request.
 *
 * The value is generated once and then reused for the rest of the request so
 * that what is rendered into the page (App\Twig\CspNonceExtension) and what is
 * written into the header (App\EventSubscriber\ContentSecurityPolicySubscriber)
 * can never drift apart.
 */
class CspNonceGenerator
{
    private ?string $nonce = null;

    /**
     * @throws RandomException
     */
    public function getNonce(): string
    {
        // The CSP spec asks for at least 128 bits from a cryptographically
        // secure source; random_bytes() throws rather than return weak output.
        return $this->nonce ??= base64_encode(random_bytes(16));
    }

    /**
     * Drops the current nonce so the next request starts a fresh one. A nonce
     * that is reused across responses is no better than 'unsafe-inline'.
     */
    public function reset(): void
    {
        $this->nonce = null;
    }
}
