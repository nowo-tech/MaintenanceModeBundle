<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Exclusion;

use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;

use function fnmatch;
use function in_array;
use function is_string;
use function preg_match;
use function str_ends_with;
use function str_starts_with;
use function substr;

/**
 * Matches requests against configured path / route / pattern / IP exclusions.
 */
final class MaintenanceExclusionMatcher
{
    /**
     * @param list<string> $paths Exact paths
     * @param list<string> $pathPrefixes Segment-aware path prefixes (e.g. /api matches /api and /api/…, not /apix);
     *                                   a trailing "*" (e.g. /api*) or "/" keeps a raw string-prefix match
     * @param list<string> $routes Route names
     * @param list<string> $patterns Glob or #regex# patterns against the path
     * @param list<string> $ips Client IPs or CIDR ranges (see trusted_proxies)
     */
    public function __construct(
        private readonly array $paths = [],
        private readonly array $pathPrefixes = [],
        private readonly array $routes = [],
        private readonly array $patterns = [],
        private readonly array $ips = [],
    ) {
    }

    public function matches(Request $request): bool
    {
        $path = $request->getPathInfo();

        if (in_array($path, $this->paths, true)) {
            return true;
        }

        foreach ($this->pathPrefixes as $prefix) {
            if ($this->matchesPrefix($path, $prefix)) {
                return true;
            }
        }

        $route = $request->attributes->get('_route');
        if (is_string($route) && $route !== '' && in_array($route, $this->routes, true)) {
            return true;
        }

        foreach ($this->patterns as $pattern) {
            if ($pattern === '') {
                continue;
            }

            if ($this->isRegex($pattern)) {
                if (@preg_match($pattern, $path) === 1) {
                    return true;
                }

                continue;
            }

            if (fnmatch($pattern, $path)) {
                return true;
            }
        }

        if ($this->ips !== []) {
            $clientIp = $request->getClientIp();
            if (is_string($clientIp) && $clientIp !== '' && IpUtils::checkIp($clientIp, $this->ips)) {
                return true;
            }
        }

        return false;
    }

    /**
     * "/api" matches "/api" and "/api/…" but not "/apix" (segment-aware).
     * "/api*" is a raw prefix (also matches "/apix"); a prefix ending in "/" is matched as-is.
     */
    private function matchesPrefix(string $path, string $prefix): bool
    {
        if ($prefix === '') {
            return false;
        }

        if (str_ends_with($prefix, '*')) {
            $raw = substr($prefix, 0, -1);

            return $raw !== '' && str_starts_with($path, $raw);
        }

        if (str_ends_with($prefix, '/')) {
            return str_starts_with($path, $prefix);
        }

        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }

    private function isRegex(string $pattern): bool
    {
        // Do not treat leading "/" as regex — path globs usually start with "/".
        return str_starts_with($pattern, '#') || str_starts_with($pattern, '~');
    }
}
