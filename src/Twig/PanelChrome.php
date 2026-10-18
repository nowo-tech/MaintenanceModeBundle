<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Twig;

use function is_string;
use function trim;

/**
 * 1.x admin chrome hooks (`web_ui.classes`, `web_ui.partials`, 1.7.0).
 *
 * @deprecated since 1.10, the panel pages extend the UiKit scaffolds (REQ-UI-003) and read the UiKit theme slots.
 *             The class map is bridged into {@code nowo_ui_kit.theme.bundles.NowoMaintenanceModeBundle} by
 *             {@see self::themeBridge()} for one minor version; removed in 2.0. The constants still feed the
 *             deprecated Twig global {@code nowo_maintenance_mode_chrome} for 1.x host templates.
 */
final class PanelChrome
{
    /**
     * 1.x chrome class map keys => 1.x default classes.
     */
    public const DEFAULT_CLASSES = [
        'nav'              => 'nowo-ui-nav',
        'nav_link'         => '',
        'page_header'      => 'nowo-ui-page-header',
        'page_title'       => '',
        'page_intro'       => '',
        'flash'            => '',
        'status_line'      => '',
        'status_on'        => 'nowo-ui-status nowo-ui-status--on status on',
        'status_off'       => 'nowo-ui-status nowo-ui-status--off status off',
        'card'             => 'nowo-ui-card card',
        'section_title'    => '',
        'form'             => '',
        'form_actions'     => '',
        'table_wrap'       => 'nowo-ui-card nowo-ui-table-wrap card',
        'table'            => 'nowo-ui-table',
        'table_head_row'   => '',
        'table_head_cell'  => '',
        'table_row'        => '',
        'table_cell'       => '',
        'table_cell_muted' => '',
        'empty'            => 'nowo-ui-empty',
        'login_error'      => 'nowo-ui-error error',
    ];

    /**
     * Keys accepted by the deprecated {@code web_ui.classes}.
     *
     * @var list<string>
     */
    public const CLASS_KEYS = [
        'nav', 'nav_link', 'page_header', 'page_title', 'page_intro', 'flash', 'status_line', 'status_on', 'status_off',
        'card', 'section_title', 'form', 'form_actions', 'table_wrap', 'table', 'table_head_row', 'table_head_cell',
        'table_row', 'table_cell', 'table_cell_muted', 'empty', 'login_error',
    ];

    /**
     * 1.x class key => UiKit theme slot (null: no equivalent region, the value is dropped).
     *
     * @var array<string, string|null>
     */
    public const CLASS_SLOTS = [
        'nav'              => 'tabs',
        'nav_link'         => 'tab',
        'page_header'      => 'page_header',
        'page_title'       => 'page_title',
        'page_intro'       => 'page_intro',
        'flash'            => 'flash',
        'status_line'      => null,
        'status_on'        => 'badge_warning',
        'status_off'       => 'badge_success',
        'card'             => 'card',
        'section_title'    => 'card_title',
        'form'             => 'form',
        'form_actions'     => 'form_actions',
        'table_wrap'       => 'table_wrap',
        'table'            => 'table',
        'table_head_row'   => 'table_head_row',
        'table_head_cell'  => 'table_head_cell',
        'table_row'        => 'table_row',
        'table_cell'       => 'table_cell',
        'table_cell_muted' => 'table_cell_muted',
        'empty'            => 'empty',
        'login_error'      => 'flash_error',
    ];

    /**
     * 1.x partial paths (`pagination` null = no pagination include). Their inputs (`active`, `path_prefix`, `form`,
     * `entries`) do not match any UiKit partial: the deprecated {@code web_ui.partials} map is ignored.
     */
    public const DEFAULT_PARTIALS = [
        'nav'         => '@NowoMaintenanceModeBundle/panel/_nav.html.twig',
        'form_fields' => '@NowoMaintenanceModeBundle/panel/_form_fields.html.twig',
        'pagination'  => null,
    ];

    /**
     * 1.x default chrome array exposed to Twig as the deprecated global `nowo_maintenance_mode_chrome`.
     */
    public const DEFAULTS = [
        'show_page_header' => true,
        'document_title'   => true,
        'test_ids'         => false,
        'classes'          => self::DEFAULT_CLASSES,
        'partials'         => self::DEFAULT_PARTIALS,
    ];

    /**
     * Bridges the deprecated {@code web_ui.classes} map into a {@code nowo_ui_kit.theme.bundles.NowoMaintenanceModeBundle}
     * entry. A 1.x class value replaced the whole class list, so the slot drops UiKit's framework defaults
     * ({@code framework_defaults: false}) and keeps only its semantic {@code nowo-ui-*} class plus the host value.
     * Empty values (1.x "no class attribute"), 1.x
     * defaults and keys without a slot are dropped.
     *
     * @param array<array-key, mixed> $classes web_ui.classes
     *
     * @return array{classes?: array<string, array{value: string, framework_defaults: bool}>}
     */
    public static function themeBridge(array $classes): array
    {
        $bridge = [];
        foreach ($classes as $key => $value) {
            $slot = is_string($key) ? (self::CLASS_SLOTS[$key] ?? null) : null;
            if ($slot === null || !is_string($value) || trim($value) === '') {
                continue;
            }
            // A host that repeated a 1.x default changes nothing: the slot default already renders the same region.
            if (trim($value) !== self::DEFAULT_CLASSES[$key]) {
                $bridge['classes'][$slot] = ['value' => trim($value), 'framework_defaults' => false];
            }
        }

        return $bridge;
    }
}
