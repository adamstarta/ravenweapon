<?php declare(strict_types=1);

namespace RavenTheme\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Validates Cloudflare Turnstile tokens on registration and login forms.
 * Blocks submissions that fail Turnstile verification to prevent spam bots.
 */
class TurnstileValidationSubscriber implements EventSubscriberInterface
{
    private const TURNSTILE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    // Turnstile keys - Site key is public (used in frontend), Secret key is private
    private const TURNSTILE_SECRET_KEY = '0x4AAAAAACLCpE4d1wbexCIu9elg2QE6ZoU';

    // Routes to protect with Turnstile
    private const PROTECTED_ROUTES = [
        'frontend.account.register.save',
        'frontend.account.login',
    ];

    private RequestStack $requestStack;
    private RouterInterface $router;

    public function __construct(RequestStack $requestStack, RouterInterface $router)
    {
        $this->requestStack = $requestStack;
        $this->router = $router;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onController', 10],
        ];
    }

    public function onController(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        // Only validate on protected routes
        if (!in_array($route, self::PROTECTED_ROUTES, true)) {
            return;
        }

        // Only validate POST requests (form submissions)
        if ($request->getMethod() !== 'POST') {
            return;
        }

        // Get the Turnstile token from the form
        $turnstileResponse = $request->request->get('cf-turnstile-response');

        // If no token provided, reject the request
        if (empty($turnstileResponse)) {
            $this->rejectRequest($event, $route, 'Bitte bestätigen Sie, dass Sie kein Roboter sind.');
            return;
        }

        // Validate the token with Cloudflare
        if (!$this->validateTurnstileToken($turnstileResponse, $request->getClientIp())) {
            $this->rejectRequest($event, $route, 'Verifizierung fehlgeschlagen. Bitte versuchen Sie es erneut.');
            return;
        }

        // Token is valid, allow the request to proceed
    }

    private function validateTurnstileToken(string $token, ?string $remoteIp): bool
    {
        $postData = [
            'secret' => self::TURNSTILE_SECRET_KEY,
            'response' => $token,
        ];

        if ($remoteIp) {
            $postData['remoteip'] = $remoteIp;
        }

        // Use cURL to make the request
        $ch = curl_init(self::TURNSTILE_VERIFY_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // If the API call failed, fail-open to not block legitimate users
        if ($response === false || $httpCode !== 200) {
            // Log the error but allow the request
            return true;
        }

        $result = json_decode($response, true);

        // Check if validation was successful
        return isset($result['success']) && $result['success'] === true;
    }

    private function rejectRequest(ControllerEvent $event, string $route, string $errorMessage): void
    {
        $session = $this->requestStack->getSession();

        // Add flash message for the error
        $session->getFlashBag()->add('danger', $errorMessage);

        // Determine redirect URL based on the route
        if ($route === 'frontend.account.register.save') {
            $redirectUrl = $this->router->generate('frontend.account.register.page');
        } else {
            $redirectUrl = $this->router->generate('frontend.account.login.page');
        }

        // Replace the controller with a redirect response
        $event->setController(function () use ($redirectUrl) {
            return new RedirectResponse($redirectUrl);
        });
    }
}
