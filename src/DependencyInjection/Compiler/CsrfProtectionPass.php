<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\DependencyInjection\Compiler;

use LogicException;
use Nowo\MaintenanceModeBundle\Controller\MaintenancePanelController;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Fails closed when the maintenance panel is enabled without form CSRF protection (REQ-SEC-005).
 *
 * The mutations of the maintenance panel rely on Symfony's form CSRF extension: forms that declare their own `csrf_protection` /
 * `csrf_token_id` options would otherwise validate without a token when `form.type_extension.csrf` is missing or
 * `framework.form.csrf_protection` is false. The container refuses to compile instead.
 *
 * MaintenancePanelController already denies mutations without a token manager; this pass also covers
 * `framework.form.csrf_protection: false`, where the token manager exists but forms skip validation.
 */
final class CsrfProtectionPass implements CompilerPassInterface
{
    /**
     * Throws when the maintenance panel is enabled and form CSRF protection or the CSRF token manager is unavailable.
     */
    public function process(ContainerBuilder $container): void
    {
        // The extension removes the controller when web_ui.enabled is false.
        if (!$container->hasDefinition(MaintenancePanelController::class)) {
            return;
        }

        $formCsrfDisabled = $container->hasParameter('form.type_extension.csrf.enabled')
            && $container->getParameter('form.type_extension.csrf.enabled') === false;

        if (
            !$container->has('security.csrf.token_manager')
            || !$container->hasDefinition('form.type_extension.csrf')
            || $formCsrfDisabled
        ) {
            throw new LogicException('NowoMaintenanceModeBundle: the panel (web_ui.enabled: true) requires CSRF protection. Enable framework.csrf_protection and framework.form.csrf_protection (composer require symfony/security-csrf), or set nowo_maintenance_mode.web_ui.enabled: false.');
        }
    }
}
