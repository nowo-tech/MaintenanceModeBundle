<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Twig;

/**
 * Defaults for the admin panel chrome hooks (`web_ui.classes`, `web_ui.partials`, …).
 *
 * The class map values equal the classes hard-coded before 1.7.0, so the default
 * rendered output is unchanged. An empty string means "no class attribute".
 */
final class PanelChrome
{
    /**
     * Chrome class map keys => default classes.
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
     * Partial template paths. `pagination` null = no pagination include.
     */
    public const DEFAULT_PARTIALS = [
        'nav'         => '@NowoMaintenanceModeBundle/panel/_nav.html.twig',
        'form_fields' => '@NowoMaintenanceModeBundle/panel/_form_fields.html.twig',
        'pagination'  => null,
    ];

    /**
     * Full default chrome array exposed to Twig as `nowo_maintenance_mode_chrome`.
     */
    public const DEFAULTS = [
        'show_page_header' => true,
        'document_title'   => true,
        'test_ids'         => false,
        'classes'          => self::DEFAULT_CLASSES,
        'partials'         => self::DEFAULT_PARTIALS,
    ];
}
