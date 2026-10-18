<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\View;

use DateTimeImmutable;
use Nowo\MaintenanceModeBundle\Model\MaintenanceHistoryEntry;
use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Nowo\MaintenanceModeBundle\Panel\PanelView;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\AreaNav;
use Nowo\UiKitBundle\View\AuthPage;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\DetailItem;
use Nowo\UiKitBundle\View\EmptyState;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\Pagination;
use Nowo\UiKitBundle\View\SettingsPage;
use Nowo\UiKitBundle\View\SettingsSection;
use Symfony\Component\Form\FormView;

use function array_slice;
use function ceil;
use function count;
use function max;
use function min;

/**
 * UiKit view models of the bundle pages (REQ-UI-003): the panel (settings scaffold), the history (list scaffold),
 * the password login (auth scaffold) and the public maintenance page (guest layout). Plain data, no services
 * (worker safe); labels are {@code NowoMaintenanceModeBundle} translation keys translated by the scaffolds. The
 * controllers, the 503 subscriber, the preview controller and the contract test share these builders.
 */
final class MaintenancePages
{
    public const DATE_FORMAT = 'Y-m-d H:i:s';

    /**
     * Panel page ({@code $nav}: {@see PanelView::nav()} with {@code panel} active): sections status (no form), manual (enable form + disable action) and schedule (schedule form +
     * clear action); header action Logout when the password gate is on; area nav Panel | History.
     *
     * @return array{page: SettingsPage, section_actions: array<string, list<Action>>, status_items: list<DetailItem>}
     */
    public static function panel(
        MaintenanceState $state,
        FormView $enableForm,
        FormView $disableForm,
        FormView $scheduleForm,
        FormView $clearScheduleForm,
        ?FormView $logoutForm,
        AreaNav $nav,
        string $disableHref,
        string $clearScheduleHref,
        ?string $logoutHref = null,
        ?string $layout = null,
    ): array {
        $actions = [];
        if ($logoutForm instanceof FormView && $logoutHref !== null) {
            $actions[] = new Action('custom', PanelView::label('panel.logout'), $logoutHref, method: 'POST', variant: 'secondary', attributes: ['data-testid' => 'maintenance-logout'], form: $logoutForm);
        }

        return [
            'page' => new SettingsPage(
                new PageHeader(PanelView::label('panel.title'), PanelView::label('panel.intro'), $actions, tabs: $nav),
                [
                    new SettingsSection('status', PanelView::label('panel.status')),
                    new SettingsSection('manual', PanelView::label('panel.manual'), form: $enableForm, intro: PanelView::label('panel.manual.intro'), submitLabel: PanelView::label('panel.enable')),
                    new SettingsSection('schedule', PanelView::label('panel.schedule'), form: $scheduleForm, intro: PanelView::label('panel.schedule.intro'), submitLabel: PanelView::label('panel.save_schedule')),
                ],
                bundle: PanelView::BUNDLE,
                layout: $layout,
                attributes: ['data-testid' => 'maintenance-panel'],
            ),
            'section_actions' => [
                'manual' => [
                    new Action('custom', PanelView::label('panel.disable'), $disableHref, method: 'POST', variant: 'danger', attributes: ['data-testid' => 'maintenance-disable'], form: $disableForm),
                ],
                'schedule' => [
                    new Action('custom', PanelView::label('panel.clear_schedule'), $clearScheduleHref, method: 'POST', variant: 'secondary', attributes: ['data-testid' => 'maintenance-clear-schedule'], form: $clearScheduleForm),
                ],
            ],
            'status_items' => [
                new DetailItem(PanelView::label('panel.message'), $state->getMessage()),
                new DetailItem(PanelView::label('panel.activated'), self::date($state->getActivatedAt())),
                new DetailItem(PanelView::label('panel.deactivated'), self::date($state->getDeactivatedAt())),
                new DetailItem(PanelView::label('panel.scheduled_enable'), self::date($state->getScheduledEnableAt())),
                new DetailItem(PanelView::label('panel.scheduled_disable'), self::date($state->getScheduledDisableAt())),
                new DetailItem(PanelView::label('panel.updated_by'), $state->getUpdatedBy()),
            ],
        ];
    }

    /**
     * History page ({@code $nav} with {@code history} active): the most recent entries (newest first), paginated in memory.
     *
     * @param list<MaintenanceHistoryEntry> $entries all listed entries (at most web_ui.history_limit)
     */
    public static function history(array $entries, int $page, int $perPage, AreaNav $nav, ?string $layout = null): ListPage
    {
        $perPage    = max(1, $perPage);
        $total      = count($entries);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page       = min(max(1, $page), $totalPages);

        return new ListPage(
            new PageHeader(PanelView::label('panel.history'), PanelView::label('panel.history.intro'), tabs: $nav),
            [
                new Column('occurredAt', PanelView::label('panel.history.when'), required: true),
                new Column('action', PanelView::label('panel.history.action'), required: true),
                new Column('actor', PanelView::label('panel.history.actor'), priority: 2),
                new Column('message', PanelView::label('panel.history.message'), priority: 3),
            ],
            array_slice($entries, ($page - 1) * $perPage, $perPage),
            pagination: $total > $perPage ? new Pagination($page, $perPage, $total, $totalPages, 'nowo_maintenance_mode_panel_history') : null,
            empty: new EmptyState(PanelView::label('panel.history.empty')),
            bundle: PanelView::BUNDLE,
            layout: $layout,
            attributes: ['data-testid' => 'maintenance-history'],
        );
    }

    /**
     * Password gate login (auth scaffold on the guest layout).
     */
    public static function login(FormView $form, ?string $error = null, bool $locked = false): AuthPage
    {
        return new AuthPage(
            PanelView::label('panel.login.heading'),
            $form,
            PanelView::label('panel.login.intro'),
            null,
            $error !== null ? PanelView::label($locked ? 'panel.login.locked' : 'panel.login.error') : null,
            PanelView::label('panel.login.submit'),
            bundle: PanelView::BUNDLE,
            attributes: ['data-testid' => 'maintenance-login'],
        );
    }

    /**
     * Public maintenance page (503 and preview). The default template is a unique page on the guest layout; a host
     * template set in {@code templates.page} may extend {@code @NowoUiKit/page/auth.html.twig} with this model.
     */
    public static function maintenance(?string $message = null, ?string $layout = null): AuthPage
    {
        return new AuthPage(
            PanelView::label('maintenance.page.heading'),
            intro: $message !== null && $message !== '' ? $message : PanelView::label('maintenance.page.message'),
            brand: PanelView::label('maintenance.page.badge'),
            bundle: PanelView::BUNDLE,
            layout: $layout,
            attributes: ['data-testid' => 'maintenance-page'],
        );
    }

    private static function date(?DateTimeImmutable $date): ?string
    {
        return $date?->format(self::DATE_FORMAT);
    }
}
