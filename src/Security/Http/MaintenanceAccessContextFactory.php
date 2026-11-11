<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Security\Http;

use Nowo\MaintenanceModeBundle\Security\MaintenanceAccessContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

use function array_values;
use function is_string;

/**
 * HTTP edge: turns a Symfony `Request` (and the security token, when SecurityBundle is installed) into a
 * {@see MaintenanceAccessContext}. Stateless; call it per request.
 *
 * - Client IP: `Request::getClientIp()`, so the host's `framework.trusted_proxies` / `trusted_headers` apply exactly as
 *   in 1.x (an untrusted `X-Forwarded-For` is ignored).
 * - Headers: only {@see self::HEADERS} (never `Cookie` / `Authorization`).
 * - Session: the request's session when it has one, else null (same as the 1.x `hasSession()` checks).
 */
final class MaintenanceAccessContextFactory
{
    /** Headers copied into the context (lower-case). */
    public const HEADERS = ['user-agent', 'accept-language'];

    public static function fromRequest(Request $request, ?TokenStorageInterface $tokenStorage = null): MaintenanceAccessContext
    {
        $headers = [];
        foreach (self::HEADERS as $name) {
            $value = $request->headers->get($name);
            if (is_string($value)) {
                $headers[$name] = $value;
            }
        }

        $token = $tokenStorage?->getToken();
        $user  = $token instanceof TokenInterface ? $token->getUserIdentifier() : null;
        $roles = $token instanceof TokenInterface ? array_values($token->getRoleNames()) : [];

        return new MaintenanceAccessContext(
            path: $request->getPathInfo(),
            method: $request->getMethod(),
            clientIp: $request->getClientIp(),
            headers: $headers,
            userIdentifier: $user,
            roles: $roles,
            session: $request->hasSession() ? new SymfonyMaintenanceAccessSession($request->getSession()) : null,
        );
    }
}
