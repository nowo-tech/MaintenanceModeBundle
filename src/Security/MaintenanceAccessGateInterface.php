<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Security;

use Symfony\Component\HttpFoundation\Request;

/**
 * Pluggable gate for the maintenance admin panel.
 * Replace via DI with a voter / authenticator of your own.
 *
 * @deprecated since 1.17, implement the framework-free {@see MaintenancePanelAccessGateInterface} instead (REQ-ARCH-001
 *             rule 3: no `Request` in public ports); removed in 2.0. The panel still calls a gate that implements only
 *             this interface, and triggers a deprecation when it does.
 */
interface MaintenanceAccessGateInterface
{
    /**
     * Whether the panel requires a password challenge.
     */
    public function isPasswordRequired(): bool;

    /**
     * Whether the current request is already authorized for the panel.
     */
    public function isGranted(Request $request): bool;

    /**
     * Validate a submitted password and optionally mark the request as authorized.
     */
    public function authenticate(Request $request, string $password): bool;

    /**
     * Clear panel authorization for the request/session.
     */
    public function revoke(Request $request): void;
}
