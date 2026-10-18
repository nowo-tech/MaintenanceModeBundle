<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Controller;

use DateTimeImmutable;
use Nowo\MaintenanceModeBundle\Form\ClearScheduleType;
use Nowo\MaintenanceModeBundle\Form\DisableMaintenanceType;
use Nowo\MaintenanceModeBundle\Form\EnableMaintenanceType;
use Nowo\MaintenanceModeBundle\Form\LoginMaintenanceType;
use Nowo\MaintenanceModeBundle\Form\LogoutMaintenanceType;
use Nowo\MaintenanceModeBundle\Form\ScheduleMaintenanceType;
use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Nowo\MaintenanceModeBundle\Panel\PanelView;
use Nowo\MaintenanceModeBundle\Security\MaintenanceAccessGateInterface;
use Nowo\MaintenanceModeBundle\Security\MaintenanceModeAccessCheckerInterface;
use Nowo\MaintenanceModeBundle\Security\PasswordMaintenanceAccessGate;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Nowo\MaintenanceModeBundle\View\MaintenancePages;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use function array_merge;
use function is_string;
use function max;

/**
 * Twig panel to enable / disable maintenance and view history (Symfony Forms + CSRF). Pages render on the UiKit page
 * contract (REQ-UI-003): settings scaffold (panel), list scaffold (history), auth scaffold (password login).
 */
final class MaintenancePanelController
{
    private readonly string $pathPrefix;

    public const CSRF_ENABLE         = 'nowo_maintenance_enable';
    public const CSRF_DISABLE        = 'nowo_maintenance_disable';
    public const CSRF_SCHEDULE       = 'nowo_maintenance_schedule';
    public const CSRF_CLEAR_SCHEDULE = 'nowo_maintenance_clear_schedule';
    public const CSRF_LOGIN          = 'nowo_maintenance_login';
    public const CSRF_LOGOUT         = 'nowo_maintenance_logout';

    public function __construct(
        private readonly MaintenanceManager $manager,
        private readonly MaintenanceAccessGateInterface $accessGate,
        private readonly FormFactoryInterface $formFactory,
        private readonly PanelView $view,
        private readonly ?CsrfTokenManagerInterface $csrfTokenManager = null,
        private readonly ?MaintenanceModeAccessCheckerInterface $accessChecker = null,
        private readonly bool $allowUnauthenticated = true,
        private readonly int $historyLimit = 100,
        private readonly int $historyPerPage = 25,
    ) {
        $this->pathPrefix = $view->pathPrefix;
    }

    #[Route(path: '', name: 'nowo_maintenance_mode_panel_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if (($response = $this->denyUnlessGranted($request)) instanceof Response) {
            return $response;
        }

        // 1.x context variable: peek only, the layout's flashes partial consumes them.
        $flashes = [];
        if ($request->hasSession()) {
            $session = $request->getSession();
            if ($session instanceof FlashBagAwareSessionInterface) {
                $flashes = $session->getFlashBag()->peekAll();
            }
        }

        $state            = $this->manager->getState();
        $passwordRequired = $this->accessGate->isPasswordRequired();
        $forms            = [
            'enable_form'         => $this->createEnableForm($state)->createView(),
            'disable_form'        => $this->createDisableForm()->createView(),
            'schedule_form'       => $this->createScheduleForm($state)->createView(),
            'clear_schedule_form' => $this->createClearScheduleForm()->createView(),
            'logout_form'         => $this->createLogoutForm()->createView(),
        ];

        return $this->view->render('index', MaintenancePages::panel(
            $state,
            $forms['enable_form'],
            $forms['disable_form'],
            $forms['schedule_form'],
            $forms['clear_schedule_form'],
            $passwordRequired ? $forms['logout_form'] : null,
            $this->view->nav('panel'),
            $this->view->href('/disable'),
            $this->view->href('/clear-schedule'),
            $this->view->href('/logout'),
            $this->view->layout(),
        ) + $forms + [
            'state'             => $state,
            'flashes'           => $flashes,
            'password_required' => $passwordRequired,
        ]);
    }

    #[Route(path: '/enable', name: 'nowo_maintenance_mode_panel_enable', methods: ['POST'])]
    public function enable(Request $request): Response
    {
        if (($response = $this->denyUnlessGranted($request)) instanceof Response) {
            return $response;
        }

        $form = $this->createEnableForm($this->manager->getState());
        if (!$this->handleValidForm($form, $request)) {
            return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
        }

        /** @var array{message?: string|null} $data */
        $data    = $form->getData() ?? [];
        $message = isset($data['message']) && is_string($data['message']) ? $data['message'] : '';
        // @igor-ignore - Mutates attached request-scoped or value object; not worker singleton cache
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

        $form = $this->createDisableForm();
        if (!$this->handleValidForm($form, $request)) {
            return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
        }

        // @igor-ignore - Mutates attached request-scoped or value object; not worker singleton cache

        // @igor-ignore - Mutates attached request-scoped or value object; not worker singleton cache
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

        $form = $this->createScheduleForm($this->manager->getState());
        if (!$this->handleValidForm($form, $request)) {
            return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
        }

        /** @var array{scheduled_enable_at?: DateTimeImmutable|null, scheduled_disable_at?: DateTimeImmutable|null, message?: string|null} $data */
        $data    = $form->getData() ?? [];
        $message = isset($data['message']) && is_string($data['message']) ? $data['message'] : '';

        $this->manager->schedule(
            enableAt: $data['scheduled_enable_at'] ?? null,
            disableAt: $data['scheduled_disable_at'] ?? null,
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

        $form = $this->createClearScheduleForm();
        if (!$this->handleValidForm($form, $request)) {
            return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
            // @igor-ignore - Mutates attached request-scoped or value object; not worker singleton cache
        }

        // @igor-ignore - Mutates attached request-scoped or value object; not worker singleton cache

        // @igor-ignore - Mutates attached request-scoped or value object; not worker singleton cache
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

        $entries = $this->manager->history($this->historyLimit);
        $page    = max(1, $request->query->getInt('page', 1));

        return $this->view->render('history', [
            'page'    => MaintenancePages::history($entries, $page, $this->historyPerPage, $this->view->nav('history'), $this->view->layout()),
            'entries' => $entries,
        ]);
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

        $form   = $this->createLoginForm();
        $error  = null;
        $status = null;
        if ($request->isMethod('POST')) {
            if (!$this->handleValidForm($form, $request)) {
                return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
            }
            /** @var array{password?: string|null} $data */
            $data     = $form->getData() ?? [];
            $password = isset($data['password']) && is_string($data['password']) ? $data['password'] : '';
            if ($this->accessGate instanceof PasswordMaintenanceAccessGate && $this->accessGate->isLockedOut($request)) {
                $error  = 'Too many failed attempts. Try again later.';
                $status = Response::HTTP_TOO_MANY_REQUESTS;
            } elseif ($this->accessGate->authenticate($request, $password)) {
                return new RedirectResponse($this->pathPrefix);
            } else {
                $error = 'Invalid password.';
            }
        }

        $locked    = $status === Response::HTTP_TOO_MANY_REQUESTS;
        $loginForm = $form->createView();

        return $this->view->render('login', [
            'page'       => MaintenancePages::login($loginForm, $error, $locked),
            'error'      => $error,
            'locked'     => $locked,
            'login_form' => $loginForm,
        ], $status ?? ($error !== null ? Response::HTTP_UNAUTHORIZED : Response::HTTP_OK));
    }

    #[Route(path: '/logout', name: 'nowo_maintenance_mode_panel_logout', methods: ['POST'])]
    public function logout(Request $request): Response
    {
        $form = $this->createLogoutForm();
        if (!$this->handleValidForm($form, $request)) {
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

    /**
     * @param FormInterface<array<string, mixed>|null> $form
     */
    private function handleValidForm(FormInterface $form, Request $request): bool
    {
        // Fail-closed (REQ-SEC-005): deny mutations when CSRF cannot be validated.
        if (!$this->csrfTokenManager instanceof CsrfTokenManagerInterface) {
            return false;
        }

        $form->handleRequest($request);

        return $form->isSubmitted() && $form->isValid();
    }

    /**
     * @return FormInterface<array<string, mixed>|null>
     */
    private function createEnableForm(MaintenanceState $state): FormInterface
    {
        return $this->formFactory->createNamed('', EnableMaintenanceType::class, [
            'message' => $state->getMessage(),
        ], $this->formOptions([
            'action' => $this->pathPrefix . '/enable',
        ]));
    }

    /**
     * @return FormInterface<array<string, mixed>|null>
     */
    private function createDisableForm(): FormInterface
    {
        return $this->formFactory->createNamed('', DisableMaintenanceType::class, null, $this->formOptions([
            'action' => $this->pathPrefix . '/disable',
        ]));
    }

    /**
     * @return FormInterface<array<string, mixed>|null>
     */
    private function createScheduleForm(MaintenanceState $state): FormInterface
    {
        return $this->formFactory->createNamed('', ScheduleMaintenanceType::class, [
            'scheduled_enable_at'  => $state->getScheduledEnableAt(),
            'scheduled_disable_at' => $state->getScheduledDisableAt(),
            'message'              => $state->getMessage(),
        ], $this->formOptions([
            'action' => $this->pathPrefix . '/schedule',
        ]));
    }

    /**
     * @return FormInterface<array<string, mixed>|null>
     */
    private function createClearScheduleForm(): FormInterface
    {
        return $this->formFactory->createNamed('', ClearScheduleType::class, null, $this->formOptions([
            'action' => $this->pathPrefix . '/clear-schedule',
        ]));
    }

    /**
     * @return FormInterface<array<string, mixed>|null>
     */
    private function createLoginForm(): FormInterface
    {
        return $this->formFactory->createNamed('', LoginMaintenanceType::class, null, $this->formOptions([
            'action' => $this->pathPrefix . '/login',
        ]));
    }

    /**
     * @return FormInterface<array<string, mixed>|null>
     */
    private function createLogoutForm(): FormInterface
    {
        return $this->formFactory->createNamed('', LogoutMaintenanceType::class, null, $this->formOptions([
            'action' => $this->pathPrefix . '/logout',
        ]));
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function formOptions(array $options = []): array
    {
        return array_merge([
            'csrf_protection' => $this->csrfTokenManager instanceof CsrfTokenManagerInterface,
        ], $options);
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
