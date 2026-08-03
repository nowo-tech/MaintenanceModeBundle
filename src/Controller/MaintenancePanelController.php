<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Controller;

use DateTimeImmutable;
use Nowo\MaintenanceModeBundle\Security\MaintenanceAccessGateInterface;
use Nowo\MaintenanceModeBundle\Security\MaintenanceModeAccessCheckerInterface;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * Simple Twig CRUD panel to enable / disable maintenance and view history.
 */
final class MaintenancePanelController
{
    public const CSRF_ENABLE         = 'nowo_maintenance_enable';
    public const CSRF_DISABLE        = 'nowo_maintenance_disable';
    public const CSRF_SCHEDULE       = 'nowo_maintenance_schedule';
    public const CSRF_CLEAR_SCHEDULE = 'nowo_maintenance_clear_schedule';
    public const CSRF_LOGIN          = 'nowo_maintenance_login';
    public const CSRF_LOGOUT         = 'nowo_maintenance_logout';

    /**
     * @param array{page: string, panel_layout: string, panel_index: string, panel_login: string, panel_history: string} $templates
     */
    public function __construct(
        private readonly MaintenanceManager $manager,
        private readonly MaintenanceAccessGateInterface $accessGate,
        private readonly Environment $twig,
        private readonly array $templates,
        private readonly string $pathPrefix = '/_maintenance',
        private readonly ?CsrfTokenManagerInterface $csrfTokenManager = null,
        private readonly ?MaintenanceModeAccessCheckerInterface $accessChecker = null,
        private readonly bool $allowUnauthenticated = true,
    ) {
    }

    #[Route(path: '', name: 'nowo_maintenance_mode_panel_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if (($response = $this->denyUnlessGranted($request)) instanceof Response) {
            return $response;
        }

        $flashes = [];
        if ($request->hasSession()) {
            $session = $request->getSession();
            if ($session instanceof FlashBagAwareSessionInterface) {
                $flashes = $session->getFlashBag()->all();
            }
        }

        return new Response($this->twig->render($this->templates['panel_index'], [
            'state'             => $this->manager->getState(),
            'path_prefix'       => $this->pathPrefix,
            'layout'            => $this->templates['panel_layout'],
            'flashes'           => $flashes,
            'password_required' => $this->accessGate->isPasswordRequired(),
        ]));
    }

    #[Route(path: '/enable', name: 'nowo_maintenance_mode_panel_enable', methods: ['POST'])]
    public function enable(Request $request): Response
    {
        if (($response = $this->denyUnlessGranted($request)) instanceof Response) {
            return $response;
        }
        if (!$this->isCsrfValid($request, self::CSRF_ENABLE)) {
            return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
        }

        $message = $request->request->getString('message');
        $this->manager->enable($message !== '' ? $message : null, 'panel');
        $this->flash($request, 'success', 'panel.flash.enabled');

        return new RedirectResponse($this->pathPrefix);
    }

    #[Route(path: '/disable', name: 'nowo_maintenance_mode_panel_disable', methods: ['POST'])]
    public function disable(Request $request): Response
    {
        if (($response = $this->denyUnlessGranted($request)) instanceof Response) {
            return $response;
        }
        if (!$this->isCsrfValid($request, self::CSRF_DISABLE)) {
            return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
        }

        $this->manager->disable('panel');
        $this->flash($request, 'success', 'panel.flash.disabled');

        return new RedirectResponse($this->pathPrefix);
    }

    #[Route(path: '/schedule', name: 'nowo_maintenance_mode_panel_schedule', methods: ['POST'])]
    public function schedule(Request $request): Response
    {
        if (($response = $this->denyUnlessGranted($request)) instanceof Response) {
            return $response;
        }
        if (!$this->isCsrfValid($request, self::CSRF_SCHEDULE)) {
            return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
        }

        $enableRaw  = $request->request->getString('scheduled_enable_at');
        $disableRaw = $request->request->getString('scheduled_disable_at');
        $message    = $request->request->getString('message');

        $this->manager->schedule(
            enableAt: $enableRaw !== '' ? new DateTimeImmutable($enableRaw) : null,
            disableAt: $disableRaw !== '' ? new DateTimeImmutable($disableRaw) : null,
            message: $message !== '' ? $message : null,
            updatedBy: 'panel',
            clearMissing: true,
        );
        $this->flash($request, 'success', 'panel.flash.schedule_saved');

        return new RedirectResponse($this->pathPrefix);
    }

    #[Route(path: '/clear-schedule', name: 'nowo_maintenance_mode_panel_clear_schedule', methods: ['POST'])]
    public function clearSchedule(Request $request): Response
    {
        if (($response = $this->denyUnlessGranted($request)) instanceof Response) {
            return $response;
        }
        if (!$this->isCsrfValid($request, self::CSRF_CLEAR_SCHEDULE)) {
            return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
        }

        $this->manager->clearSchedule('panel');
        $this->flash($request, 'success', 'panel.flash.schedule_cleared');

        return new RedirectResponse($this->pathPrefix);
    }

    #[Route(path: '/history', name: 'nowo_maintenance_mode_panel_history', methods: ['GET'])]
    public function history(Request $request): Response
    {
        if (($response = $this->denyUnlessGranted($request)) instanceof Response) {
            return $response;
        }

        return new Response($this->twig->render($this->templates['panel_history'], [
            'entries'     => $this->manager->history(100),
            'path_prefix' => $this->pathPrefix,
            'layout'      => $this->templates['panel_layout'],
        ]));
    }

    #[Route(path: '/login', name: 'nowo_maintenance_mode_panel_login', methods: ['GET', 'POST'])]
    public function login(Request $request): Response
    {
        if (($response = $this->denyUnlessRoleAccess()) instanceof Response) {
            return $response;
        }

        if (!$this->accessGate->isPasswordRequired() || $this->accessGate->isGranted($request)) {
            return new RedirectResponse($this->pathPrefix);
        }

        $error = null;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfValid($request, self::CSRF_LOGIN)) {
                return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
            }
            $password = $request->request->getString('password');
            if ($this->accessGate->authenticate($request, $password)) {
                return new RedirectResponse($this->pathPrefix);
            }
            $error = 'Invalid password.';
        }

        return new Response($this->twig->render($this->templates['panel_login'], [
            'error'       => $error,
            'path_prefix' => $this->pathPrefix,
            'layout'      => $this->templates['panel_layout'],
        ]), $error !== null ? Response::HTTP_UNAUTHORIZED : Response::HTTP_OK);
    }

    #[Route(path: '/logout', name: 'nowo_maintenance_mode_panel_logout', methods: ['POST'])]
    public function logout(Request $request): Response
    {
        if (!$this->isCsrfValid($request, self::CSRF_LOGOUT)) {
            return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
        }
        $this->accessGate->revoke($request);

        return new RedirectResponse($this->pathPrefix . '/login');
    }

    private function denyUnlessGranted(Request $request): ?Response
    {
        if (($response = $this->denyUnlessRoleAccess()) instanceof Response) {
            return $response;
        }

        if ($this->accessGate->isGranted($request)) {
            return null;
        }

        return new RedirectResponse($this->pathPrefix . '/login');
    }

    private function denyUnlessRoleAccess(): ?Response
    {
        if ($this->allowUnauthenticated) {
            return null;
        }

        if (!$this->accessChecker instanceof MaintenanceModeAccessCheckerInterface || !$this->accessChecker->canAccess(null)) {
            return new Response('Access denied.', Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    private function isCsrfValid(Request $request, string $tokenId): bool
    {
        // Fail-closed (REQ-SEC-005): deny mutations when CSRF cannot be validated.
        if (!$this->csrfTokenManager instanceof CsrfTokenManagerInterface) {
            return false;
        }

        $token = $request->request->getString('_token');

        return $this->csrfTokenManager->isTokenValid(new CsrfToken($tokenId, $token));
    }

    private function flash(Request $request, string $type, string $message): void
    {
        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($type, $message);
        }
    }
}
