<?php

namespace App\Twig;

use App\Service\CspNonceGenerator;
use Random\RandomException;

class CspNonceExtension
{
    public function __construct(private readonly CspNonceGenerator $nonceGenerator)
    {
    }

    /**
     * Returns the Content-Security-Policy nonce for the current request.
     *
     * Every inline <script> and <style> element in a template needs it, or the
     * browser will refuse to run it:
     *
     *     <script nonce="{{ csp_nonce() }}"> ... </script>
     *     {{ importmap('app', {'nonce': csp_nonce()}) }}
     *
     * Inline style="..." attributes do not need one; they are covered by
     * style-src-attr instead.
     *
     * @throws RandomException
     */
    #[\Twig\Attribute\AsTwigFunction(name: 'csp_nonce')]
    public function cspNonce(): string
    {
        return $this->nonceGenerator->getNonce();
    }
}
