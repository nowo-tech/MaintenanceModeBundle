<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

use function password_verify;

/**
 * Default gate: optional password hash (bcrypt / argon2id / sodium) from config.
 * When no hash is configured (or protection is disabled), access is open.
 */
final class PasswordMaintenanceAccessGate implements MaintenanceAccessGateInterface
{
    public const SESSION_KEY = '_nowo_maintenance_mode_authorized';

    public function __construct(
        private readonly ?string $passwordHash,
        private readonly bool $enabled = true,
    ) {
    }

    public function isPasswordRequired(): bool
    {
        return $this->enabled && $this->passwordHash !== null && $this->passwordHash !== '';
    }

    public function isGranted(Request $request): bool
    {
        if (!$this->isPasswordRequired()) {
            return true;
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        if (!$session instanceof SessionInterface) {
            return false;
        }

        return $session->get(self::SESSION_KEY) === true;
    }

    public function authenticate(Request $request, string $password): bool
    {
        if (!$this->isPasswordRequired()) {
            return true;
        }

        /** @var non-empty-string $hash */
        $hash = $this->passwordHash;
        if (!password_verify($password, $hash)) {
            return false;
        }

        if ($request->hasSession()) {
            $request->getSession()->set(self::SESSION_KEY, true);
        }

        return true;
    }

    public function revoke(Request $request): void
    {
        if ($request->hasSession()) {
            $request->getSession()->remove(self::SESSION_KEY);
        }
    }
}
