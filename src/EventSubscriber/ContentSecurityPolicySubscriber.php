<?php

namespace App\EventSubscriber;

use App\Service\CspNonceGenerator;
use Random\RandomException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sends the Content-Security-Policy header on every response the application
 * produces.
 *
 * The policy lives here and not in the nginx vhosts under service-config/nginx
 * because it carries a per-request nonce, and nginx has no cryptographically
 * secure source to generate one from ($request_id comes from a plain PRNG).
 * Those vhosts must therefore not send a Content-Security-Policy header of
 * their own: a browser that receives two of them enforces the intersection,
 * which would block the very scripts the nonce exists to allow.
 */
readonly class ContentSecurityPolicySubscriber implements EventSubscriberInterface
{
    /**
     * Static HTML files that are served through a controller instead of being
     * rendered by Twig can carry this marker where a nonce is needed; it is
     * substituted on the way out. See public/resources/turnstile_html/.
     */
    public const string NONCE_PLACEHOLDER = '%%CSP_NONCE%%';

    public function __construct(private CspNonceGenerator $nonceGenerator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Early, so the nonce is reset before anything can render with it.
            KernelEvents::REQUEST => ['onKernelRequest', 1024],
            // Late, but still ahead of the web debug toolbar listener (-128),
            // which reads this header to nonce its own injected markup.
            KernelEvents::RESPONSE => ['onKernelResponse', -100],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->nonceGenerator->reset();
    }

    /**
     * @throws RandomException
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();

        // Never override a policy a controller deliberately set for itself.
        if ($response->headers->has('Content-Security-Policy')) {
            return;
        }

        $request = $event->getRequest();

        $nonce = $this->nonceGenerator->getNonce();
        $response->headers->set('Content-Security-Policy', $this->buildPolicy($nonce, $request));
        $this->injectNonceIntoStaticHtml($response, $nonce);
    }

    /**
     * Replaces the nonce marker in static HTML that a controller streamed
     * straight from disk, so those pages keep working under the same policy as
     * the Twig-rendered ones.
     */
    private function injectNonceIntoStaticHtml(Response $response, string $nonce): void
    {
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return;
        }

        $content = $response->getContent();

        if (!is_string($content) || !str_contains($content, self::NONCE_PLACEHOLDER)) {
            return;
        }

        $response->setContent(str_replace(self::NONCE_PLACEHOLDER, $nonce, $content));
    }

    /**
     * @param string $nonce the per-request nonce, already base64 encoded
     */
    private function buildPolicy(string $nonce, Request $request): string
    {
        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",

            // 'data:' is still required here: Symfony AssetMapper loads every
            // stylesheet that JavaScript imports (Leaflet's, via the vendor
            // ux-leaflet-map controller; Quill's, via quill_controller.js)
            // through a generated "data:application/javascript,..." module.
            //
            // It is a known weakness -- an injected
            // <script src="data:text/javascript,..."> is allowed by it and so
            // sidesteps the nonce. Removing it means taking those stylesheets
            // out of the JavaScript module graph (a <link> tag for Quill's, a
            // local override of the vendor Leaflet controller), or switching
            // script-src to 'strict-dynamic'.
            sprintf("script-src 'self' 'nonce-%s' data: https://challenges.cloudflare.com", $nonce),

            // One directive covers both <style> elements and style="..."
            // attributes, and neither is exempted: every inline style attribute
            // has been moved into a CSS class, and the few values that are
            // computed per request are applied through the CSSOM by a Stimulus
            // controller (see assets/controllers/background-image_controller.js),
            // which CSP does not restrict. The same goes for scripts, so an
            // injected onerror=/onclick= payload cannot execute either.
            sprintf("style-src 'self' 'nonce-%s' https://fonts.googleapis.com", $nonce),

            "img-src 'self' data: https://tile.openstreetmap.org",
            "font-src 'self' https://fonts.gstatic.com",
            "connect-src 'self' https://challenges.cloudflare.com",
            "frame-src https://challenges.cloudflare.com",
        ];

        // Only meaningful over TLS, and it would break a deployment that is
        // deliberately served over plain HTTP (service-config/nginx/sites/).
        if ($request->isSecure()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives) . ';';
    }
}
