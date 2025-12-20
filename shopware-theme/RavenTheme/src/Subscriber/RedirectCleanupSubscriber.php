<?php declare(strict_types=1);

namespace RavenTheme\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Cleans up redirect URLs to prevent Cloudflare WAF from blocking JSON in URL parameters.
 * The _noStore parameter in redirectParameters causes 403 errors from Cloudflare.
 */
class RedirectCleanupSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // Use high priority to run early, before other subscribers might modify the response
        return [
            KernelEvents::RESPONSE => ['onResponse', 10000],
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        // Only process redirects
        if (!$response->isRedirection()) {
            return;
        }

        $location = $response->headers->get('Location');
        if (!$location) {
            return;
        }

        // Check if the redirect URL contains redirectParameters (which may contain _noStore)
        // Also check for URL-encoded variants
        $hasRedirectParams = strpos($location, 'redirectParameters') !== false;
        $hasNoStore = strpos($location, '_noStore') !== false || strpos($location, '%22_noStore%22') !== false;

        if (!$hasRedirectParams || !$hasNoStore) {
            return;
        }

        // Parse the URL - handle both relative and absolute URLs
        $parsed = parse_url($location);
        if (!isset($parsed['query'])) {
            return;
        }

        parse_str($parsed['query'], $queryParams);

        // Remove or clean up redirectParameters if it contains _noStore
        if (!isset($queryParams['redirectParameters'])) {
            return;
        }

        $redirectParams = $queryParams['redirectParameters'];
        $modified = false;

        // Try to decode JSON (parse_str already URL-decodes the value)
        $decoded = json_decode($redirectParams, true);
        if (is_array($decoded)) {
            // Remove _noStore from the parameters
            if (isset($decoded['_noStore'])) {
                unset($decoded['_noStore']);
                $modified = true;
            }

            // If empty after removing _noStore, remove the parameter entirely
            if (empty($decoded)) {
                unset($queryParams['redirectParameters']);
            } else {
                $queryParams['redirectParameters'] = json_encode($decoded);
            }
        } elseif ($redirectParams === '{"_noStore":true}') {
            // Direct match - remove entirely
            unset($queryParams['redirectParameters']);
            $modified = true;
        }

        if (!$modified) {
            return;
        }

        // Rebuild the URL - handle both relative and absolute URLs
        $newLocation = '';

        // Absolute URL (has scheme)
        if (isset($parsed['scheme'])) {
            $newLocation = $parsed['scheme'] . '://';
            if (isset($parsed['host'])) {
                $newLocation .= $parsed['host'];
            }
            if (isset($parsed['port'])) {
                $newLocation .= ':' . $parsed['port'];
            }
        }

        // Add path
        if (isset($parsed['path'])) {
            $newLocation .= $parsed['path'];
        }

        // Add query string
        $newQuery = http_build_query($queryParams);
        if ($newQuery) {
            $newLocation .= '?' . $newQuery;
        }

        // Add fragment
        if (isset($parsed['fragment'])) {
            $newLocation .= '#' . $parsed['fragment'];
        }

        $response->headers->set('Location', $newLocation);
    }
}
