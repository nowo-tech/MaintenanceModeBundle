<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Security;

use function array_keys;
use function array_values;
use function in_array;
use function is_string;
use function strtolower;

/**
 * Framework-free description of one panel request, passed to {@see MaintenancePanelAccessGateInterface} and
 * {@see MaintenanceActorResolverInterface} (REQ-ARCH-001 rule 3; replaces the `Request` of the 1.x ports).
 *
 * - `clientIp` is the address the edge resolved, honouring the host's trusted proxies (the Symfony adapter uses
 *   `Request::getClientIp()`, exactly what the 1.x gate used), or null when unknown.
 * - `headers` holds only the allow-listed headers the edge copied (lower-case names); never cookies or credentials.
 * - `userIdentifier` / `roles` describe the authenticated user, if any (null / [] when anonymous).
 * - `session` is the visitor's session, or null when the request has none (then a password gate denies).
 *
 * Build it per request; never keep it in a service (FrankenPHP worker mode, kernel reset false).
 */
final readonly class MaintenanceAccessContext
{
    /** @var array<string, string> */
    public array $headers;

    /** @var list<string> */
    public array $roles;

    public ?string $userIdentifier;

    /**
     * @param array<string, string> $headers header name => value
     * @param array<int, string> $roles
     */
    public function __construct(
        public string $path,
        public string $method = 'GET',
        public ?string $clientIp = null,
        array $headers = [],
        ?string $userIdentifier = null,
        array $roles = [],
        public ?MaintenanceAccessSessionInterface $session = null,
    ) {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower($name)] = $value;
        }
        $this->headers        = $normalized;
        $this->userIdentifier = $userIdentifier === '' ? null : $userIdentifier;
        $this->roles          = array_values($roles);
    }

    public function header(string $name): ?string
    {
        $value = $this->headers[strtolower($name)] ?? null;

        return is_string($value) ? $value : null;
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function isAuthenticated(): bool
    {
        return $this->userIdentifier !== null;
    }

    /**
     * What `dump()` / `var_dump()` / `print_r()` show (audit MK-S-L3): the client IP is masked, header values and the
     * session are left out, so a debug dump or a log of the context never carries them.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'path'           => $this->path,
            'method'         => $this->method,
            'clientIp'       => $this->clientIp === null ? null : '[masked]',
            'headers'        => array_keys($this->headers),
            'userIdentifier' => $this->userIdentifier,
            'roles'          => $this->roles,
            'session'        => $this->session instanceof MaintenanceAccessSessionInterface ? '[redacted]' : null,
        ];
    }
}
