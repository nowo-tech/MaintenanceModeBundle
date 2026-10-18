<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Support;

use DateTimeImmutable;
use Nowo\MaintenanceModeBundle\Form\ClearScheduleType;
use Nowo\MaintenanceModeBundle\Form\DisableMaintenanceType;
use Nowo\MaintenanceModeBundle\Form\EnableMaintenanceType;
use Nowo\MaintenanceModeBundle\Form\LoginMaintenanceType;
use Nowo\MaintenanceModeBundle\Form\LogoutMaintenanceType;
use Nowo\MaintenanceModeBundle\Form\ScheduleMaintenanceType;
use Nowo\MaintenanceModeBundle\Model\MaintenanceHistoryEntry;
use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Nowo\MaintenanceModeBundle\Panel\PanelView;
use Nowo\MaintenanceModeBundle\View\MaintenancePages;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * Realistic page contexts for the bundle templates, shaped like the controllers / the 503 subscriber build them
 * (the {@see MaintenancePages} view models + the 1.x variables). Used by the page contract tests.
 */
final class PageFixtures
{
    public const PREFIX = '/_maintenance';

    public static function enabledState(): MaintenanceState
    {
        return (new MaintenanceState())
            ->withEnabled(true)
            ->withMessage('Database upgrade in progress — back at 14:30.')
            ->withActivatedAt(new DateTimeImmutable('2026-10-18 13:45:00'))
            ->withScheduledDisableAt(new DateTimeImmutable('2099-10-18 14:30:00'))
            ->withUpdatedBy('panel');
    }

    public static function disabledState(): MaintenanceState
    {
        return (new MaintenanceState())
            ->withEnabled(false)
            ->withActivatedAt(new DateTimeImmutable('2026-10-17 09:00:00'))
            ->withDeactivatedAt(new DateTimeImmutable('2026-10-17 09:20:00'))
            ->withScheduledEnableAt(new DateTimeImmutable('2099-10-20 02:00:00'))
            ->withScheduledDisableAt(new DateTimeImmutable('2099-10-20 03:00:00'))
            ->withUpdatedBy('cli');
    }

    /**
     * @return list<MaintenanceHistoryEntry>
     */
    public static function entries(int $count = 3): array
    {
        $base = [
            new MaintenanceHistoryEntry('enable', new DateTimeImmutable('2026-10-18 13:45:00'), 'panel', 'Database upgrade in progress — back at 14:30.'),
            new MaintenanceHistoryEntry('schedule', new DateTimeImmutable('2026-10-17 18:00:00'), 'cli'),
            new MaintenanceHistoryEntry('disable', new DateTimeImmutable('2026-10-17 09:20:00')),
        ];
        $entries = [];
        for ($i = 0; $i < $count; ++$i) {
            $entries[] = $base[$i % 3];
        }

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    public static function index(MaintenanceState $state, bool $passwordRequired = true): array
    {
        $f     = self::factory();
        $forms = [
            'enable_form'   => $f->createNamed('', EnableMaintenanceType::class, ['message' => $state->getMessage()], ['action' => self::PREFIX . '/enable'])->createView(),
            'disable_form'  => $f->createNamed('', DisableMaintenanceType::class, null, ['action' => self::PREFIX . '/disable'])->createView(),
            'schedule_form' => $f->createNamed('', ScheduleMaintenanceType::class, [
                'scheduled_enable_at'  => $state->getScheduledEnableAt(),
                'scheduled_disable_at' => $state->getScheduledDisableAt(),
                'message'              => $state->getMessage(),
            ], ['action' => self::PREFIX . '/schedule'])->createView(),
            'clear_schedule_form' => $f->createNamed('', ClearScheduleType::class, null, ['action' => self::PREFIX . '/clear-schedule'])->createView(),
            'logout_form'         => $f->createNamed('', LogoutMaintenanceType::class, null, ['action' => self::PREFIX . '/logout'])->createView(),
        ];
        $view = self::view();

        return MaintenancePages::panel(
            $state,
            $forms['enable_form'],
            $forms['disable_form'],
            $forms['schedule_form'],
            $forms['clear_schedule_form'],
            $passwordRequired ? $forms['logout_form'] : null,
            $view->nav('panel'),
            $view->href('/disable'),
            $view->href('/clear-schedule'),
            $view->href('/logout'),
        ) + $forms + [
            'state'             => $state,
            'flashes'           => [],
            'password_required' => $passwordRequired,
            'path_prefix'       => self::PREFIX,
        ];
    }

    /**
     * @param list<MaintenanceHistoryEntry> $entries
     *
     * @return array<string, mixed>
     */
    public static function history(array $entries, int $page = 1, int $perPage = 25): array
    {
        return [
            'page'        => MaintenancePages::history($entries, $page, $perPage, self::view()->nav('history')),
            'entries'     => $entries,
            'path_prefix' => self::PREFIX,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function login(?string $error = null, bool $locked = false): array
    {
        $form = self::factory()->createNamed('', LoginMaintenanceType::class, null, ['action' => self::PREFIX . '/login'])->createView();

        return [
            'page'        => MaintenancePages::login($form, $error, $locked),
            'error'       => $error,
            'locked'      => $locked,
            'login_form'  => $form,
            'path_prefix' => self::PREFIX,
        ];
    }

    /**
     * Context of the public page as the 503 subscriber renders it.
     *
     * @return array<string, mixed>
     */
    public static function maintenance(?string $message = null): array
    {
        $state = self::enabledState()->withMessage($message);

        return ['message' => $message, 'state' => $state, 'page' => MaintenancePages::maintenance($message)];
    }

    public static function view(): PanelView
    {
        return new PanelView(new Environment(new ArrayLoader()), self::PREFIX);
    }

    public static function factory(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addExtension(new CsrfExtension(new class implements CsrfTokenManagerInterface {
                public function getToken(string $tokenId): CsrfToken
                {
                    return new CsrfToken($tokenId, 'token-' . $tokenId);
                }

                public function refreshToken(string $tokenId): CsrfToken
                {
                    return $this->getToken($tokenId);
                }

                public function removeToken(string $tokenId): ?string
                {
                    return null;
                }

                public function isTokenValid(CsrfToken $token): bool
                {
                    return true;
                }
            }))
            ->getFormFactory();
    }
}
