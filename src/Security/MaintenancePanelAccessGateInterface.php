<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Security;

/**
 * Pluggable gate for the maintenance admin panel, framework-free (REQ-ARCH-001 rule 3). Replaces the `Request`-typed
 * {@see MaintenanceAccessGateInterface} (deprecated since 1.17, removed in 2.0).
 *
 * Set `security.access_gate` to a service implementing this interface. When a gate implements both interfaces, the
 * panel uses this one. Deny by default: return false from {@see self::isGrantedFor()} when unsure.
 */
interface MaintenancePanelAccessGateInterface
{
    /**
     * Whether the panel requires a password challenge.
     */
    public function isPasswordRequired(): bool;

    /**
     * Whether this request is already authorized for the panel.
     */
    public function isGrantedFor(MaintenanceAccessContext $context): bool;

    /**
     * Whether login attempts are refused for now (too many failures for this session or client IP).
     */
    public function isLockedOutFor(MaintenanceAccessContext $context): bool;

    /**
     * Validate a submitted password and, on success, mark the session as authorized.
     */
    public function authenticateFor(MaintenanceAccessContext $context, string $password): bool;

    /**
     * Clear panel authorization for this session.
     */
    public function revokeFor(MaintenanceAccessContext $context): void;
}
